<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'creator_id' => Str::uuid()->toString(),
            'assignee_id' => Str::uuid()->toString(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'due_date' => fake()->dateTimeBetween('now', '+1 year'),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'is_completed' => false,
            'attachment_key' => null,
            'attachment_mime' => null,
        ];
    }
}
