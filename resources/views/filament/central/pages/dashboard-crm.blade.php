{{--
    Fundo branco so' nesta pagina -- pedido do usuario 2026-08-19 ("quero o
    fundo branco"), resto da Central continua no tema escuro "Convertico"
    (ver resources/css/filament/central/theme.css). Envolve o grid de
    widgets num container proprio em vez de mexer no tema global.
--}}
<x-filament-panels::page>
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
        <x-filament-widgets::widgets
            :widgets="$this->getWidgets()"
            :columns="$this->getColumns()"
        />
    </div>
</x-filament-panels::page>
