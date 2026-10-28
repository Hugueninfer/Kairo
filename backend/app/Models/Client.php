<?php

namespace App\Models;

use App\Enums\AuditSubject;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\LogsCompanyActivity;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'email', 'phone', 'document', 'notes'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToCompany, HasFactory, HasUlids, LogsCompanyActivity, SoftDeletes;

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
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public static function auditSubject(): AuditSubject
    {
        return AuditSubject::Client;
    }

    /**
     * @return list<string>
     */
    protected function auditedAttributes(): array
    {
        return ['name', 'email', 'phone', 'document'];
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
