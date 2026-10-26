<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('name', 120);
            $table->string('document', 20)->nullable();
            $table->char('currency', 3)->default('BRL');
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->decimal('default_hourly_rate', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Empresa ativa do usuário (lembrada entre sessões).
            $table->foreignId('current_company_id')->nullable()->after('email_verified_at')
                ->constrained('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_company_id');
        });

        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
