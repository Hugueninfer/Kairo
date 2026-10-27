<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table): void {
            // Link de leitura para o cliente. Nulo quando o board não está compartilhado.
            $table->string('share_token', 64)->nullable()->unique()->after('title');
            $table->timestamp('shared_at')->nullable()->after('share_token');
        });

        Schema::create('board_versions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label', 80)->nullable();
            // Versão automática (feita pelo sistema enquanto se edita) ou salva por alguém.
            $table->boolean('automatic')->default(false);
            // Cópia completa dos elementos, no mesmo formato que a API devolve.
            $table->json('elements');
            $table->unsignedInteger('element_count');
            // Evita guardar duas versões automáticas iguais seguidas.
            $table->string('content_hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->index(['board_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_versions');

        Schema::table('boards', function (Blueprint $table): void {
            $table->dropUnique(['share_token']);
            $table->dropColumn(['share_token', 'shared_at']);
        });
    }
};
