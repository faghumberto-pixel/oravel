<x-filament-widgets::widgets
    :widgets="$this->getWidgets()"
    @class([
        'fi-hidden' => empty($widgets = $this->getWidgets()),
    ])
/>

<x-filament::section>
    <p>Bem-vindo ao módulo Almoxarifado. Selecione uma opção no menu para começar.</p>
</x-filament::section>
