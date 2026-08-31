@php
    $data = \App\Support\BreadcrumbService::getModuleAndPageTitle();
    $module = $data['module'];
    $title = $data['title'];
@endphp

@if ($module && $title)
    <div class="px-6 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
        <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">
            <span>{{ $module }}</span>
            <span class="mx-2 text-gray-400 dark:text-gray-600">→</span>
            <span>{{ $title }}</span>
        </p>
    </div>
@endif
