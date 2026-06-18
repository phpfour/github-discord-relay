<?php

namespace App\Services\Relay;

use App\Models\Member;
use App\Models\MemberIdentity;

/**
 * Builds source -> Discord mention maps from the stored member identities.
 */
class MentionMapper
{
    /**
     * Map of GitHub username => Discord mention tag ("<@id>").
     *
     * @return array<string, string>
     */
    public function githubMap(): array
    {
        return $this->mapFor('github');
    }

    /**
     * Map of Linear user UUID => Discord mention tag ("<@id>").
     *
     * @return array<string, string>
     */
    public function linearMap(): array
    {
        return $this->mapFor('linear');
    }

    /**
     * @return array<string, string>
     */
    private function mapFor(string $source): array
    {
        return MemberIdentity::query()
            ->where('source', $source)
            ->with('member')
            ->get()
            ->reduce(function (array $carry, MemberIdentity $identity): array {
                $member = $identity->member;

                if ($member instanceof Member) {
                    $carry[$identity->external_id] = $member->discordTag();
                }

                return $carry;
            }, []);
    }
}
