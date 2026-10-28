<?php

namespace App\Models;

use App\Enums\AuditSubject;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\LogsCompanyActivity;
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
 * @property string|null $share_token
 * @property Carbon|null $shared_at
 * @property Carbon $updated_at
 */
#[Fillable(['project_id', 'created_by', 'title', 'share_token', 'shared_at'])]
class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use BelongsToCompany, HasFactory, HasUlids, LogsCompanyActivity;

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['shared_at' => 'datetime'];
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

    /**
     * @return HasMany<BoardVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(BoardVersion::class);
    }

    public static function auditSubject(): AuditSubject
    {
        return AuditSubject::Board;
    }

    /**
     * @return list<string>
     */
    protected function auditedAttributes(): array
    {
        return ['title', 'shared_at'];
    }

    public function auditLabel(): string
    {
        return $this->title;
    }
}
