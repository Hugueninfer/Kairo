<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->timestamps();

            $table->index(['company_id', 'updated_at']);
        });

        Schema::create('board_elements', function (Blueprint $table): void {
            $table->id();
            // O id do elemento nasce no navegador (ULID), para o front criar e salvar sem esperar a API.
            $table->ulid('ulid');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->double('x');
            $table->double('y');
            $table->double('width')->default(0);
            $table->double('height')->default(0);
            $table->double('rotation')->default(0);
            $table->integer('z_index')->default(0);
            // Texto, cores, pontos da seta e elementos ligados por ela.
            $table->json('props');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['board_id', 'ulid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_elements');
        Schema::dropIfExists('boards');
    }
};
