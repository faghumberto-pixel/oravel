<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $proposta->titulo() }} — {{ $proposta->tenant?->name }}</title>
    <style>
        body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; background: #f3f4f6; color: #111827; margin: 0; }
        .box { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; margin-bottom: 16px; }
        .head { padding: 20px 24px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; gap: 12px; align-items: center; }
        .body { padding: 20px 24px; }
        h1 { font-size: 18px; margin: 0; } .muted { color: #6b7280; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; } th { text-align: left; font-size: 11px; text-transform: uppercase; color: #9ca3af; padding-bottom: 6px; border-bottom: 1px solid #f3f4f6; }
        td { padding: 9px 0; border-bottom: 1px solid #f9fafb; } .r { text-align: right; }
        .total { font-size: 20px; font-weight: 800; text-align: right; margin-top: 12px; }
        .badge { font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 999px; background: #e5e7eb; }
        .aceita { background: #d1fae5; color: #047857; } .recusada { background: #fee2e2; color: #b91c1c; } .enviada { background: #dbeafe; color: #1d4ed8; } .expirada { background: #fef3c7; color: #b45309; }
        .msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; } .ok { background: #d1fae5; color: #065f46; } .erro { background: #fee2e2; color: #991b1b; }
        input, textarea { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font: inherit; margin: 6px 0 10px; }
        button { padding: 10px 18px; border: 0; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; } .b-ok { background: #059669; color: #fff; } .b-no { background: #fff; color: #b91c1c; border: 1px solid #fca5a5; }
        .cols { display: grid; gap: 16px; grid-template-columns: 1fr; } @media (min-width: 640px) { .cols { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
<div class="box">
    <p class="muted" style="margin-bottom:12px">{{ $proposta->tenant?->name }}</p>

    @if (session('ok'))<div class="msg ok">{{ session('ok') }}</div>@endif
    @if (session('erro'))<div class="msg erro">{{ session('erro') }}</div>@endif
    @if ($errors->any())<div class="msg erro">{{ $errors->first() }}</div>@endif

    <div class="card">
        <div class="head">
            <div>
                <p class="muted" style="margin:0">Proposta para</p>
                <h1>{{ $proposta->destinatarioNome() }}</h1>
                <p class="muted" style="margin:4px 0 0">{{ $proposta->titulo() }}@if($proposta->validade_ate) · válida até {{ $proposta->validade_ate->format('d/m/Y') }}@endif</p>
            </div>
            <span class="badge {{ $proposta->status }}">{{ \App\Models\CrmProposta::statusLabels()[$proposta->status] ?? $proposta->status }}</span>
        </div>
        <div class="body">
            <table>
                <thead><tr><th>Item</th><th class="r">Qtd.</th><th class="r">Unitário</th><th class="r">Subtotal</th></tr></thead>
                <tbody>
                @foreach ($proposta->itens as $item)
                    <tr><td>{{ $item->descricao }}</td><td class="r">{{ number_format($item->quantidade, 2, ',', '.') }}</td><td class="r">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td><td class="r">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td></tr>
                @endforeach
                </tbody>
            </table>
            @if ((float) $proposta->desconto > 0)<p class="muted r" style="margin:10px 0 0">Desconto: − R$ {{ number_format($proposta->desconto, 2, ',', '.') }}</p>@endif
            <p class="total">Total: R$ {{ number_format($proposta->total, 2, ',', '.') }}</p>

            @if ($proposta->condicoes)<h3 style="font-size:14px;margin:20px 0 4px">Condições</h3><div style="font-size:14px;white-space:pre-line">{{ $proposta->condicoes }}</div>@endif
            @if ($proposta->observacoes)<h3 style="font-size:14px;margin:16px 0 4px">Observações</h3><div style="font-size:14px;white-space:pre-line">{{ $proposta->observacoes }}</div>@endif
            @if ($proposta->vendedor)<p class="muted" style="margin-top:20px">Responsável: {{ $proposta->vendedor->name }}</p>@endif
        </div>
    </div>

    @if ($proposta->status === 'enviada')
        <div class="cols">
            <form method="POST" action="{{ route('crm.proposta.aceitar', $proposta->token) }}" class="card"><div class="body">
                @csrf
                <h3 style="margin:0 0 6px;font-size:15px">Aceitar a proposta</h3>
                <label class="muted">Seu nome completo</label>
                <input type="text" name="nome" required minlength="3" maxlength="120" value="{{ old('nome') }}">
                <button class="b-ok" type="submit">Aceitar proposta</button>
            </div></form>
            <form method="POST" action="{{ route('crm.proposta.recusar', $proposta->token) }}" class="card"><div class="body">
                @csrf
                <h3 style="margin:0 0 6px;font-size:15px">Recusar</h3>
                <label class="muted">Motivo</label>
                <textarea name="motivo" rows="2" required minlength="3" maxlength="1000">{{ old('motivo') }}</textarea>
                <button class="b-no" type="submit">Recusar proposta</button>
            </div></form>
        </div>
    @elseif ($proposta->status === 'aceita')
        <div class="msg ok">Proposta aceita por {{ $proposta->nome_aceite }} em {{ $proposta->respondida_em?->format('d/m/Y H:i') }}.</div>
    @elseif ($proposta->status === 'recusada')
        <div class="msg erro">Proposta recusada em {{ $proposta->respondida_em?->format('d/m/Y H:i') }}.</div>
    @elseif ($proposta->status === 'expirada')
        <div class="msg erro">Esta proposta expirou. Peça uma nova ao vendedor.</div>
    @endif
</div>
</body>
</html>
