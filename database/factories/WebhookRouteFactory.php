<?php

namespace Database\Factories;

use App\Models\WebhookRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookRoute>
 */
class WebhookRouteFactory extends Factory
{
    protected $model = WebhookRoute::class;

    public function definition(): array
    {
        return [
            'source' => 'github',
            'scope' => 'global',
            'match_value' => null,
            'discord_webhook_url' => 'https://discord.com/api/webhooks/'.fake()->numerify('####################').'/'.fake()->lexify('????????'),
            'label' => fake()->words(2, true),
            'is_active' => true,
        ];
    }

    public function github(): static
    {
        return $this->state(fn () => ['source' => 'github']);
    }

    public function linear(): static
    {
        return $this->state(fn () => ['source' => 'linear']);
    }

    public function scope(string $scope, ?string $matchValue = null): static
    {
        return $this->state(fn () => ['scope' => $scope, 'match_value' => $matchValue]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
