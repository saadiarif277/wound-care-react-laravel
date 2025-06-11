<?php

namespace Database\Factories;

use App\Models\MscSalesRep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MscSalesRepFactory extends Factory
{
    protected $model = MscSalesRep::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rep_code' => strtoupper($this->faker->bothify('REP####')),
            'rep_type' => $this->faker->randomElement(['msc_rep', 'msc_sub_rep']),
            'parent_rep_id' => null,
            'commission_rate' => $this->faker->randomFloat(2, 5, 20),
            'territory' => $this->faker->state(),
            'status' => 'active',
            'organization_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function mscRep(): static
    {
        return $this->state(fn (array $attributes) => [
            'rep_type' => 'msc_rep',
            'commission_rate' => $this->faker->randomFloat(2, 10, 20),
        ]);
    }

    public function mscSubRep(): static
    {
        return $this->state(fn (array $attributes) => [
            'rep_type' => 'msc_sub_rep',
            'commission_rate' => $this->faker->randomFloat(2, 5, 12),
            'parent_rep_id' => MscSalesRep::factory()->mscRep(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function withOrganization(int $organizationId): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organizationId,
        ]);
    }
}