<?php

namespace Database\Factories;

use App\Enums\BoardElementType;
use App\Models\Board;
use App\Models\BoardElement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BoardElement>
 */
class BoardElementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ulid' => strtolower((string) Str::ulid()),
            'board_id' => Board::factory(),
            'type' => BoardElementType::Note,
            'x' => fake()->numberBetween(-500, 500),
            'y' => fake()->numberBetween(-500, 500),
            'width' => 200,
            'height' => 160,
            'rotation' => 0,
            'z_index' => fake()->numberBetween(1, 50),
            'props' => ['text' => fake()->sentence(3), 'fill' => '#FEF08A'],
        ];
    }
}
