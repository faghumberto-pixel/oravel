{{-- Avisos criados no painel Central (2026-09-18, pedido do usuario): banner
     de largura total logo abaixo do topbar, mesmo padrao visual do banner
     "Nenhum tenant selecionado" (acting-tenant-banner.blade.php) -- antes
     apareciam como texto pequeno centralizado no topbar
     (topbar-announcements-ticker.blade.php, agora aposentado), pedido
     explicito foi ficar "como a de cima" (o banner amarelo). Dispensa por
     item fica em localStorage (Alpine $persist), nao reaparece ate expirar
     ou um aviso novo ser criado. --}}
@php
    $announcements = auth()->check()
        ? \App\Models\Announcement::activeFor(\App\Support\Tenancy::current()?->id)
        : collect();
@endphp

@if ($announcements->isNotEmpty())
    @foreach ($announcements as $announcement)
        @php
            [$borderClass, $bgClass, $textClass] = match ($announcement->level) {
                \App\Models\Announcement::LEVEL_CRITICAL => ['border-danger-300 dark:border-danger-500/30', 'bg-danger-50 dark:bg-danger-500/10', 'text-danger-800 dark:text-danger-300'],
                \App\Models\Announcement::LEVEL_WARNING => ['border-warning-300 dark:border-warning-500/30', 'bg-warning-50 dark:bg-warning-500/10', 'text-warning-800 dark:text-warning-300'],
                // "gray" do painel e' Color::Stone (AdminPanelProvider), nao azul --
                // pedido do usuario 2026-09-18: nivel "info" com a mesma paleta
                // neutra quente do resto do app em vez do azul padrao do Filament.
                default => ['border-gray-300 dark:border-gray-500/30', 'bg-gray-100 dark:bg-gray-500/10', 'text-gray-700 dark:text-gray-300'],
            };
        @endphp
        <div
            x-data="{
                dismissed: $persist(false).as('announcement-dismissed-{{ $announcement->id }}'),
            }"
            x-show="! dismissed"
            class="fi-oravel-announcement-banner w-full border-b {{ $borderClass }} {{ $bgClass }} px-4 py-2 text-center text-sm {{ $textClass }}"
        >
            <strong>{{ $announcement->title }}</strong>
            {{ $announcement->message }}
            <button
                type="button"
                x-on:click="dismissed = true"
                class="ms-2 font-semibold underline"
            >
                Dispensar
            </button>
        </div>
    @endforeach
@endif
