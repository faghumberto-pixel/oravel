<label class="block text-xs font-bold tracking-wider text-zinc-400">{{ $rotuloData }}
    <input type="datetime-local" wire:model="dataHora" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
</label>
<label class="block text-xs font-bold tracking-wider text-zinc-400">{{ $rotuloKm }}
    <input type="number" inputmode="numeric" wire:model="odometro" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
</label>
<label class="block text-xs font-bold tracking-wider text-zinc-400">JUSTIFICATIVA (SÓ SE O KM FOR MENOR)
    <input type="text" wire:model="justificativaOdometro" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
</label>
<label class="block text-xs font-bold tracking-wider text-zinc-400">COMBUSTÍVEL
    <select wire:model="combustivel" class="mt-1 min-h-[3rem] w-full rounded-xl bg-zinc-800 px-3 text-base text-white">
        <option value="">—</option>
        <option value="vazio">Reserva</option>
        <option value="1/4">1/4</option>
        <option value="1/2">1/2</option>
        <option value="3/4">3/4</option>
        <option value="cheio">Cheio</option>
    </select>
</label>
