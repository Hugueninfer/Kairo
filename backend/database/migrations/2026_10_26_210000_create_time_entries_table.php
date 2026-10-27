<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->timestamp('started_at');
            // Vazio enquanto o timer está rodando.
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->boolean('billable')->default(true);
            // Valor/hora congelado no lançamento, para mudanças futuras no projeto não alterarem o passado.
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'started_at']);
            $table->index(['company_id', 'project_id', 'started_at']);
        });

        // No máximo um timer rodando por pessoa (em todas as empresas). Índice parcial: Postgres e SQLite aceitam.
        DB::statement('create unique index time_entries_one_running_timer on time_entries (user_id) where ended_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
