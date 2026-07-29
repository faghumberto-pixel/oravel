{{--
    Bloco de perfil fixo no rodape da sidebar -- pedido do usuario 2026-07-28
    (design system "Convertico"). Reaproveita <x-filament-panels::user-menu>
    de proposito em vez de reescrever o dropdown/logout na mao: aquele
    componente ja resolve o link de perfil (->userMenuItems() do
    CentralPanelProvider) e o logout via POST+CSRF corretamente
    (vendor/filament/filament/resources/views/components/user-menu.blade.php).
    O "avatar" clicavel do proprio componente E' o gatilho do dropdown --
    so' adiciono nome + cargo ao lado.
--}}
<div class="fi-sidebar-footer-oravel flex items-center gap-3 mx-3 mb-3 px-3 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 transition-colors">
    <x-filament-panels::user-menu />
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-white truncate leading-tight">
            {{ filament()->getUserName(auth()->user()) }}
        </p>
        <p class="text-[11px] text-white/50 truncate leading-tight mt-0.5">
            Super Admin
        </p>
    </div>
</div>
