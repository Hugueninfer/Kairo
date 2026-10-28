<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * O que aparece no histórico de alterações. O valor é o log_name gravado em activity_log.
 */
enum AuditSubject: string implements HasLabel
{
    use HasOptions;

    case Client = 'client';
    case Project = 'project';
    case Task = 'task';
    case Quote = 'quote';
    case Contract = 'contract';
    case Invoice = 'invoice';
    case Payment = 'payment';
    case Board = 'board';
    case Role = 'role';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Clientes',
            self::Project => 'Projetos',
            self::Task => 'Tarefas',
            self::Quote => 'Orçamentos',
            self::Contract => 'Contratos',
            self::Invoice => 'Faturas',
            self::Payment => 'Pagamentos',
            self::Board => 'Boards',
            self::Role => 'Papéis',
            self::Member => 'Equipe',
        };
    }

    /**
     * Nome do item com artigo, para montar a frase ("Ana alterou a fatura FAT-2026-0001").
     */
    public function noun(): string
    {
        return match ($this) {
            self::Client => 'o cliente',
            self::Project => 'o projeto',
            self::Task => 'a tarefa',
            self::Quote => 'o orçamento',
            self::Contract => 'o contrato',
            self::Invoice => 'a fatura',
            self::Payment => 'o pagamento',
            self::Board => 'o board',
            self::Role => 'o papel',
            self::Member => 'o membro',
        };
    }

    /**
     * Nome em português dos campos que aparecem nas alterações.
     */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => 'Nome',
            'title' => 'Título',
            'email' => 'E-mail',
            'phone' => 'Telefone',
            'document' => 'Documento',
            'number' => 'Número',
            'status' => 'Situação',
            'billing_type' => 'Cobrança',
            'hourly_rate' => 'Valor/hora',
            'budget' => 'Orçamento',
            'starts_on' => 'Início',
            'due_on', 'due_date' => 'Vencimento',
            'priority' => 'Prioridade',
            'estimate_minutes' => 'Estimativa (min)',
            'valid_until' => 'Validade',
            'total' => 'Total',
            'amount_paid' => 'Valor pago',
            'billing_frequency' => 'Recorrência',
            'recurring_amount' => 'Valor recorrente',
            'next_billing_on' => 'Próxima cobrança',
            'amount' => 'Valor',
            'method' => 'Forma de pagamento',
            'paid_at' => 'Pago em',
            'shared_at' => 'Compartilhado em',
            'role' => 'Papel',
            'permissions' => 'Permissões',
            default => $field,
        };
    }
}
