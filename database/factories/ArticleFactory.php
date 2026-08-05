<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'status' => 'draft',
            'approval_level' => 'none',
            'sensitive_words_hit' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn() => ['status' => 'draft']);
    }

    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => 'pending',
            'approval_level' => 'editor',
            'submitted_at' => now(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn() => [
            'status' => 'published',
            'approval_level' => 'none',
            'approved_at' => now(),
        ]);
    }
}
