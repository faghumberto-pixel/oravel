@php
    $r = $getRecord();
    $p = $r->lessons_available ? $r->lessons_done_all / $r->lessons_available * 100 : 0;
@endphp
<div class="px-3 py-2">
    <x-academy.bar :percent="$p" :label="$r->lessons_done_all.' de '.$r->lessons_available.' aulas'" />
</div>
