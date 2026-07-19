<div class="flex items-center gap-2 mt-2 mb-1 px-1">
    @if ($icon)
        <x-filament::icon :icon="$icon" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
    @endif
    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {{ $heading }}
    </h2>
</div>
