<?php

use App\Enums\Permission;
use App\Enums\SystemRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela do spatie/laravel-activitylog, com a empresa para o histórico ficar isolado por tenant.
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();

            $table->index('log_name');
            $table->index(['company_id', 'created_at']);
        });

        // Permissão nova: os papéis Dono e Administrador das empresas que já existem passam a tê-la.
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => Permission::AuditView->value,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $permissionId = DB::table('permissions')->where('name', Permission::AuditView->value)->value('id');

        $roles = DB::table('roles')
            ->whereIn('system_key', [SystemRole::Owner->value, SystemRole::Admin->value])
            ->pluck('id');

        DB::table('role_has_permissions')->insertOrIgnore(
            $roles->map(fn (int $roleId) => ['permission_id' => $permissionId, 'role_id' => $roleId])->all(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');

        DB::table('permissions')->where('name', Permission::AuditView->value)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
