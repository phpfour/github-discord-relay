<?php

namespace App\Http\Requests;

use App\Models\MemberIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'discord_user_id' => ['required', 'string', 'regex:/^\d{5,32}$/'],
            'identities' => ['array'],
            'identities.*.source' => ['required', Rule::in(['github', 'linear'])],
            'identities.*.external_id' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discord_user_id.regex' => 'The Discord user ID must be a numeric snowflake (5-32 digits).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $identities = $this->input('identities', []);
            $ignoreMemberId = $this->route('member')?->id;

            $seen = [];
            foreach ($identities as $i => $identity) {
                $source = $identity['source'] ?? null;
                $externalId = $identity['external_id'] ?? null;

                if ($source === null || $externalId === null) {
                    continue;
                }

                $key = $source.'|'.$externalId;

                // Duplicate within the submitted set.
                if (isset($seen[$key])) {
                    $validator->errors()->add("identities.$i.external_id", 'Duplicate identity in this form.');

                    continue;
                }
                $seen[$key] = true;

                // Duplicate against other members.
                $exists = MemberIdentity::where('source', $source)
                    ->where('external_id', $externalId)
                    ->when($ignoreMemberId, fn ($q) => $q->where('member_id', '!=', $ignoreMemberId))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add("identities.$i.external_id", 'This identity is already assigned to another member.');
                }
            }
        });
    }
}
