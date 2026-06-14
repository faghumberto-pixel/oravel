<div>
    <div class="flex items-center gap-4">
        <div class="relative flex h-4 w-4">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-4 w-4 bg-red-600 shadow-lg shadow-red-500/50"></span>
        </div>
        <span class="text-sm font-bold text-red-500 uppercase tracking-widest tabular-nums">
            {{ $getRecord()->alerta_descricao ?? 'CRÍTICO' }}
        </span>
    </div>
</div>