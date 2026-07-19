<footer class="mt-20 bg-slate-900">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
            <div class="col-span-2 md:col-span-1">
                <div class="text-lg font-bold tracking-tight text-white">O<span class="text-primary-500">r</span>avel</div>
                <p class="mt-2 max-w-[16rem] text-xs leading-relaxed text-gray-400">
                    Gestão de Locadoras e Manutenção. Do pátio ao contrato fechado, numa plataforma só.
                </p>
            </div>

            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-300">Módulos</h3>
                <ul class="mt-3 space-y-2">
                    @if(\App\Filament\Resources\AssetResource::canViewAny())
                        <li><a href="{{ \App\Filament\Resources\AssetResource::getUrl() }}" class="text-xs text-gray-500 hover:text-gray-300">Ativos</a></li>
                    @endif
                    @if(\App\Filament\Resources\ClientResource::canViewAny())
                        <li><a href="{{ \App\Filament\Resources\ClientResource::getUrl() }}" class="text-xs text-gray-500 hover:text-gray-300">Clientes</a></li>
                    @endif
                    @if(\App\Filament\Pages\MaintenanceKanban::canAccess())
                        <li><a href="{{ \App\Filament\Pages\MaintenanceKanban::getUrl() }}" class="text-xs text-gray-500 hover:text-gray-300">Kanban do Pátio</a></li>
                    @endif
                </ul>
            </div>

            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-300">Configurações</h3>
                <ul class="mt-3 space-y-2">
                    @if(\App\Filament\Resources\RoleResource::canViewAny())
                        <li><a href="{{ \App\Filament\Resources\RoleResource::getUrl() }}" class="text-xs text-gray-500 hover:text-gray-300">Perfis de Acesso</a></li>
                    @endif
                    @if(\App\Filament\Resources\DepartmentResource::canViewAny())
                        <li><a href="{{ \App\Filament\Resources\DepartmentResource::getUrl() }}" class="text-xs text-gray-500 hover:text-gray-300">Departamentos</a></li>
                    @endif
                    <li><a href="{{ \Jeffgreco13\FilamentBreezy\Pages\MyProfilePage::getUrl() }}" class="text-xs text-gray-500 hover:text-gray-300">Minha Conta</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-300">Suporte</h3>
                <ul class="mt-3 space-y-2">
                    <li><a href="https://academy.oravel.com.br/" target="_blank" rel="noopener" class="text-xs text-gray-500 hover:text-gray-300">Central de Ajuda</a></li>
                    <li><a href="mailto:contato@oravel.com.br" class="text-xs text-gray-500 hover:text-gray-300">contato@oravel.com.br</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-8 border-t border-gray-800 pt-6 text-[11px] text-gray-600">
            Copyright &copy; {{ now()->year }} Oravel. Todos os direitos reservados.
        </div>
    </div>
</footer>
