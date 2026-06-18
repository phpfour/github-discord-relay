<?php

namespace App\Http\Requests;

use App\Models\WebhookRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WebhookRouteRequest extends FormRequest
{
    /**
     * Scopes valid for each source.
     *
     * @var array<string, list<string>>
     */
    public const SCOPES = [
        'github' => ['repo', 'org', 'global'],
        'linear' => ['project', 'team', 'global'],
    ];

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
            'source' => ['required', Rule::in(['github', 'linear'])],
            'scope' => ['required', 'string'],
            'match_value' => ['nullable', 'string', 'max:255'],
            'discord_webhook_url' => ['required', 'url', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $source = $this->input('source');
            $scope = $this->input('scope');

            // Scope must be valid for the source.
            if ($source !== null && $scope !== null && ! in_array($scope, self::SCOPES[$source] ?? [], true)) {
                $validator->errors()->add('scope', "The scope [$scope] is not valid for source [$source].");

                return;
            }

            // Non-global scopes require a match value; global must not have one.
            if ($scope === 'global') {
                $routeModel = $this->route('route');
                $ignoreId = $routeModel instanceof WebhookRoute ? $routeModel->id : null;
                $exists = WebhookRoute::where('source', $source)
                    ->where('scope', 'global')
                    ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('scope', "A global route already exists for source [$source].");
                }
            } elseif (in_array($this->input('match_value'), [null, ''], true)) {
                $validator->errors()->add('match_value', 'A match value is required for non-global scopes.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        $data = $this->safe()->only(['source', 'scope', 'match_value', 'discord_webhook_url', 'label']);
        $data['match_value'] = $data['scope'] === 'global' ? null : $data['match_value'];
        $data['is_active'] = $this->boolean('is_active');

        return $data;
    }
}
