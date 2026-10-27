<?php

namespace App\Http\Requests\Boards;

use App\DTOs\Boards\BoardElementData;
use App\DTOs\Boards\BoardSyncData;
use App\Enums\BoardElementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lote do autosave do canvas. As propriedades aceitas são só as que o front desenha;
 * o resto é descartado em toData().
 */
class SyncBoardElementsRequest extends FormRequest
{
    private const COORDINATE = 1_000_000;

    private const COLOR = 'regex:/^#[0-9a-fA-F]{6}$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $coordinate = ['required', 'numeric', 'between:-'.self::COORDINATE.','.self::COORDINATE];

        return [
            'upserts' => ['present', 'array', 'max:500'],
            'upserts.*.id' => ['required', 'string', 'ulid', 'distinct'],
            'upserts.*.type' => ['required', Rule::enum(BoardElementType::class)],
            'upserts.*.x' => $coordinate,
            'upserts.*.y' => $coordinate,
            'upserts.*.width' => ['required', 'numeric', 'between:0,100000'],
            'upserts.*.height' => ['required', 'numeric', 'between:0,100000'],
            'upserts.*.rotation' => ['nullable', 'numeric', 'between:-360,360'],
            'upserts.*.z_index' => ['required', 'integer', 'between:-1000000,1000000'],
            'upserts.*.props' => ['present', 'array'],
            'upserts.*.props.text' => ['nullable', 'string', 'max:5000'],
            'upserts.*.props.fill' => ['nullable', 'string', self::COLOR],
            'upserts.*.props.stroke' => ['nullable', 'string', self::COLOR],
            'upserts.*.props.font_size' => ['nullable', 'integer', 'between:8,200'],
            'upserts.*.props.points' => ['nullable', 'array', 'max:20'],
            'upserts.*.props.points.*' => ['numeric', 'between:-'.self::COORDINATE.','.self::COORDINATE],
            'upserts.*.props.from' => ['nullable', 'string', 'ulid'],
            'upserts.*.props.to' => ['nullable', 'string', 'ulid'],
            'deletes' => ['present', 'array', 'max:500'],
            'deletes.*' => ['string', 'ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'upserts' => 'elementos',
            'upserts.*.type' => 'tipo',
            'upserts.*.props.text' => 'texto',
            'upserts.*.props.fill' => 'cor',
            'upserts.*.props.stroke' => 'cor da borda',
        ];
    }

    public function toData(): BoardSyncData
    {
        /** @var list<array<string, mixed>> $upserts */
        $upserts = $this->input('upserts', []);
        /** @var list<string> $deletes */
        $deletes = $this->input('deletes', []);

        return new BoardSyncData(
            upserts: array_map(fn (array $element) => new BoardElementData(
                ulid: strtolower((string) $element['id']),
                type: BoardElementType::from((string) $element['type']),
                x: (float) $element['x'],
                y: (float) $element['y'],
                width: (float) $element['width'],
                height: (float) $element['height'],
                rotation: (float) ($element['rotation'] ?? 0),
                z_index: (int) $element['z_index'],
                props: self::props((array) $element['props']),
            ), $upserts),
            deletes: array_map(fn (string $ulid) => strtolower($ulid), $deletes),
        );
    }

    /**
     * @param  array<mixed>  $props
     * @return array<string, mixed>
     */
    private static function props(array $props): array
    {
        $allowed = array_intersect_key($props, array_flip(['text', 'fill', 'stroke', 'font_size', 'points', 'from', 'to']));

        if (isset($allowed['points']) && is_array($allowed['points'])) {
            $allowed['points'] = array_map(fn (mixed $point) => (float) $point, array_values($allowed['points']));
        }

        foreach (['from', 'to'] as $key) {
            if (isset($allowed[$key]) && is_string($allowed[$key])) {
                $allowed[$key] = strtolower($allowed[$key]);
            }
        }

        return array_filter($allowed, fn (mixed $value) => $value !== null);
    }
}
