<x-layouts::app :title="__('Dashboard')">
    <main class="w-full space-y-8" aria-labelledby="dashboard-heading">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <flux:heading id="dashboard-heading" size="xl">Choose dinner with what you have</flux:heading>
                <flux:text class="mt-2 max-w-2xl">Keep your pantry current, compare recommendation explanations, then plan dinner. Reservations and grocery shortfalls update automatically.</flux:text>
            </div>
            <flux:button :href="route('recommendations.index')" wire:navigate variant="primary" icon="sparkles">Find a dinner</flux:button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['route' => 'ingredients.index', 'icon' => 'squares-2x2', 'title' => 'Ingredients', 'description' => 'Manage ingredients, packages, staples, and measurement conventions.'],
                ['route' => 'recipes.index', 'icon' => 'book-open', 'title' => 'Recipes', 'description' => 'Build your catalogue or plan again from archived recipes.'],
                ['route' => 'pantry.index', 'icon' => 'archive-box', 'title' => 'Pantry', 'description' => 'Track stock and see what is reserved for planned dinners.'],
                ['route' => 'recommendations.index', 'icon' => 'sparkles', 'title' => 'Recommendations', 'description' => 'Review ranked choices with clear coverage explanations.'],
                ['route' => 'dinner-plans.index', 'icon' => 'calendar-days', 'title' => 'Dinner plan', 'description' => 'Order dinners, adjust dates and servings, and record cooking.'],
                ['route' => 'groceries.index', 'icon' => 'shopping-cart', 'title' => 'Groceries', 'description' => 'Shop exact shortfalls and keep manual grocery items together.'],
            ] as $area)
                <a href="{{ route($area['route']) }}" wire:navigate class="group rounded-xl border border-zinc-200 p-5 transition hover:border-zinc-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-900 dark:border-zinc-700 dark:hover:border-zinc-500 dark:focus-visible:outline-white">
                    <flux:icon :name="$area['icon']" class="size-6 text-zinc-500 group-hover:text-zinc-900 dark:group-hover:text-white" />
                    <flux:heading size="lg" class="mt-4">{{ $area['title'] }}</flux:heading>
                    <flux:text class="mt-2">{{ $area['description'] }}</flux:text>
                </a>
            @endforeach
        </div>
    </main>
</x-layouts::app>
