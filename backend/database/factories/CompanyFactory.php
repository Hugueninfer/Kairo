<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Services\Companies\RoleService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'currency' => 'BRL',
            'timezone' => 'America/Sao_Paulo',
        ];
    }

    /**
     * O dono também entra como membro e a empresa ganha os papéis padrão, como no cadastro real.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Company $company): void {
            $company->members()->syncWithoutDetaching([$company->owner_id]);
            app(RoleService::class)->setupCompany($company, $company->owner);
        });
    }
}
