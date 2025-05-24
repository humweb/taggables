<?php

namespace Humweb\Taggables\Database\Factories;

use Humweb\Taggables\Tests\TestSupport\Models\TestModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestModelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TestModel::class;
    
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
        ];
    }
} 
