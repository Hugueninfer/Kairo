<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('billing_type', 20)->default('hourly');
            // Valor/hora próprio do projeto; vazio usa o da empresa.
            $table->decimal('hourly_rate', 10, 2)->nullable();
            // Valor fechado (projeto por preço fixo) ou teto de horas cobradas.
            $table->decimal('budget', 12, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
