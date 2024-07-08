<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinearWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            // Log the incoming webhook
            $this->logWebhook($request);

            // Validate the incoming webhook
            $payload = $request->all();

            // Log the Linear payload
            Log::channel('webhooks')->info('Linear payload received', ['payload' => $payload]);

            // Transform the Linear webhook data to Discord format
            $discordPayload = $this->transformToDiscordFormat($payload);

            // Log the Discord payload
            Log::channel('webhooks')->info('Discord payload prepared', ['payload' => $discordPayload]);

            // Send the transformed data to Discord
            $response = $this->sendToDiscord($discordPayload);

            // Log the Discord response
            Log::channel('webhooks')->info('Discord response received', ['response' => $response]);

            return response()->json(['message' => 'Webhook processed successfully']);
        } catch (\Exception $e) {
            Log::channel('webhooks')->error('Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'An error occurred while processing the webhook'], 500);
        }
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

    private function transformToDiscordFormat($linearPayload)
    {
        $action = $linearPayload['action'] ?? 'unknown';
        $type = $linearPayload['type'] ?? 'unknown';
        $data = $linearPayload['data'] ?? [];

        $content = "New Linear Event: $action $type";

        $embed = [
            'title' => $content,
            'color' => $this->getColorForAction($action),
            'fields' => [],
            'footer' => [
                'text' => 'Sent via Linear Webhook',
            ],
            'timestamp' => date('c'),
        ];

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

        // Get the sender information
        $sender = $this->getSender($linearPayload);

        return [
            'content' => $content,
            'embeds' => [$embed],
            'avatar_url' => $sender['avatar_url'],
            'username' => $sender['username'],
        ];
    }

    private function getSender($payload)
    {
        // Try to get the user who triggered the event
        if (isset($payload['data']['user']['name'])) {
            $username = $payload['data']['user']['name'];
            $avatarUrl = $payload['data']['user']['avatarUrl'] ?? null;
        } else {
            $username = 'Linear Webhook';
            $avatarUrl = null; // You could set a default avatar URL here if you have one
        }

        return [
            'username' => $username,
            'avatar_url' => $avatarUrl,
        ];
    }

    private function addIssueFields(&$embed, $data)
    {
        $embed['fields'][] = [
            'name' => 'Title',
            'value' => $data['title'] ?? 'N/A',
            'inline' => false,
        ];
        $embed['fields'][] = [
            'name' => 'Status',
            'value' => $data['state']['name'] ?? 'N/A',
            'inline' => true,
        ];
        $embed['fields'][] = [
            'name' => 'Assignee',
            'value' => $data['assignee']['name'] ?? 'Unassigned',
            'inline' => true,
        ];
        $embed['fields'][] = [
            'name' => 'Priority',
            'value' => $this->getPriorityEmoji($data['priority']) . ' ' . ($data['priority'] ?? 'N/A'),
            'inline' => true,
        ];
        if (isset($data['description'])) {
            $embed['fields'][] = [
                'name' => 'Description',
                'value' => $this->truncateText($data['description'], 1024),
                'inline' => false,
            ];
        }
        if (isset($data['url'])) {
            $embed['url'] = $data['url'];
        }
    }

    private function addCommentFields(&$embed, $data)
    {
        $embed['fields'][] = [
            'name' => 'Issue',
            'value' => $data['issue']['title'] ?? 'N/A',
            'inline' => false,
        ];
        $embed['fields'][] = [
            'name' => 'Comment by',
            'value' => $data['user']['name'] ?? 'Unknown',
            'inline' => true,
        ];
        $embed['fields'][] = [
            'name' => 'Content',
            'value' => $this->truncateText($data['body'], 1024),
            'inline' => false,
        ];
        if (isset($data['url'])) {
            $embed['url'] = $data['url'];
        }
    }

    private function addProjectFields(&$embed, $data)
    {
        $embed['fields'][] = [
            'name' => 'Project Name',
            'value' => $data['name'] ?? 'N/A',
            'inline' => false,
        ];
        $embed['fields'][] = [
            'name' => 'Status',
            'value' => $data['state'] ?? 'N/A',
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
        if (isset($data['url'])) {
            $embed['url'] = $data['url'];
        }
    }

    private function addProjectUpdateFields(&$embed, $data)
    {
        $embed['fields'][] = [
            'name' => 'Project',
            'value' => $data['project']['name'] ?? 'N/A',
            'inline' => false,
        ];
        $embed['fields'][] = [
            'name' => 'Updated by',
            'value' => $data['user']['name'] ?? 'Unknown',
            'inline' => true,
        ];
        $embed['fields'][] = [
            'name' => 'Update',
            'value' => $this->truncateText($data['body'], 1024),
            'inline' => false,
        ];
        if (isset($data['url'])) {
            $embed['url'] = $data['url'];
        }
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

    private function sendToDiscord($payload)
    {
        $discordWebhookUrl = str_replace('/github', '', config('services.discord.webhook_url'));

        Log::channel('webhooks')->info('Sending to Discord', ['url' => $discordWebhookUrl]);

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

            return $response;
        } catch (\Exception $e) {
            Log::channel('webhooks')->error('Exception when sending to Discord', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
