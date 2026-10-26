<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Catálogo de permissões do Kairo. O valor é o nome gravado na tabela permissions
 * e usado nas rotas (middleware can:...). Os papéis de cada empresa combinam estas permissões.
 */
enum Permission: string implements HasLabel
{
    use HasOptions;

    case CompanyUpdate = 'company.update';

    case MembersView = 'members.view';
    case MembersInvite = 'members.invite';
    case MembersRemove = 'members.remove';
    case RolesManage = 'roles.manage';

    case ClientsView = 'clients.view';
    case ClientsManage = 'clients.manage';

    case ProjectsView = 'projects.view';
    case ProjectsManage = 'projects.manage';
    case TasksManage = 'tasks.manage';

    case TimesheetViewAll = 'timesheet.view_all';
    case TimesheetApprove = 'timesheet.approve';

    case QuotesView = 'quotes.view';
    case QuotesManage = 'quotes.manage';

    case InvoicesView = 'invoices.view';
    case InvoicesManage = 'invoices.manage';

    case BoardsView = 'boards.view';
    case BoardsManage = 'boards.manage';

    case ReportsView = 'reports.view';

    public function label(): string
    {
        return match ($this) {
            self::CompanyUpdate => 'Editar dados da empresa',
            self::MembersView => 'Ver membros',
            self::MembersInvite => 'Convidar membros',
            self::MembersRemove => 'Remover membros',
            self::RolesManage => 'Gerenciar papéis e permissões',
            self::ClientsView => 'Ver clientes',
            self::ClientsManage => 'Criar e editar clientes',
            self::ProjectsView => 'Ver projetos',
            self::ProjectsManage => 'Criar e editar projetos',
            self::TasksManage => 'Criar e editar tarefas',
            self::TimesheetViewAll => 'Ver horas de toda a equipe',
            self::TimesheetApprove => 'Aprovar horas',
            self::QuotesView => 'Ver orçamentos e contratos',
            self::QuotesManage => 'Criar e enviar orçamentos e contratos',
            self::InvoicesView => 'Ver faturas',
            self::InvoicesManage => 'Emitir e cancelar faturas',
            self::BoardsView => 'Ver boards',
            self::BoardsManage => 'Criar e editar boards',
            self::ReportsView => 'Ver relatórios',
        };
    }

    /**
     * Grupo em que a permissão aparece na matriz da tela de papéis.
     */
    public function group(): string
    {
        return match ($this) {
            self::CompanyUpdate => 'Empresa',
            self::MembersView, self::MembersInvite, self::MembersRemove, self::RolesManage => 'Equipe',
            self::ClientsView, self::ClientsManage => 'Clientes',
            self::ProjectsView, self::ProjectsManage, self::TasksManage => 'Projetos e tarefas',
            self::TimesheetViewAll, self::TimesheetApprove => 'Planilha de horas',
            self::QuotesView, self::QuotesManage => 'Orçamentos e contratos',
            self::InvoicesView, self::InvoicesManage => 'Financeiro',
            self::BoardsView, self::BoardsManage => 'Boards',
            self::ReportsView => 'Relatórios',
        };
    }
}
