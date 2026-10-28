<?php

namespace App\Http\Resources;

use App\Enums\AuditSubject;
use App\Models\Activity;
use App\Models\User;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Uma linha do histórico, já com a frase em português ("alterou a fatura FAT-2026-0001").
 *
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /** Campos em dinheiro, mostrados na moeda da empresa. */
    private const MONEY_FIELDS = ['hourly_rate', 'budget', 'total', 'amount_paid', 'recurring_amount', 'amount'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subject = AuditSubject::tryFrom((string) $this->log_name);
        $label = (string) $this->properties->get('label', '');
        $causer = $this->causer instanceof User ? $this->causer : null;

        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'subject' => $subject === null ? null : ['value' => $subject->value, 'label' => $subject->label()],
            'causer' => $causer === null ? null : ['id' => $causer->id, 'name' => $causer->name],
            'summary' => $this->summary($subject, (string) $this->event, $label),
            'changes' => $this->changes(),
        ];
    }

    private function summary(?AuditSubject $subject, string $event, string $label): string
    {
        if ($subject === AuditSubject::Member) {
            return match ($event) {
                'joined' => 'entrou na equipe',
                'removed' => "removeu {$label} da equipe",
                default => "mudou o papel de {$label}",
            };
        }

        $verb = match ($event) {
            'created' => 'criou',
            'deleted' => 'apagou',
            'restored' => 'restaurou',
            default => 'alterou',
        };

        return trim($verb.' '.($subject?->noun() ?? 'o item').' '.$label);
    }

    /**
     * @return list<array{field: string, label: string, old: string|null, new: string|null}>
     */
    private function changes(): array
    {
        /** @var array<string, mixed> $new */
        $new = (array) $this->properties->get('attributes', []);
        /** @var array<string, mixed> $old */
        $old = (array) $this->properties->get('old', []);

        $changes = [];
        foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $field) {
            $before = $this->format($field, $old[$field] ?? null);
            $after = $this->format($field, $new[$field] ?? null);

            // Na criação vêm todos os campos: os vazios só poluem a linha.
            if ($before === null && $after === null) {
                continue;
            }

            $changes[] = ['field' => $field, 'label' => AuditSubject::fieldLabel($field), 'old' => $before, 'new' => $after];
        }

        return $changes;
    }

    private function format(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn (mixed $item) => is_scalar($item) ? (string) $item : '', $value));
        }

        $text = is_scalar($value) ? (string) $value : '';

        if (in_array($field, self::MONEY_FIELDS, true) && is_numeric($text)) {
            return Money::format($text, app(TenantContext::class)->company()->currency);
        }

        // Datas gravadas pelo Eloquent: só o dia, ou dia e hora no fuso da empresa para os campos "_at".
        if (preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}.*)?$/', $text) === 1) {
            $date = Carbon::parse($text);

            if (! str_ends_with($field, '_at')) {
                return $date->format('d/m/Y');
            }

            // O pagamento é informado só com o dia; a hora gravada não diz nada para quem lê.
            return $date->setTimezone(app(TenantContext::class)->company()->timezone)->format($field === 'paid_at' ? 'd/m/Y' : 'd/m/Y H:i');
        }

        return $text;
    }
}
