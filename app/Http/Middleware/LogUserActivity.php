<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra visualizacoes de tela (GET, navegacao real de pagina) por
 * usuario/tenant. Ignora chamadas Livewire/AJAX de fundo (polling,
 * reatividade de campo) -- senao vira ruido de volume absurdo -- essas nao
 * sao "o usuario abriu uma tela nova", sao atualizacoes da mesma tela ja
 * aberta. Criacao/edicao/exclusao de registros e logada separadamente via
 * eventos do Eloquent (ver AppServiceProvider), nao aqui.
 */
class LogUserActivity
{
    private const LABELS = [
        'materials' => 'Materiais',
        'suppliers' => 'Fornecedores',
        'material-categories' => 'Categorias de Materiais',
        'parts-requests' => 'Solicitações de Peças',
        'assets' => 'Ativos',
        'asset-categories' => 'Categorias de Ativos',
        'abc-matrices' => 'Matriz ABC',
        'checklist-groups' => 'Grupos de Checklist',
        'checklist-templates' => 'Modelos de Checklist',
        'internal-units' => 'Unidades Internas',
        'maintenance-orders' => 'Ordens de Serviço',
        'maintenance-plans' => 'Planos de Manutenção',
        'clients' => 'Clientes',
        'contracts' => 'Contratos',
        'equipment-damages' => 'Avarias de Equipamento',
        'fleet-vehicles' => 'Veículos de Frota',
        'freight-records' => 'Fretes',
        'freight-carriers' => 'Transportadoras',
        'fleet-maintenance-plans' => 'Planos de Manutenção de Frota',
        'fleet-statuses' => 'Status de Frota',
        'users' => 'Usuários',
        'roles' => 'Perfis de Acesso',
        'solicitacoes-locacao' => 'Solicitações de Locação',
        'companies' => 'Empresas',
        'branches' => 'Filiais',
        'departments' => 'Departamentos',
        'cost-centers' => 'Centros de Custo',
        'bill-categories' => 'Categorias de Conta',
        'account-payables' => 'Contas a Pagar',
        'locations' => 'Localizações',
        'select-acting-tenant' => 'Atuar como Tenant',
        'user-activity-logs' => 'Logs de Atividade',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->log($request);

        return $response;
    }

    private function log(Request $request): void
    {
        if (! $request->isMethod('GET')) {
            return;
        }

        if ($this->isNoise($request)) {
            return;
        }

        $user = Auth::user();
        $tenant = Tenancy::current();

        if (! $user || ! $tenant) {
            return;
        }

        $path = '/'.trim($request->path(), '/');
        $segments = explode('/', trim($request->path(), '/'));
        $resourceSlug = $segments[1] ?? null; // depois de "admin"
        $label = self::LABELS[$resourceSlug] ?? ($resourceSlug ? Str::headline($resourceSlug) : null);

        UserActivityLog::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'method' => $request->method(),
            'path' => $path,
            'route_name' => $request->route()?->getName(),
            'resource_label' => $label,
            'action' => UserActivityLog::ACTION_VIEW,
        ]);
    }

    private function isNoise(Request $request): bool
    {
        if (
            $request->hasHeader('X-Livewire') ||
            $request->hasHeader('X-Livewire-Method') ||
            $request->hasHeader('X-Livewire-Component-Name') ||
            str_starts_with($request->path(), 'livewire/')
        ) {
            return true;
        }

        // Assets, notificacoes, downloads e afins nao sao "telas".
        if (preg_match('#\.(js|css|png|jpg|jpeg|svg|ico|woff2?|map)$#', $request->path())) {
            return true;
        }

        if (! str_starts_with($request->path(), 'admin/')) {
            return true;
        }

        return false;
    }
}
