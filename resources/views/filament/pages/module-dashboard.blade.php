<x-filament-panels::page>
    @include('dashboards.styles')

    <div class="odb">
        @if ($lead)
            <p class="odb-lead">{{ $lead }}</p>
        @endif

        @if (count($blocks) === 0)
            <p class="odb-lead">Nenhum bloco disponível para o seu perfil neste módulo.</p>
        @else
            <div class="odb-grid">
                @foreach ($blocks as $bloco)
                    <section class="odb-w" style="--span: {{ $bloco['size'] }}" data-block="{{ $bloco['key'] }}">
                        <div class="odb-wh">
                            <h3>{{ $bloco['title'] }}<small>{{ $bloco['subtitle'] }}</small></h3>
                        </div>
                        @include('dashboards.types.'.$bloco['payload']['type'], ['p' => $bloco['payload']])
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
