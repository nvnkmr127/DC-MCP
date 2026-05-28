<?php

namespace Database\Factories;

use App\Modules\Auth\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'plan' => 'free',
            'is_active' => true,
            'settings' => [
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
            ],
        ];
    }
}
