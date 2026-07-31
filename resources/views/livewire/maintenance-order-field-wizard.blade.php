{{--
    Classes/espacamentos copiados de livewire/maintenance-checklist-mobile.blade.php
    de proposito -- aquele padrao ja esta validado em campo (max-w-md, rodape
    sticky com acao full-width, alvos de 2.75rem/3.25rem = 44/52px, dark zinc,
    esmeralda no concluido). Nao inventar espacamento novo aqui.
--}}
<div class="mx-auto flex min-h-screen max-w-md flex-col">
    {{-- Cabecalho fixo: modulo + status + usuario. Sem menu. --}}
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        <h1 class="text-xs font-bold tracking-widest text-zinc-400">MODO CAMPO</h1>
        <div class="flex items-center gap-2">
            @php
                $statusColor = match ($maintenanceOrder->status) {
                    'Em Andamento' => 'bg-emerald-500/15 text-emerald-400',
                    'Pausada' => 'bg-amber-500/15 text-amber-400',
                    'Concluída' => 'bg-sky-500/15 text-sky-400',
                    'Cancelada' => 'bg-red-500/15 text-red-400',
                    default => 'bg-zinc-800 text-zinc-400',
                };
            @endphp
            <span class="rounded-full px-2.5 py-1 text-[11px] font-bold tracking-wide {{ $statusColor }}">
                {{ strtoupper($maintenanceOrder->status ?? '—') }}
            </span>
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-800 text-xs font-bold text-zinc-300">
                {{ strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
            </span>
        </div>
    </header>

    {{-- Identificacao da O.S. --}}
    <section class="px-5 pb-4">
        {{-- Badges de tipo e SLA --}}
        <div class="mb-3 flex flex-wrap gap-2">
            {{-- Tipo de manutenção --}}
            @php
                $typeColors = [
                    'emerald' => 'bg-emerald-900/30 text-emerald-400',
                    'amber' => 'bg-amber-900/30 text-amber-400',
                    'red' => 'bg-red-900/30 text-red-400',
                    'zinc' => 'bg-zinc-800 text-zinc-400',
                ];
                $typeColor = $typeColors[$this->maintenanceTypeColor] ?? $typeColors['zinc'];
            @endphp
            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $typeColor }}">
                {{ $this->maintenanceTypeLabel }}
            </span>

            {{-- SLA (se houver) --}}
            @if ($this->slaRemaining)
                @php
                    $slaColors = [
                        'success' => 'bg-emerald-900/30 text-emerald-400',
                        'warning' => 'bg-amber-900/30 text-amber-400',
                        'danger' => 'bg-red-900/30 text-red-400',
                        'gray' => 'bg-zinc-800 text-zinc-400',
                    ];
                    $slaColor = $slaColors[$this->slaColor] ?? $slaColors['gray'];
                @endphp
                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $slaColor }}">
                    @if ($this->slaRemaining['exceeded'])
                        ⏰ SLA EXPIRADO
                    @else
                        ⏱ {{ $this->slaRemaining['hours'] }}h {{ $this->slaRemaining['minutes'] }}m
                    @endif
                </span>
            @endif
        </div>

        <h2 class="text-xl font-extrabold leading-tight text-white">
            ORDEM DE SERVIÇO Nº. {{ $maintenanceOrder->os_number }}
        </h2>
        <p class="mt-1 text-sm font-medium text-zinc-400">
            @if($maintenanceOrder->asset?->patrimonio)
                PAT. {{ strtoupper($maintenanceOrder->asset->patrimonio) }} —
            @endif
            {{ strtoupper($maintenanceOrder->asset?->name ?? 'SEM ATIVO') }}
        </p>

        {{-- Progresso: "Etapa X de Y" + o nome da etapa, pra o tecnico saber
             onde esta e quanto falta sem precisar contar telas. --}}
        <div class="mt-4 flex items-baseline justify-between">
            <span class="text-[11px] font-bold tracking-widest text-emerald-400">
                ETAPA {{ $step }} DE {{ $this->totalSteps }}
            </span>
            <span class="text-[11px] font-semibold uppercase text-zinc-500">{{ $this->stepLabel }}</span>
        </div>
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-zinc-800">
            <div class="h-2 rounded-full bg-emerald-500 transition-all duration-300"
                 style="width: {{ $this->stepProgress }}%"></div>
        </div>
    </section>

    {{-- Conteudo da etapa --}}
    <main class="flex-1 overflow-y-auto">
        @if($step === 1)
            <div class="space-y-3 px-5 pb-4">
                @include('livewire.field-wizard.step-equipment')
            </div>
        @elseif($step === 2)
            @include('livewire.field-wizard.step-checklist')
        @elseif($step === 3)
            <div class="px-5 pb-4">
                @include('livewire.field-wizard.step-damages')
            </div>
        @elseif($step === 4)
            <div class="px-5 pb-4">
                @include('livewire.field-wizard.step-materials')
            </div>
        @elseif($step === 5)
            <div class="px-5 pb-4">
                @include('livewire.field-wizard.step-signature')
            </div>
        @endif
    </main>

    {{-- Rodape fixo: estado de gravacao + acao primaria full-width --}}
    <footer class="sticky bottom-0 border-t border-zinc-800 bg-zinc-950 px-5 pb-4 pt-3">
        {{-- Estado de gravacao. wire:offline e' nativo do Livewire e cobre o
             caso de o celular perder rede antes de tocar em Continuar. --}}
        <div class="mb-2 min-h-[1.25rem] text-[11px] font-semibold">
            <span wire:offline class="text-amber-400">
                ● Sem conexão — o que você já enviou está salvo.
            </span>

            <span wire:offline.remove>
                @if($saveState === 'error')
                    <button type="button" wire:click="retry" class="text-left text-red-400 underline">
                        Não foi possível salvar. Toque para tentar de novo.
                    </button>
                @elseif($saveState === 'saved')
                    <span class="text-emerald-400">✓ Salvo</span>
                @endif
                <span wire:loading wire:target="next,retry" class="text-zinc-400">Salvando...</span>
            </span>
        </div>

        <div class="flex gap-3">
            <button type="button" wire:click="back"
                    class="min-h-[3.25rem] flex-1 rounded-xl border border-zinc-700 bg-zinc-900 text-sm font-bold tracking-wide text-zinc-300">
                {{ $step === 1 ? 'SAIR' : 'VOLTAR' }}
            </button>
            <button type="button" wire:click="next" wire:loading.attr="disabled" wire:target="next"
                    class="min-h-[3.25rem] flex-[1.4] rounded-xl bg-emerald-500 text-sm font-bold tracking-wide text-zinc-950 disabled:bg-zinc-800 disabled:text-zinc-600">
                {{ $this->primaryLabel }}
            </button>
        </div>
    </footer>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const nextBtn = document.querySelector('button[wire\\:click="next"]');
        if (!nextBtn) return;

        nextBtn.addEventListener('click', function(e) {
            // Se estamos na etapa 5 (assinatura), captura as assinaturas antes de enviar
            const stepElement = document.querySelector('[wire\\:model="step"]');
            const isStep5 = @js($step) === 5;

            if (isStep5) {
                e.preventDefault();

                // Captura as assinaturas
                const techSig = window.getTechnicianSignature?.();
                const clientSig = window.getClientSignature?.();

                if (!techSig || window.isTechnicianSignature?.() === false) {
                    alert('⚠️ Técnico deve assinar para continuar');
                    return;
                }

                if (!clientSig || window.isClientSigned?.() === false) {
                    alert('⚠️ Cliente deve assinar para continuar');
                    return;
                }

                // Envia para o Livewire
                @this.call('saveSignatures', techSig, clientSig, () => {
                    // Após salvar, chama o next normalmente
                    @this.call('next');
                });
            }
        });
    });
</script>
