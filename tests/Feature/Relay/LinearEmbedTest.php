<?php

namespace Tests\Feature\Relay;

use App\Models\Member;
use App\Services\Relay\LinearRelay;
use App\Services\Relay\MentionMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinearEmbedTest extends TestCase
{
    use RefreshDatabase;

    private function relay(): LinearRelay
    {
        return new LinearRelay(new MentionMapper);
    }

    private function mapLinear(string $uuid, string $discordId): void
    {
        Member::factory()->create(['discord_user_id' => $discordId])
            ->identities()->create(['source' => 'linear', 'external_id' => $uuid]);
    }

    public function test_issue_create_embed_shape_and_mention(): void
    {
        $this->mapLinear('actor-uuid', '538057585698537506');

        $payload = [
            'type' => 'Issue',
            'action' => 'create',
            'createdAt' => '2026-06-18T00:00:00.000Z',
            'actor' => ['id' => 'actor-uuid', 'name' => 'Alice'],
            'data' => [
                'id' => 'issue-1',
                'title' => 'Fix the bug',
                'state' => ['name' => 'In Progress'],
                'assignee' => ['name' => 'Bob'],
                'priority' => 2,
                'priorityLabel' => 'Medium',
                'project' => ['name' => 'Apollo'],
                'url' => 'https://linear.app/x',
            ],
        ];

        $out = $this->relay()->transformToDiscordFormat($payload);
        $embed = $out['embeds'][0];

        $this->assertSame('Created Issue: Fix the bug', $embed['title']);
        $this->assertSame(0x4CAF50, $embed['color']);
        $this->assertSame('Sent via Linear Webhook', $embed['footer']['text']);
        $this->assertSame('https://linear.app/x', $embed['url']);

        // Project field is always first.
        $this->assertSame('Project', $embed['fields'][0]['name']);
        $this->assertSame('Apollo', $embed['fields'][0]['value']);

        $names = array_column($embed['fields'], 'name');
        $this->assertContains('Status', $names);
        $this->assertContains('Assignee', $names);
        $this->assertContains('Priority', $names);

        // Content includes the mapped actor mention.
        $this->assertStringContainsString('<@538057585698537506>', $out['content']);
        $this->assertStringContainsString('titled "Fix the bug"', $out['content']);
    }

    public function test_comment_project_and_projectupdate_shapes(): void
    {
        $comment = $this->relay()->transformToDiscordFormat([
            'type' => 'Comment', 'action' => 'create',
            'data' => ['id' => 'c1', 'issue' => ['title' => 'Login broken'], 'user' => ['name' => 'Carol'], 'body' => 'me too'],
        ]);
        $this->assertSame('New Comment on Issue: Login broken', $comment['embeds'][0]['title']);

        $project = $this->relay()->transformToDiscordFormat([
            'type' => 'Project', 'action' => 'update',
            'data' => ['id' => 'p1', 'name' => 'Apollo', 'state' => 'started', 'lead' => ['name' => 'Dan']],
        ]);
        $this->assertSame('Updated Project: Apollo', $project['embeds'][0]['title']);
        $this->assertSame(0x2196F3, $project['embeds'][0]['color']);

        $update = $this->relay()->transformToDiscordFormat([
            'type' => 'ProjectUpdate', 'action' => 'create',
            'data' => ['id' => 'u1', 'project' => ['name' => 'Apollo'], 'user' => ['name' => 'Eve'], 'body' => 'going well'],
        ]);
        $this->assertSame('Project Update: Apollo', $update['embeds'][0]['title']);
    }

    public function test_unknown_type_yields_unhandled_description(): void
    {
        $out = $this->relay()->transformToDiscordFormat([
            'type' => 'Cycle', 'action' => 'create', 'data' => ['id' => 'z1'],
        ]);

        $this->assertSame('Unhandled event type: Cycle', $out['embeds'][0]['description']);
    }

    public function test_remove_action_is_red_and_omits_title_clause(): void
    {
        $out = $this->relay()->transformToDiscordFormat([
            'type' => 'Issue', 'action' => 'remove',
            'data' => ['id' => 'i9', 'title' => 'Gone', 'project' => ['name' => 'Apollo']],
        ]);

        $this->assertSame(0xF44336, $out['embeds'][0]['color']);
        $this->assertStringNotContainsString('titled', $out['content']);
    }

    public function test_description_is_truncated_at_1024(): void
    {
        $long = str_repeat('a', 2000);
        $out = $this->relay()->transformToDiscordFormat([
            'type' => 'Issue', 'action' => 'create',
            'data' => ['id' => 'i1', 'title' => 'T', 'project' => ['name' => 'P'], 'description' => $long],
        ]);

        $descField = collect($out['embeds'][0]['fields'])->firstWhere('name', 'Description');
        $this->assertSame(1024, strlen($descField['value']));
        $this->assertStringEndsWith('...', $descField['value']);
    }

    public function test_unmapped_actor_renders_unknown_user(): void
    {
        $out = $this->relay()->transformToDiscordFormat([
            'type' => 'Project', 'action' => 'create',
            'actor' => ['id' => 'ghost'],
            'data' => ['id' => 'p1', 'name' => 'Apollo'],
        ]);

        $this->assertStringContainsString('Unknown User', $out['content']);
    }
}
