@props(['model'])

<div x-data="dinnerDatePicker(@entangle($model).live)" x-id="['date-picker-dialog', 'date-picker-label']" class="relative" @click.outside="close(false)" @keydown.escape.stop.prevent="close(true)">
    <button x-ref="trigger" type="button" class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:focus-visible:outline-white" @click="toggle" :aria-expanded="open.toString()" aria-haspopup="dialog" :aria-controls="$id('date-picker-dialog')" x-text="displayValue"></button>
    <div x-cloak x-show="open" x-transition.opacity :id="$id('date-picker-dialog')" role="dialog" aria-modal="false" :aria-labelledby="$id('date-picker-label')" class="absolute z-30 mt-2 w-[min(18rem,calc(100vw-2rem))] rounded-xl border border-zinc-200 bg-white p-3 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-3 flex items-center justify-between">
            <button type="button" class="rounded p-1 hover:bg-zinc-100 focus-visible:outline-2 dark:hover:bg-zinc-800" @click="previousMonth" aria-label="Previous month">&larr;</button>
            <span :id="$id('date-picker-label')" class="text-sm font-semibold" x-text="monthLabel"></span>
            <button type="button" class="rounded p-1 hover:bg-zinc-100 focus-visible:outline-2 dark:hover:bg-zinc-800" @click="nextMonth" aria-label="Next month">&rarr;</button>
        </div>
        <div role="row" class="grid grid-cols-7 text-center text-xs text-zinc-500">
            <template x-for="day in weekDays" :key="day"><span role="columnheader" class="py-1" x-text="day"></span></template>
        </div>
        <div role="grid" :aria-labelledby="$id('date-picker-label')" class="grid grid-cols-7 text-center text-sm" @keydown="handleGridKeydown">
            <template x-for="day in days" :key="day.iso">
                <button x-ref="dayButtons" role="gridcell" type="button" class="rounded py-1.5 hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-zinc-900 dark:hover:bg-zinc-800 dark:focus-visible:outline-white" :class="{ 'opacity-35': ! day.current, 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900': value === day.iso }" :tabindex="day.iso === activeIso ? 0 : -1" :aria-label="day.label" :aria-selected="(value === day.iso).toString()" :aria-current="day.today ? 'date' : null" @focus="activeIso = day.iso" @click="choose(day.iso)" x-text="day.day"></button>
            </template>
        </div>
        <button type="button" class="mt-2 rounded text-xs text-zinc-500 hover:text-zinc-900 focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:text-white" @click="clear">No date</button>
    </div>
</div>
