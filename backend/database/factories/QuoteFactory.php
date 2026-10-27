<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_id' => fn (array $attributes) => Client::factory()->create(['company_id' => $attributes['company_id']])->id,
            'number' => 'ORC-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'title' => ucfirst(fake()->words(3, true)),
            'status' => QuoteStatus::Draft,
            'valid_until' => now()->addDays(15)->toDateString(),
            'subtotal' => '1000.00',
            'total' => '1000.00',
        ];
    }

    public function status(QuoteStatus $status): static
    {
        return $this->state(['status' => $status, 'sent_at' => $status === QuoteStatus::Draft ? null : now()]);
    }
}
