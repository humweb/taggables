<?php

namespace Humweb\Taggables\Tests\TestSupport\Factories;

use Humweb\Taggables\Tests\TestSupport\Models\TestModelWithTags;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestModelFactory extends Factory
{
    protected $model = TestModelWithTags::class;

    public function definition()
    {
        return [
            'name' => $this->faker->sentence,
        ];
    }
} 
