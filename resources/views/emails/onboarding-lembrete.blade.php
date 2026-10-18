<div style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; max-width: 540px; margin: 0 auto; line-height: 1.6;">
    <p>{{ $saudacao }}</p>
    @foreach ($paragrafos as $p)<p>{{ $p }}</p>@endforeach
    <p><a href="{{ $link }}" style="background:#ea580c;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:bold">{{ $botao }}</a></p>
    @if ($linkAjuda)<p style="font-size:13px">Precisa de ajuda? <a href="{{ $linkAjuda }}">Fale com a gente pelo WhatsApp</a>.</p>@endif
    <p style="font-size:12px;color:#6b7280">Oravel</p>
</div>
