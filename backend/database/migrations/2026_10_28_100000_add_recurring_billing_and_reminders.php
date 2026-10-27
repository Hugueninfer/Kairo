<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            // Cobrança recorrente: vazio quando o contrato é cobrado só por parcelas avulsas.
            $table->string('billing_frequency', 20)->nullable()->after('total');
            $table->decimal('recurring_amount', 12, 2)->nullable()->after('billing_frequency');
            $table->date('next_billing_on')->nullable()->after('recurring_amount');
            $table->date('billing_ends_on')->nullable()->after('next_billing_on');
            $table->unsignedSmallInteger('payment_term_days')->default(7)->after('billing_ends_on');
            $table->boolean('auto_send')->default(true)->after('payment_term_days');

            $table->index('next_billing_on');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // Início do período cobrado por um contrato recorrente: impede gerar a mesma mensalidade duas vezes.
            $table->date('billing_period_start')->nullable()->after('contract_id');

            $table->unique(['contract_id', 'billing_period_start']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('reminders_enabled')->default(true)->after('default_hourly_rate');
            $table->json('reminder_stages')->nullable()->after('reminders_enabled');
            $table->text('reminder_message')->nullable()->after('reminder_stages');
        });

        Schema::create('invoice_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->timestamp('sent_at');
            $table->timestamps();

            // Cada etapa da régua sai uma vez só por fatura.
            $table->unique(['invoice_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminders');

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['reminders_enabled', 'reminder_stages', 'reminder_message']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['contract_id', 'billing_period_start']);
            $table->dropColumn('billing_period_start');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropIndex(['next_billing_on']);
            $table->dropColumn(['billing_frequency', 'recurring_amount', 'next_billing_on', 'billing_ends_on', 'payment_term_days', 'auto_send']);
        });
    }
};
