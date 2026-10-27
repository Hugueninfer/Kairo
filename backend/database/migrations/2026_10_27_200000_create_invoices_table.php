<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Sequencial por empresa e ano: FAT-2026-0001.
            $table->string('number', 20);
            $table->string('status', 20)->default('draft');
            $table->date('issue_date');
            $table->date('due_date');
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            // Cobrança no gateway: id e link de pagamento que vão para o cliente.
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_charge_id', 100)->nullable();
            $table->string('payment_url', 500)->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'due_date']);
            $table->index(['gateway', 'gateway_charge_id']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            // Vazio quando o pagamento foi registrado à mão (dinheiro, transferência).
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // O mesmo pagamento avisado duas vezes pelo gateway não entra em dobro.
            $table->unique(['gateway', 'gateway_payment_id']);
        });

        // Todo aviso recebido do gateway fica guardado antes de ser processado na fila.
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 30);
            $table->string('event_id', 150);
            $table->string('type', 60);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
        });

        // Hora faturada fica presa à fatura, para não ser cobrada duas vezes.
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });

        // No SQLite, alterar a tabela a recria e o índice parcial perde o "where". Recria do jeito certo.
        DB::statement('drop index if exists time_entries_one_running_timer');
        DB::statement('create unique index time_entries_one_running_timer on time_entries (user_id) where ended_at is null');
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
