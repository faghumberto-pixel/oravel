<div @class([
    'p-3 rounded-t-xl shadow-sm mb-2 border-b-4 border-black/10',
    'bg-red-600' => $color === 'danger',
    'bg-amber-500' => $color === 'warning',
    'bg-blue-600' => $color === 'info',
    'bg-emerald-500' => $color === 'success',
    'bg-gray-500' => $color === 'gray',
])>
    <h3 class="text-white font-bold text-center uppercase tracking-widest text-xs">
        {{ $label }}
    </h3>
</div>
