<div>
    <section class="ac-hero sm">
        <div class="ac-crumb"><a href="{{ url('/academia') }}">Academia</a> / Ranking</div>
        <h1>🏆 Ranking da empresa</h1>
        <p>Pontos por leitura, respostas certas e tempo de estudo. Só aparecem as pessoas da sua empresa.</p>
    </section>

    <div class="ac-card">
        <table class="ac-table" data-testid="ranking">
            <thead><tr><th style="width:60px">#</th><th>Nome</th><th style="text-align:right">Pontos</th></tr></thead>
            <tbody>
            @forelse ($rows as $i => $row)
                <tr wire:key="r-{{ $row->user_id }}" style="{{ $row->user_id === $me ? 'background:#eef4ff;font-weight:700' : '' }}">
                    <td><span class="ac-rank {{ $i < 3 ? 'g'.($i + 1) : '' }}">{{ $i + 1 }}</span></td>
                    <td>{{ $row->name }}{{ $row->user_id === $me ? ' (você)' : '' }}</td>
                    <td style="text-align:right">{{ number_format($row->points, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="color:var(--muted);padding:24px;text-align:center">Ninguém pontuou ainda. Abra uma aula e comece!</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
