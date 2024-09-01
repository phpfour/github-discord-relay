<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinearWebhookController extends Controller
{
    private array $linearToDiscordMap;

    private array $skipActions = [
        'issue' => ['update'],
    ];

    public function __construct()
    {
        $this->linearToDiscordMap = config('user_mapping.linear');
    }

    public function handle(Request $request)
    {
        try {
            $payload = $request->all();

            // Generate a unique identifier for this event
            $eventId = $this->generateEventId($payload);

            // Check if we've already processed this event
            if ($this->isEventProcessed($eventId)) {
                Log::channel('webhooks')->info("Duplicate event received and ignored", ['eventId' => $eventId]);
                return response()->json(['message' => 'Event already processed'], 200);
            }

            // Log the incoming webhook
            $this->logWebhook($request);

            // Transform the Linear webhook data to Discord format
            $discordPayload = $this->transformToDiscordFormat($payload);

            // Send the transformed data to Discord
            $this->sendToDiscord($discordPayload);

            // Mark this event as processed
            $this->markEventAsProcessed($eventId);

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            Log::error('Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'An error occurred while processing the webhook'], 500);
        }
    }

    private function generateEventId($payload)
    {
        // Create a unique identifier based on the event details
        $components = [
            $payload['type'] ?? '',
            $payload['action'] ?? '',
            $payload['data']['id'] ?? '',
            $payload['createdAt'] ?? '',
        ];

        return md5(implode('|', $components));
    }

    private function isEventProcessed($eventId)
    {
        return Cache::has("processed_event:{$eventId}");
    }

    private function markEventAsProcessed($eventId)
    {
        // Store the event ID in cache for 24 hours
        Cache::put("processed_event:{$eventId}", true, now()->addHours(24));
    }

    private function logWebhook(Request $request)
    {
        $logData = [
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ];

        Log::channel('webhooks')->info('Incoming Linear webhook', $logData);
    }

    private function shouldProcess($linearPayload): bool
    {
        $action = strtolower($linearPayload['action'] ?? 'unknown');
        $type = strtolower($linearPayload['type'] ?? 'unknown');

        Log::channel('webhooks')->info("Action: $action, Type: $type, HasAction: " . isset($this->skipActions[$type]) . ", InArray: " . in_array($action, $this->skipActions[$type]));

        if (isset($this->skipActions[$type]) && in_array($action, $this->skipActions[$type])) {
            return false;
        }

        return true;
    }

    private function transformToDiscordFormat($linearPayload)
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

    private function generateContent($payload)
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

    private function getActionVerb($action)
    {
        return match ($action) {
            'create' => 'created',
            'update' => 'updated',
            'remove' => 'removed',
            default => 'modified',
        };
    }

    private function generateEmbedTitle($type, $action, $data)
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

    private function addProjectField(&$embed, $data)
    {
        $projectName = $data['project']['name'] ?? 'Unknown Project';

        $embed['fields'][] = [
            'name' => 'Project',
            'value' => $projectName,
            'inline' => true,
        ];
    }

    private function addIssueFields(&$embed, $data)
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
            'value' => $this->getPriorityEmoji($data['priority']) . ' ' . ($data['priorityLabel'] ?? 'N/A'),
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

    private function addCommentFields(&$embed, $data)
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
            'value' => $this->truncateText($data['body'], 1024),
            'inline' => false,
        ];

        $embed['url'] = $data['url'] ?? null;
    }

    private function addProjectFields(&$embed, $data)
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

    private function addProjectUpdateFields(&$embed, $data)
    {
        $embed['fields'][] = [
            'name' => 'Updated by',
            'value' => $data['user']['name'] ?? 'Unknown User',
            'inline' => true,
        ];

        $embed['fields'][] = [
            'name' => 'Update',
            'value' => $this->truncateText($data['body'], 1024),
            'inline' => false,
        ];

        $embed['url'] = $data['url'] ?? null;
    }

    private function getColorForAction($action)
    {
        return match ($action) {
            'create' => 0x4CAF50,  // Green
            'update' => 0x2196F3,  // Blue
            'remove' => 0xF44336,  // Red
            default => 0x9E9E9E,   // Grey
        };
    }

    private function getPriorityEmoji($priority)
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

    private function truncateText($text, $length = 1024)
    {
        return (strlen($text) > $length) ? substr($text, 0, $length - 3) . '...' : $text;
    }

    private function getDiscordTag($linearUserId)
    {
        return $this->linearToDiscordMap[$linearUserId] ?? 'Unknown User';
    }

    private function getSender($payload)
    {
        $username = $payload['actor']['name'] ?? 'Linear Webhook';

        return [
            'username' => $username,
            'avatar_url' => null,
        ];
    }

    private function sendToDiscord($payload): void
    {
        if (! $this->shouldProcess($payload)) {
            return;
        }

        $discordWebhookUrl = config('services.discord.webhook_url_2');

        Log::channel('webhooks')->info('Sending to Discord', [
            'url' => $discordWebhookUrl,
            'payload' => json_encode($payload, JSON_PRETTY_PRINT)
        ]);

        try {
            $response = Http::post($discordWebhookUrl, $payload);

            if ($response->successful()) {
                Log::channel('webhooks')->info('Successfully sent to Discord', ['status' => $response->status()]);
            } else {
                Log::channel('webhooks')->error('Failed to send to Discord', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::channel('webhooks')->error('Exception when sending to Discord', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
