<?php

use App\Actions\Decisions\PlanDecisionChoice;
use App\Data\Decisions\DecisionCandidate;
use App\Models\Recipe;
use App\Models\User;
use App\Queries\GetDecisionCandidates;
use App\Rules\PositiveDecimalQuantity;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Decision Mode')] class extends Component
{
    #[Locked]
    #[Session]
    public string $seed = '';

    #[Session]
    public int $round = 0;

    #[Session]
    public string $servings = '';

    /** @var list<mixed> */
    #[Session]
    public array $excludedRecipeIds = [];

    private GetDecisionCandidates $candidateQuery;

    public function boot(GetDecisionCandidates $candidateQuery): void
    {
        $this->candidateQuery = $candidateQuery;
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', Recipe::class);
        if ($this->seed === '') {
            $this->seed = Str::random(40);
        }

        $this->round = max(0, $this->round);
        $this->excludedRecipeIds = $this->normalizeExclusions($this->excludedRecipeIds);
    }

    public function applyServings(): void
    {
        $this->validate(['servings' => ['nullable', new PositiveDecimalQuantity]]);
        $this->round = 0;
        unset($this->candidates);
    }

    public function reroll(): void
    {
        $this->round = max(0, $this->round) + 1;
        unset($this->candidates);
    }

    public function exclude(int $recipeId): void
    {
        $recipe = Recipe::query()->whereBelongsTo($this->user())->active()->findOrFail($recipeId);
        abort_unless($this->candidates->contains(fn (DecisionCandidate $candidate): bool => $candidate->recommendation->recipe->is($recipe)), 422);

        $this->excludedRecipeIds = $this->normalizeExclusions([...$this->excludedRecipeIds, $recipe->id]);
        unset($this->candidates);
    }

    public function plan(int $recipeId, PlanDecisionChoice $planChoice): void
    {
        $planChoice->handle(
            $this->user(),
            $recipeId,
            $this->seed,
            $this->round,
            filled($this->servings) ? $this->servings : null,
            $this->excludedRecipeIds,
        );

        Flux::toast(variant: 'success', text: 'Decision planned as an ordinary dinner.');
    }

    /** @return Collection<int, DecisionCandidate> */
    #[Computed]
    public function candidates(): Collection
    {
        $this->excludedRecipeIds = $this->normalizeExclusions($this->excludedRecipeIds);

        return $this->candidateQuery->get(
            $this->user(),
            $this->seed,
            $this->round,
            filled($this->servings) ? $this->servings : null,
            $this->excludedRecipeIds,
        );
    }

    /** @param list<mixed> $ids @return list<int> */
    private function normalizeExclusions(array $ids): array
    {
        return collect($ids)
            ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->take((int) config('decisions.exclusion_limit', 24))
            ->values()
            ->all();
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
};
?>

<section class="w-full space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><flux:heading size="xl">Decision Mode</flux:heading><flux:text class="mt-1 max-w-2xl">A small, explainable shortlist based on pantry fit, favourites, cooking history, and deterministic variety.</flux:text></div>
        <flux:button wire:click="reroll" wire:loading.attr="disabled" variant="primary" aria-label="Reroll Decision Mode candidates">Reroll choices</flux:button>
    </div>

    <form wire:submit="applyServings" class="flex max-w-md flex-col gap-2 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1"><flux:input wire:model="servings" label="Servings override" placeholder="Recipe defaults" inputmode="decimal" /><flux:error name="servings" /></div>
        <flux:button type="submit" wire:loading.attr="disabled">Apply</flux:button>
    </form>

    @if ($this->candidates->isNotEmpty() && $this->candidates->count() < (int) config('decisions.result_limit'))
        <flux:callout icon="information-circle">Only {{ $this->candidates->count() }} eligible {{ Str::plural('recipe', $this->candidates->count()) }} remain in this decision session.</flux:callout>
    @endif

    <div class="grid gap-4 lg:grid-cols-3" aria-live="polite">
        @forelse ($this->candidates as $candidate)
            @php($result = $candidate->recommendation)
            <flux:card wire:key="decision-candidate-{{ $result->recipe->id }}" class="flex flex-col gap-4">
                <div><div class="flex flex-wrap items-center gap-2"><flux:heading size="lg">{{ $result->recipe->name }}</flux:heading>@if ($result->isFavourite)<flux:badge color="amber">Favourite</flux:badge>@endif</div><flux:text>{{ $result->servings }} servings · pantry score {{ $result->score }}</flux:text></div>
                <ul class="flex-1 space-y-2" aria-label="Why {{ $result->recipe->name }} was chosen">@foreach ($candidate->explanations as $explanation)<li wire:key="decision-explanation-{{ $result->recipe->id }}-{{ $loop->index }}" class="text-sm text-zinc-600 dark:text-zinc-300">{{ $explanation }}</li>@endforeach</ul>
                <div class="flex flex-wrap gap-2"><flux:button wire:click="plan({{ $result->recipe->id }})" wire:loading.attr="disabled" variant="primary" aria-label="Plan {{ $result->recipe->name }} from Decision Mode">Plan dinner</flux:button><flux:button wire:click="exclude({{ $result->recipe->id }})" wire:loading.attr="disabled" variant="ghost" aria-label="Exclude {{ $result->recipe->name }} from this decision session">Exclude</flux:button><flux:button :href="route('recipes.show', $result->recipe)" wire:navigate variant="ghost">View</flux:button></div>
            </flux:card>
        @empty
            <flux:callout class="lg:col-span-3" icon="sparkles">No eligible recipes remain. Add an active recipe or start a fresh browser session to clear session-only exclusions.</flux:callout>
        @endforelse
    </div>
</section>
