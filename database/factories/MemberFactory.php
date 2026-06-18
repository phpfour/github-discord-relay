<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'discord_user_id' => (string) fake()->numberBetween(100000000000000000, 999999999999999999),
        ];
    }
}
