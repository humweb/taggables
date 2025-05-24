<?php

namespace Humweb\Taggables\Database\Factories;

use Humweb\Taggables\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tag::class;
    
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'user_id' => null, // Global tag by default
            'type' => null,
        ];
    }
    
    /**
     * Indicate that the tag should be user-scoped.
     */
    public function forUser(int $userId): static
    {
        return $this->state(function (array $attributes) use ($userId) {
            return [
                'user_id' => $userId,
            ];
        });
    }
    
    /**
     * Indicate that the tag should have a type.
     */
    public function withType(string $type): static
    {
        return $this->state(function (array $attributes) use ($type) {
            return [
                'type' => $type,
            ];
        });
    }
    

} 
