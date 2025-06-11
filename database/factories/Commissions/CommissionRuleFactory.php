<?php

namespace Database\Factories\Commissions;

use App\Models\Commissions\CommissionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    public function definition(): array
    {
        $targetType = $this->faker->randomElement(['product', 'category']);
        
        return [
            'target_type' => $targetType,
            'target_id' => $targetType === 'product' ? $this->faker->numberBetween(1, 100) : $this->faker->randomElement(['wound_care', 'skin_substitutes', 'compression']),
            'organization_id' => null,
            'msc_rep_rate' => $this->faker->randomFloat(2, 10, 25),
            'msc_sub_rep_rate' => $this->faker->randomFloat(2, 5, 15),
            'effective_from' => now()->subMonths(3),
            'effective_to' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function productRule(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => 'product',
            'target_id' => $this->faker->numberBetween(1, 100),
        ]);
    }

    public function categoryRule(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => 'category',
            'target_id' => $this->faker->randomElement(['wound_care', 'skin_substitutes', 'compression']),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forOrganization(int $organizationId): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organizationId,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_to' => now()->subDay(),
        ]);
    }
}