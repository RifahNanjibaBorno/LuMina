<?php

namespace Database\Factories;

use App\Models\AcademicItem;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicItem>
 */
class AcademicItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'type' => 'project',
            'technology' => 'Laravel, Python',
            'visibility' => 'course',
        ];
    }
}
