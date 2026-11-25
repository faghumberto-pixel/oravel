<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#1f2937;line-height:1.5">
    <h2 style="color:#4f46e5;margin:0 0 16px">{{ $assunto }}</h2>
    @foreach ($paragrafos as $p)
        <p style="margin:0 0 12px">{{ $p }}</p>
    @endforeach
    @if ($botao && $link)
        <p style="margin:24px 0"><a href="{{ $link }}" style="background:#4f46e5;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold">{{ $botao }}</a></p>
    @endif
    <p style="color:#6b7280;font-size:12px;margin-top:24px">Oravel Intelligence</p>
</div>
