<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTenantPlanFeatures
{
    public function handle(Request $request, Closure $next): Response
    {
        // Libera requisições de gravação assíncronas para não travar o fluxo AJAX/Livewire do formulário
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $tenant = Filament::getTenant();
        if (!$tenant) {
            return $next($request);
        }

        $route = $request->route();
        $component = $route?->getAction('livewire') ?? ($route?->defaults['component'] ?? null);

        if ($component && class_exists($component)) {
            
            // Validação e isolamento para Páginas Estáticas Puras
            if (is_subclass_of($component, \Filament\Pages\Page::class) && !method_exists($component, 'getResource')) {
                if (method_exists($component, 'canAccess') && !$component::canAccess()) {
                    abort(403, 'MÓDULO NÃO INCLUSO NO SEU PLANO DE ASSINATURA.');
                }
                return $next($request);
            }

            // ENGENHARIA REVERSA DE RESOURCES: Bloqueio implacável via injeção direta de URL
            if (method_exists($component, 'getResource')) {
                $resourceClass = $component::getResource();
                
                if ($resourceClass) {
                    $modelClass = $resourceClass::getModel();
                    
                    if (class_exists($modelClass)) {
                        $tableName = (new $modelClass)->getTable();
                        $feature = 'tabela_' . $tableName;
                        $user = auth()->user();

                        // Bypass exclusivo do Administrador de Plataforma Central da Oravel
                        if ($user && method_exists($user, 'isOravelSuperAdmin') && $user->isOravelSuperAdmin()) {
                            return $next($request);
                        }

                        // Aplicação rígida do plano contratado
                        if (!$tenant->hasFeature($feature)) {
                            Log::warning("🚫 [SaaS Guard] Bloqueio de URL acionado para recurso não contratado.", [
                                'tenant' => $tenant->slug,
                                'tabela' => $tableName
                            ]);
                            abort(403, 'MÓDULO NÃO INCLUSO NO SEU PLANO DE ASSINATURA.');
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
