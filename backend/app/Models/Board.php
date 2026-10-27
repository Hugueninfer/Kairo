<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Quadro colaborativo (estilo Miro), opcionalmente ligado a um projeto.
 *
 * @property Carbon $updated_at
 */
#[Fillable(['project_id', 'created_by', 'title'])]
class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use BelongsToCompany, HasFactory, HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BoardElement, $this>
     */
    public function elements(): HasMany
    {
        return $this->hasMany(BoardElement::class)->orderBy('z_index')->orderBy('id');
    }
}
