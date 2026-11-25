<x-filament-breezy::grid-section md=2 title="Meu plano" description="O plano do Oravel Intelligence da sua empresa.">
    <x-filament::card data-plano-no-perfil>
        <div class="space-y-4 text-sm">
            @if (session('ok'))<p class="rounded-lg px-3 py-2" style="background:rgba(16,185,129,.14);border:1px solid rgba(16,185,129,.5)">{{ session('ok') }}</p>@endif
            @if ($errors->any())<p class="rounded-lg px-3 py-2" style="background:rgba(239,68,68,.14);border:1px solid rgba(239,68,68,.5)">{{ $errors->first() }}</p>@endif

            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-lg font-semibold">{{ $atual['nome'] }}</span>
                    <x-filament::badge :color="$atual['cor']">{{ $atual['situacao'] }}</x-filament::badge>
                </div>
                @foreach ($atual['detalhes'] as $linha)<p>{{ $linha }}</p>@endforeach
                <p>Inclui: {{ implode(', ', $atual['modulos']) }}.</p>
                <p>Perguntas ao assistente hoje: <strong>{{ $usadasIa }} de {{ $limiteIa }}</strong></p>
            </div>

            <div class="space-y-2" data-degraus>
                <p class="font-semibold">Planos</p>
                @foreach ($degraus as $d)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg px-3 py-2" style="border:1px solid rgba(128,128,128,.35)" data-plano="{{ $d['chave'] }}">
                        <div>
                            <p class="font-semibold">{{ $d['nome'] }} <span class="font-normal opacity-70">R$ {{ number_format($d['preco'], 2, ',', '.') }}/mês</span></p>
                            <p class="text-xs opacity-80">
                                @if ($d['chave'] !== 'basico')Tudo do plano anterior + @endif{{ implode(' e ', $d['modulos_rotulos']) }}
                                · {{ $d['ia_por_dia'] }} perguntas/dia · {{ $d['relatorios'] }}
                            </p>
                        </div>
                        @if ($d['atual'])
                            <x-filament::badge color="success">Seu plano</x-filament::badge>
                        @elseif ($d['acao'] === 'em_breve')
                            <x-filament::badge color="gray">Em breve</x-filament::badge>
                        @elseif ($d['acao'] === 'contratar')
                            <x-filament::button tag="a" size="sm" :href="route('intelligence.trial.contratar', ['plano' => $d['chave']])">Assinar</x-filament::button>
                        @else
                            <form method="POST" action="{{ route('intelligence.trial.trocar') }}" onsubmit="return confirm('{{ $d['acao'] === 'subir' ? 'Subir para '.$d['nome'].'? Vale na hora.' : 'Descer para '.$d['nome'].'? Vale a partir da próxima cobrança.' }}')">
                                @csrf
                                <input type="hidden" name="plano" value="{{ $d['chave'] }}">
                                <x-filament::button type="submit" size="sm" :color="$d['acao'] === 'subir' ? 'primary' : 'gray'">{{ $d['acao'] === 'subir' ? 'Subir para este' : 'Descer para este' }}</x-filament::button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($atual['contratado'])
                <x-filament::button tag="a" color="gray" :href="route('intelligence.trial.plano')">Ver detalhes e contrato</x-filament::button>
            @endif
        </div>
    </x-filament::card>
</x-filament-breezy::grid-section>
