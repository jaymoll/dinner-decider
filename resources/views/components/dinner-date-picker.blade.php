@props(['model'])

<div x-data="dinnerDatePicker(@entangle($model).live)" class="relative" @click.outside="open = false">
    <button type="button" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm dark:border-zinc-700 dark:bg-zinc-900" @click="open = ! open" x-text="displayValue"></button>
    <div x-cloak x-show="open" class="absolute z-30 mt-2 w-72 rounded-xl border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-3 flex items-center justify-between">
            <button type="button" class="rounded p-1 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="previousMonth" aria-label="Previous month">&larr;</button>
            <span class="text-sm font-semibold" x-text="monthLabel"></span>
            <button type="button" class="rounded p-1 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="nextMonth" aria-label="Next month">&rarr;</button>
        </div>
        <div class="grid grid-cols-7 text-center text-xs text-zinc-500">
            <template x-for="day in weekDays" :key="day"><span class="py-1" x-text="day"></span></template>
        </div>
        <div class="grid grid-cols-7 text-center text-sm">
            <template x-for="day in days" :key="day.iso">
                <button type="button" class="rounded py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800" :class="{ 'opacity-35': ! day.current, 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900': value === day.iso }" @click="choose(day.iso)" x-text="day.day"></button>
            </template>
        </div>
        <button type="button" class="mt-2 text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-white" @click="value = ''; open = false">No date</button>
    </div>
</div>
