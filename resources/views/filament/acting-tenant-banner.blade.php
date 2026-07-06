@php
    $user = auth()->user();
@endphp

@if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() && ! \App\Support\Tenancy::current())
    <div class="fi-acting-tenant-banner w-full border-b border-warning-300 bg-warning-50 px-4 py-2 text-center text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
        <strong>Nenhum tenant selecionado.</strong>
        Como super admin, você não vê cards de métricas nem consegue criar registros (Ativos, Grupos de Checklist, etc.) até escolher em nome de qual empresa está atuando.
        <a href="{{ route('filament.admin.pages.select-acting-tenant') }}" class="font-semibold underline">
            Escolher tenant agora
        </a>
    </div>
@endif
