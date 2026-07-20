<?php

use App\Actions\DinnerPlans\PlanDinner;
use App\Data\Recommendations\IngredientMatch;
use App\Data\Recommendations\RecommendationResult;
use App\Models\Recipe;
use App\Models\User;
use App\Queries\GetPantryAwareRecommendations;
use App\Rules\PositiveDecimalQuantity;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Recommendations')] class extends Component {
    use WithPagination;

    #[Url]
    public string $servings = '';
    public string $appliedServings = '';
    private GetPantryAwareRecommendations $recommendationsQuery;

    public function boot(GetPantryAwareRecommendations $recommendationsQuery): void { $this->recommendationsQuery = $recommendationsQuery; }
    public function mount(): void { Gate::authorize('viewAny', Recipe::class); $this->appliedServings = $this->servings; }

    public function applyServings(): void
    {
        $this->validate(['servings' => ['nullable', new PositiveDecimalQuantity]]);
        $this->appliedServings = $this->servings;
        $this->resetPage();
        unset($this->recommendations);
    }

    public function planDinner(int $recipeId, PlanDinner $planDinner): void
    {
        $recipe = Recipe::query()->whereBelongsTo($this->user())->active()->findOrFail($recipeId);
        $servings = filled($this->appliedServings) ? $this->appliedServings : (string) $recipe->default_servings;
        $planDinner->handle($this->user(), $recipe, $servings);
        \Flux\Flux::toast(variant: 'success', text: 'Dinner added to your plan.');
    }

    /** @return LengthAwarePaginator<int, RecommendationResult> */
    #[Computed]
    public function recommendations(): LengthAwarePaginator
    {
        return $this->recommendationsQuery->get($this->user(), filled($this->appliedServings) ? $this->appliedServings : null, page: $this->getPage());
    }

    public function scoreLabel(RecommendationResult $result): string { return $this->decimalLabel($result->score); }
    public function coverageLabel(RecommendationResult $result): string { return $this->decimalLabel(bcmul($result->quantityCoverage, '100', 2)).'%'; }
    public function amountLabel(?string $amount): string { return $amount === null ? '—' : $this->decimalLabel($amount); }
    private function decimalLabel(string $amount): string { $amount = rtrim(rtrim($amount, '0'), '.'); return $amount === '' ? '0' : $amount; }
    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><flux:heading size="xl">Recommendations</flux:heading><flux:text class="mt-1">Every active recipe ranked against the pantry you have now.</flux:text></div>
        <form wire:submit="applyServings" class="flex items-end gap-2"><flux:input wire:model="servings" label="Servings override" placeholder="Recipe defaults" inputmode="decimal" /><flux:button type="submit">Apply</flux:button></form>
    </div>
    <flux:error name="servings" />
    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($this->recommendations as $result)
            <flux:card wire:key="recommendation-{{ $result->recipe->id }}" class="space-y-4">
                <div class="flex items-start justify-between gap-4"><div><flux:heading size="lg">{{ $result->recipe->name }}</flux:heading><flux:text>{{ $result->servings }} servings</flux:text></div><div class="text-right"><div class="text-2xl font-semibold">{{ $this->scoreLabel($result) }}</div><div class="text-xs text-zinc-500">recommendation score</div></div></div>
                <div class="flex items-center justify-between"><flux:text>Quantity coverage</flux:text><flux:badge>{{ $this->coverageLabel($result) }}</flux:badge></div>
                <div class="flex flex-wrap gap-2"><flux:badge color="lime">{{ $result->fullCount }} full</flux:badge><flux:badge color="amber">{{ $result->partialCount }} partial</flux:badge><flux:badge>{{ $result->missingCount }} missing</flux:badge><flux:badge color="red">{{ $result->incompatibleCount }} incompatible</flux:badge></div>
                <details class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700"><summary class="cursor-pointer font-medium">Why this ranking?</summary><div class="mt-3 space-y-2">
                    @if ($result->exactCount === 0) <flux:callout>This recipe has no exact requirements and cannot be quantity-matched.</flux:callout> @endif
                    @foreach ($result->matches as $match)
                        <div wire:key="match-{{ $result->recipe->id }}-{{ $loop->index }}" class="flex items-start justify-between gap-3 text-sm"><div><span class="font-medium">{{ $match->ingredientName }}</span>@if (! $match->exact)<div class="text-zinc-500">{{ $match->description }} · {{ str($match->nonExactStatus)->headline() }} · {{ $match->unitLabel }}</div>@endif</div><div class="text-right"><flux:badge>{{ str($match->status)->headline() }}</flux:badge>@if ($match->exact)<div class="mt-1 text-zinc-500">required {{ $this->amountLabel($match->requiredAmount) }} {{ $match->unitLabel }} · available {{ $this->amountLabel($match->availableAmount) }} · missing {{ $this->amountLabel($match->missingAmount) }}</div>@endif</div></div>
                    @endforeach
                </div></details>
                <div class="flex gap-2"><flux:button :href="route('recipes.show', $result->recipe)" wire:navigate>View recipe</flux:button><flux:button wire:click="planDinner({{ $result->recipe->id }})" variant="primary">Plan dinner</flux:button></div>
            </flux:card>
        @empty
            <flux:callout>No active recipes yet. The recipe catalogue remains available for unranked browsing.</flux:callout>
        @endforelse
    </div>
    {{ $this->recommendations->links() }}
</section>
