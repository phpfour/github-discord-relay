<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(): Response
    {
        $members = Member::query()
            ->with('identities')
            ->orderBy('name')
            ->get()
            ->map(fn (Member $member) => $this->present($member));

        return Inertia::render('members/index', ['members' => $members]);
    }

    public function create(): Response
    {
        return Inertia::render('members/create');
    }

    public function store(MemberRequest $request): RedirectResponse
    {
        $member = Member::create($request->safe()->only(['name', 'discord_user_id']));
        $this->syncIdentities($member, $request->validated('identities', []));

        return to_route('members.index');
    }

    public function edit(Member $member): Response
    {
        $member->load('identities');

        return Inertia::render('members/edit', ['member' => $this->present($member)]);
    }

    public function update(MemberRequest $request, Member $member): RedirectResponse
    {
        $member->update($request->safe()->only(['name', 'discord_user_id']));
        $this->syncIdentities($member, $request->validated('identities', []));

        return to_route('members.index');
    }

    public function destroy(Member $member): RedirectResponse
    {
        $member->delete();

        return to_route('members.index');
    }

    /**
     * Replace the member's identities with the submitted set.
     *
     * @param  array<int, array{source: string, external_id: string}>  $identities
     */
    private function syncIdentities(Member $member, array $identities): void
    {
        $member->identities()->delete();

        foreach ($identities as $identity) {
            $member->identities()->create([
                'source' => $identity['source'],
                'external_id' => $identity['external_id'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Member $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'discord_user_id' => $member->discord_user_id,
            'identities' => $member->identities->map(fn ($identity) => [
                'id' => $identity->id,
                'source' => $identity->source,
                'external_id' => $identity->external_id,
            ])->values(),
        ];
    }
}
