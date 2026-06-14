<div class="ticket-box">
    @if($qrCodeSvg)
        <div>{!! $qrCodeSvg !!}</div>
    @else
        <div style="color: red;">QR Code Indisponível</div>
    @endif
    <div>Patrimônio: {{ $asset->patrimonio }}</div>
</div>
