<?php

namespace Tests\Feature\Admin;

use App\Models\Member;
use App\Models\MemberIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_member_routes_require_authentication(): void
    {
        $this->get('/members')->assertRedirect(route('login'));
        $this->post('/members', [])->assertRedirect(route('login'));
    }

    public function test_admin_can_create_member_with_identities(): void
    {
        $this->actingAs($this->admin())->post('/members', [
            'name' => 'Alice',
            'discord_user_id' => '538057585698537506',
            'identities' => [
                ['source' => 'github', 'external_id' => 'alice-gh'],
                ['source' => 'linear', 'external_id' => 'd943eac5-279a-4506-8901-1c1b5dbc3830'],
            ],
        ])->assertRedirect('/members');

        $member = Member::firstWhere('name', 'Alice');
        $this->assertNotNull($member);
        $this->assertCount(2, $member->identities);
    }

    public function test_duplicate_identity_is_rejected(): void
    {
        Member::factory()->create()->identities()->create(['source' => 'github', 'external_id' => 'taken']);

        $this->actingAs($this->admin())->post('/members', [
            'name' => 'Bob',
            'discord_user_id' => '111111111111111111',
            'identities' => [['source' => 'github', 'external_id' => 'taken']],
        ])->assertSessionHasErrors('identities.0.external_id');

        $this->assertNull(Member::firstWhere('name', 'Bob'));
    }

    public function test_invalid_discord_id_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/members', [
            'name' => 'Bad',
            'discord_user_id' => 'not-a-snowflake',
        ])->assertSessionHasErrors('discord_user_id');
    }

    public function test_editing_replaces_identities(): void
    {
        $member = Member::factory()->create();
        $member->identities()->create(['source' => 'github', 'external_id' => 'old']);

        $this->actingAs($this->admin())->put("/members/{$member->id}", [
            'name' => $member->name,
            'discord_user_id' => $member->discord_user_id,
            'identities' => [['source' => 'github', 'external_id' => 'new']],
        ])->assertRedirect('/members');

        $this->assertDatabaseMissing('member_identities', ['external_id' => 'old']);
        $this->assertDatabaseHas('member_identities', ['external_id' => 'new']);
    }

    public function test_deleting_member_removes_identities(): void
    {
        $member = Member::factory()->create();
        $member->identities()->create(['source' => 'github', 'external_id' => 'x']);

        $this->actingAs($this->admin())->delete("/members/{$member->id}")->assertRedirect('/members');

        $this->assertSame(0, Member::count());
        $this->assertSame(0, MemberIdentity::count());
    }
}
