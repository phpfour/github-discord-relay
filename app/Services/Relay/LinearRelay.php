<?php

namespace App\Services\Relay;

use App\Models\Setting;
use App\Models\WebhookRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Faithful port of the original LinearWebhookController pipeline: 24h
 * deduplication, per-type Discord embed construction, configurable skip
 * filter, verbose logging to the "webhooks" channel.
 */
class LinearRelay
{
    public const RESULT_DUPLICATE = 'duplicate';

    public const RESULT_PROCESSED = 'processed';

    public function __construct(
        private readonly MentionMapper $mentions,
    ) {}

    /**
     * Run the full Linear relay pipeline against a resolved destination.
     *
     * @param  array<mixed>  $payload
     */
    public function handle(WebhookRoute $route, array $payload, Request $request): string
    {
        $eventId = $this->generateEventId($payload);

        if ($this->isEventProcessed($eventId)) {
            Log::channel('webhooks')->info('Duplicate event received and ignored', ['eventId' => $eventId]);

            return self::RESULT_DUPLICATE;
        }

        $this->logWebhook($payload, $request);

        $discordPayload = $this->transformToDiscordFormat($payload);

        $sent = $this->sendToDiscord($route, $payload, $discordPayload);

        // Only mark processed once we have actually attempted a send. Skipped
        // events are intentionally left unmarked.
        if ($sent) {
            $this->markEventAsProcessed($eventId);
        }

        return self::RESULT_PROCESSED;
    }

    /**
     * @param  array<mixed>  $payload
     */
    public function generateEventId(array $payload): string
    {
        $components = [
            $payload['type'] ?? '',
            $payload['action'] ?? '',
            $payload['data']['id'] ?? '',
            $payload['createdAt'] ?? '',
        ];

        return md5(implode('|', $components));
    }

    public function isEventProcessed(string $eventId): bool
    {
        return Cache::has("processed_event:{$eventId}");
    }

    public function markEventAsProcessed(string $eventId): void
    {
        Cache::put("processed_event:{$eventId}", true, now()->addHours(24));
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function logWebhook(array $payload, Request $request): void
    {
        Log::channel('webhooks')->info('Incoming Linear webhook', [
            'headers' => $request->headers->all(),
            'payload' => $payload,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Whether the event should be relayed, based on the configurable skip
     * filter (default: suppress "issue" -> "update").
     *
     * @param  array<mixed>  $payload
     */
    public function shouldProcess(array $payload): bool
    {
        $action = strtolower($payload['action'] ?? 'unknown');
        $type = strtolower($payload['type'] ?? 'unknown');

        $skip = $this->skipActions();

        if (isset($skip[$type]) && in_array($action, $skip[$type], true)) {
            return false;
        }

        return true;
    }

    /**
     * The skip-filter configuration, sourced from settings with a faithful
     * default of suppressing issue updates.
     *
     * @return array<string, list<string>>
     */
    private function skipActions(): array
    {
        $raw = Setting::get('linear_skip_filter');

        if ($raw === null) {
            return ['issue' => ['update']];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['issue' => ['update']];
    }

    /**
     * Build the Discord payload from a Linear webhook payload.
     *
     * @param  array<mixed>  $linearPayload
     * @return array<mixed>
     */
    public function transformToDiscordFormat(array $linearPayload): array
    {
        $action = $linearPayload['action'] ?? 'unknown';
        $type = $linearPayload['type'] ?? 'unknown';
        $data = $linearPayload['data'] ?? [];

        $content = $this->generateContent($linearPayload);

        $embed = [
            'title' => $this->generateEmbedTitle($type, $action, $data),
            'color' => $this->getColorForAction($action),
            'fields' => [],
            'footer' => [
                'text' => 'Sent via Linear Webhook',
            ],
            'timestamp' => $linearPayload['createdAt'] ?? date('c'),
        ];

        $this->addProjectField($embed, $data);

        switch ($type) {
            case 'Issue':
                $this->addIssueFields($embed, $data);
                break;
            case 'Comment':
                $this->addCommentFields($embed, $data);
                break;
            case 'Project':
                $this->addProjectFields($embed, $data);
                break;
            case 'ProjectUpdate':
                $this->addProjectUpdateFields($embed, $data);
                break;
            default:
                $embed['description'] = "Unhandled event type: $type";
        }

        $sender = $this->getSender($linearPayload);

        return [
            'content' => $content,
            'embeds' => [$embed],
            'avatar_url' => $sender['avatar_url'],
            'username' => $sender['username'],
        ];
    }

    /**
     * @param  array<mixed>  $payload
     */
    private function generateContent(array $payload): string
    {
        $type = $payload['type'] ?? 'unknown';
        $action = $payload['action'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        $actorId = $payload['actor']['id'] ?? null;
        $userTag = $actorId ? $this->getDiscordTag($actorId) : 'Someone';

        $projectName = $data['project']['name'] ?? 'Unknown Project';

        switch ($type) {
            case 'Issue':
                $content = "{$userTag} {$this->getActionVerb($action)} an issue";
                if ($action !== 'remove') {
                    $content .= " titled \"{$data['title']}\"";
                }
                $content .= " in {$projectName}.";
                if (isset($data['assignee']['id'])) {
                    $assigneeTag = $this->getDiscordTag($data['assignee']['id']);
                    $content .= " Assigned to {$assigneeTag}.";
                }
                break;
            case 'Comment':
                $content = "{$userTag} commented on the issue \"{$data['issue']['title']}\" in {$projectName}.";
                break;
            case 'Project':
                $content = "{$userTag} {$this->getActionVerb($action)} the project \"{$projectName}\".";
                break;
            case 'ProjectUpdate':
                $content = "{$userTag} posted an update to the project \"{$projectName}\".";
                break;
            default:
                $content = "{$userTag} performed an action in {$projectName}.";
        }

        return $content;
    }

    private function getActionVerb(string $action): string
    {
        return match ($action) {
            'create' => 'created',
            'update' => 'updated',
            'remove' => 'removed',
            default => 'modified',
        };
    }

    /**
     * @param  array<mixed>  $data
     */
    private function generateEmbedTitle(string $type, string $action, array $data): string
    {
        $actionVerb = ucfirst($this->getActionVerb($action));

        return match ($type) {
            'Issue' => "{$actionVerb} Issue: {$data['title']}",
            'Comment' => "New Comment on Issue: {$data['issue']['title']}",
            'Project' => "{$actionVerb} Project: {$data['name']}",
            'ProjectUpdate' => "Project Update: {$data['project']['name']}",
            default => "New Linear Event: {$actionVerb} {$type}",
        };
    }

    /**
     * @param  array<mixed>  $embed
     * @param  array<mixed>  $data
     */
    private function addProjectField(array &$embed, array $data): void
    {
        $embed['fields'][] = [
            'name' => 'Project',
            'value' => $data['project']['name'] ?? 'Unknown Project',
            'inline' => true,
        ];
    }

    /**
     * @param  array<mixed>  $embed
     * @param  array<mixed>  $data
     */
    private function addIssueFields(array &$embed, array $data): void
    {
        $embed['fields'][] = [
            'name' => 'Status',
            'value' => $data['state']['name'] ?? 'Unknown',
            'inline' => true,
        ];

        $embed['fields'][] = [
            'name' => 'Assignee',
            'value' => $data['assignee']['name'] ?? 'Unassigned',
            'inline' => true,
        ];

        $embed['fields'][] = [
            'name' => 'Priority',
            'value' => $this->getPriorityEmoji($data['priority'] ?? null).' '.($data['priorityLabel'] ?? 'N/A'),
            'inline' => true,
        ];

        if (isset($data['description'])) {
            $embed['fields'][] = [
                'name' => 'Description',
                'value' => $this->truncateText($data['description'], 1024),
                'inline' => false,
            ];
        }

        $embed['url'] = $data['url'] ?? null;
    }

    /**
     * @param  array<mixed>  $embed
     * @param  array<mixed>  $data
     */
    private function addCommentFields(array &$embed, array $data): void
    {
        $embed['fields'][] = [
            'name' => 'Issue',
            'value' => $data['issue']['title'] ?? 'Unknown Issue',
            'inline' => false,
        ];

        $embed['fields'][] = [
            'name' => 'Comment by',
            'value' => $data['user']['name'] ?? 'Unknown User',
            'inline' => true,
        ];

        $embed['fields'][] = [
            'name' => 'Content',
            'value' => $this->truncateText($data['body'] ?? '', 1024),
            'inline' => false,
        ];

        $embed['url'] = $data['url'] ?? null;
    }

    /**
     * @param  array<mixed>  $embed
     * @param  array<mixed>  $data
     */
    private function addProjectFields(array &$embed, array $data): void
    {
        $embed['fields'][] = [
            'name' => 'Status',
            'value' => $data['state'] ?? 'Unknown',
            'inline' => true,
        ];

        $embed['fields'][] = [
            'name' => 'Lead',
            'value' => $data['lead']['name'] ?? 'Unassigned',
            'inline' => true,
        ];

        if (isset($data['description'])) {
            $embed['fields'][] = [
                'name' => 'Description',
                'value' => $this->truncateText($data['description'], 1024),
                'inline' => false,
            ];
        }

        $embed['url'] = $data['url'] ?? null;
    }

    /**
     * @param  array<mixed>  $embed
     * @param  array<mixed>  $data
     */
    private function addProjectUpdateFields(array &$embed, array $data): void
    {
        $embed['fields'][] = [
            'name' => 'Updated by',
            'value' => $data['user']['name'] ?? 'Unknown User',
            'inline' => true,
        ];

        $embed['fields'][] = [
            'name' => 'Update',
            'value' => $this->truncateText($data['body'] ?? '', 1024),
            'inline' => false,
        ];

        $embed['url'] = $data['url'] ?? null;
    }

    private function getColorForAction(string $action): int
    {
        return match ($action) {
            'create' => 0x4CAF50,  // Green
            'update' => 0x2196F3,  // Blue
            'remove' => 0xF44336,  // Red
            default => 0x9E9E9E,   // Grey
        };
    }

    private function getPriorityEmoji(?int $priority): string
    {
        return match ($priority) {
            0 => '🟢',  // No priority
            1 => '🔵',  // Low
            2 => '🟡',  // Medium
            3 => '🟠',  // High
            4 => '🔴',  // Urgent
            default => '❓',
        };
    }

    private function truncateText(string $text, int $length = 1024): string
    {
        return (strlen($text) > $length) ? substr($text, 0, $length - 3).'...' : $text;
    }

    private function getDiscordTag(string $linearUserId): string
    {
        return $this->mentions->linearMap()[$linearUserId] ?? 'Unknown User';
    }

    /**
     * @param  array<mixed>  $payload
     * @return array{username: string, avatar_url: null}
     */
    private function getSender(array $payload): array
    {
        return [
            'username' => $payload['actor']['name'] ?? 'Linear Webhook',
            'avatar_url' => null,
        ];
    }

    /**
     * Send the transformed payload to Discord, honoring the skip filter.
     * Returns true if a send was attempted, false if the event was skipped.
     *
     * @param  array<mixed>  $linearPayload
     * @param  array<mixed>  $discordPayload
     */
    private function sendToDiscord(WebhookRoute $route, array $linearPayload, array $discordPayload): bool
    {
        if (! $this->shouldProcess($linearPayload)) {
            Log::channel('webhooks')->info('Skipping event per skip filter', [
                'type' => $linearPayload['type'] ?? null,
                'action' => $linearPayload['action'] ?? null,
            ]);

            return false;
        }

        $url = $route->discord_webhook_url;

        Log::channel('webhooks')->info('Sending to Discord', [
            'url' => $url,
            'payload' => json_encode($discordPayload, JSON_PRETTY_PRINT),
        ]);

        try {
            $response = Http::post($url, $discordPayload);

            if ($response->successful()) {
                Log::channel('webhooks')->info('Successfully sent to Discord', ['status' => $response->status()]);
            } else {
                Log::channel('webhooks')->error('Failed to send to Discord', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('webhooks')->error('Exception when sending to Discord', [
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
