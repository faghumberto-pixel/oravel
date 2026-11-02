@php
    $compromissosHoje = \App\Models\SalesLeadAppointment::whereDate('scheduled_at', now()->toDateString())->count();
    $compromissosSemana = \App\Models\SalesLeadAppointment::whereBetween('scheduled_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
    $urlProgramacao = \App\Filament\Central\Pages\Programacao::getUrl();
@endphp

<div
    x-data="{
        agora: new Date(),
        dias: ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'],
        meses: ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
        init() {
            setInterval(() => { this.agora = new Date() }, 1000);
        },
        get dataFormatada() {
            return this.dias[this.agora.getDay()] + ', ' + this.agora.getDate() + ' de ' + this.meses[this.agora.getMonth()];
        },
        get horaFormatada() {
            return this.agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },
    }"
    class="hidden md:flex items-center gap-3 text-gray-300 text-xs font-medium"
>
    <div class="flex flex-col items-end leading-tight">
        <span x-text="dataFormatada" class="capitalize"></span>
        <span x-text="horaFormatada" class="font-mono text-sm text-gray-100 tabular-nums"></span>
    </div>

    <div class="flex items-center rounded-full bg-white/5 border border-white/10 overflow-hidden">
        <a href="{{ $urlProgramacao }}?visao=hoje" title="Ver a lista de compromissos de hoje"
           class="flex items-center gap-1.5 hover:bg-white/10 transition-colors pl-3 pr-2 py-1.5">
            <x-heroicon-o-calendar-days class="w-4 h-4 text-primary-400" />
            <span class="text-gray-100 font-bold">{{ $compromissosHoje }}</span>
            <span class="text-gray-400">hoje</span>
        </a>
        <span class="h-4 w-px bg-white/10"></span>
        <a href="{{ $urlProgramacao }}?visao=semana" title="Ver a lista de compromissos da semana"
           class="flex items-center gap-1.5 hover:bg-white/10 transition-colors pl-2 pr-3 py-1.5">
            <span class="text-gray-100 font-bold">{{ $compromissosSemana }}</span>
            <span class="text-gray-400">na semana</span>
        </a>
    </div>
</div>
