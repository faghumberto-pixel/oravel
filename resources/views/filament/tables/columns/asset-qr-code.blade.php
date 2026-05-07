<div class="p-2">
    {!! QrCode::size(64)->generate(route('asset.scan', $getRecord()->id)) !!}
</div>
