<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Papéis criados automaticamente em toda empresa nova.
 *
 * O papel de dono é reservado ao dono da empresa e não pode ser editado. Os demais
 * podem ter as permissões ajustadas, mas não podem ser renomeados nem apagados.
 */
enum SystemRole: string implements HasLabel
{
    use HasOptions;

    case Owner = 'owner';
    case Admin = 'admin';
    case Finance = 'finance';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dono',
            self::Admin => 'Administrador',
            self::Finance => 'Financeiro',
            self::Member => 'Membro',
        };
    }

    /**
     * Permissões com que o papel nasce.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner, self::Admin => Permission::cases(),
            self::Finance => [
                Permission::MembersView,
                Permission::ClientsView,
                Permission::ClientsManage,
                Permission::ProjectsView,
                Permission::TimesheetViewAll,
                Permission::QuotesView,
                Permission::QuotesManage,
                Permission::InvoicesView,
                Permission::InvoicesManage,
                Permission::ReportsView,
            ],
            self::Member => [
                Permission::MembersView,
                Permission::ClientsView,
                Permission::ProjectsView,
                Permission::TasksManage,
                Permission::BoardsView,
                Permission::BoardsManage,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function defaultPermissionNames(): array
    {
        $names = array_map(fn (Permission $permission) => $permission->value, $this->defaultPermissions());
        sort($names);

        return $names;
    }
}
