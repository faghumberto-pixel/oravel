@php $r = $getRecord(); @endphp
<div class="px-3 py-2">
    @if ($r->quiz_total)
        <x-academy.bar :percent="$r->quiz_correct / $r->quiz_total * 100" :label="$r->quiz_correct.' de '.$r->quiz_total.' certas'" />
    @else
        <span style="font-size:12px;color:#9ca3af">—</span>
    @endif
</div>
