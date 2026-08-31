@php
    $data = \App\Support\BreadcrumbService::getModuleAndPageTitle();
    $module = $data['module'];
    $title = $data['title'];
@endphp

@if ($module && $title)
    <div class="px-6 py-3">
        <p class="text-sm text-white font-medium">
            <span>{{ $module }}</span>
            <span class="mx-2 text-gray-400">→</span>
            <span>{{ $title }}</span>
        </p>
    </div>
@endif
