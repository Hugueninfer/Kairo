<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Projeto criado quando o cliente aceita.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            // Sequencial por empresa e ano: ORC-2026-0001.
            $table->string('number', 20);
            $table->string('title', 160);
            $table->text('introduction')->nullable();
            $table->text('terms')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('valid_until');
            // Totais gravados no momento em que os itens mudam, para o PDF e o aceite baterem sempre.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            // Quem aceitou pelo link público (nome digitado e IP), como registro do aceite.
            $table->string('signer_name', 160)->nullable();
            $table->string('signer_ip', 45)->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 255);
            $table->string('type', 10)->default('fixed');
            // Horas (item por hora) ou unidades (item de valor fixo).
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('quote_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 20);
            $table->string('title', 160);
            $table->decimal('total', 12, 2);
            $table->text('terms')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('signed_at');
            $table->string('signer_name', 160);
            $table->string('signer_ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
