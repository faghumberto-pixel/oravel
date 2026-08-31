<div class="header">
    <table>
        <tr>
            <td class="logo-area">
                <div class="logo-text">O<span class="accent">r</span>avel</div>
                <div class="logo-subtext">Asset Intelligence &amp; Maintenance Systems</div>
            </td>
            <td class="title-area">
                <div class="title">Proposta Comercial</div>
                <div class="quote-tag">#{{ $proposta->id }}</div>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Cliente</div>
    <table class="data-grid">
        <tr>
            <td>
                <span class="label">Nome</span>
                <span class="value">{{ $proposta->client?->name }}</span>
            </td>
            <td>
                <span class="label">Vendedor</span>
                <span class="value">{{ $proposta->sellerUser?->name ?? '—' }}</span>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">Itens</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Descrição</th>
                <th class="numeric">Qtd.</th>
                <th class="numeric">Valor Unit.</th>
                <th class="numeric">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($proposta->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="numeric">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td class="numeric">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td class="numeric">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="total-row">Total: <span class="amount">R$ {{ number_format($proposta->total_value, 2, ',', '.') }}</span></div>
</div>

@if($proposta->terms)
    <div class="section">
        <div class="section-title">Termos</div>
        <p>{{ $proposta->terms }}</p>
    </div>
@endif
