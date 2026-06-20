<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberIdentity>
 */
class MemberIdentityFactory extends Factory
{
    protected $model = MemberIdentity::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'source' => fake()->randomElement(['github', 'linear']),
            'external_id' => fake()->unique()->userName(),
        ];
    }

    public function github(): static
    {
        return $this->state(fn () => ['source' => 'github']);
    }

    public function linear(): static
    {
        return $this->state(fn () => ['source' => 'linear', 'external_id' => fake()->unique()->uuid()]);
    }
}
