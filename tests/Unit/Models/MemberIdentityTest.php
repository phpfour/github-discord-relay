<?php

namespace Tests\Unit\Models;

use App\Models\Member;
use App\Models\MemberIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_have_many_identities_across_sources(): void
    {
        $member = Member::factory()->create();
        $member->identities()->createMany([
            ['source' => 'github', 'external_id' => 'octocat'],
            ['source' => 'github', 'external_id' => 'octodog'],
            ['source' => 'linear', 'external_id' => 'd943eac5-279a-4506-8901-1c1b5dbc3830'],
        ]);

        $this->assertCount(3, $member->fresh()->identities);
        $this->assertCount(2, $member->githubIdentities()->get());
        $this->assertCount(1, $member->linearIdentities()->get());
    }

    public function test_duplicate_source_external_id_is_rejected(): void
    {
        Member::factory()->create()->identities()->create(['source' => 'github', 'external_id' => 'dup']);

        $this->expectException(QueryException::class);

        Member::factory()->create()->identities()->create(['source' => 'github', 'external_id' => 'dup']);
    }

    public function test_deleting_a_member_cascades_its_identities(): void
    {
        $member = Member::factory()->create();
        $member->identities()->create(['source' => 'github', 'external_id' => 'octocat']);

        $member->delete();

        $this->assertSame(0, MemberIdentity::count());
    }
}
