<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_id' => fn (array $attributes) => Client::factory()->create(['company_id' => $attributes['company_id']])->id,
            'number' => 'FAT-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Draft,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => '1000.00',
            'total' => '1000.00',
        ];
    }

    /**
     * Enviada, com cobrança no gateway simulado.
     */
    public function open(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Open,
            'sent_at' => now(),
            'gateway' => 'fake',
            'gateway_charge_id' => 'fake_'.fake()->unique()->lexify('??????????'),
            'payment_url' => 'http://localhost/pagar',
        ]);
    }
}
