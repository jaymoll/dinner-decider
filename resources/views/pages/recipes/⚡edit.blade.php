<?php

use App\Actions\Recipes\UpdateRecipe;
use App\Enums\NonExactStatus;
use App\Livewire\Forms\RecipeForm;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit recipe')] class extends Component {
    use WithFileUploads;
    public Recipe $recipe;
    public RecipeForm $form;

    public function mount(Recipe $recipe): void { Gate::authorize('update', $recipe); $this->recipe = $recipe; $this->form->setRecipe($recipe); }
    public function addIngredient(): void { $this->form->addIngredient(); }
    public function removeIngredient(int $index): void { $this->form->removeIngredient($index); }
    public function addStep(): void { $this->form->addStep(); }
    public function removeStep(int $index): void { $this->form->removeStep($index); }
    public function moveIngredient(string $key, int $position): void { $this->form->moveIngredient($key, $position); }
    public function moveStep(string $key, int $position): void { $this->form->moveStep($key, $position); }
    public function ingredientChanged(int $index): void { $ingredient = $this->ingredientOptions->firstWhere('id', (int) $this->form->ingredients[$index]['ingredient_id']); if ($ingredient) { $this->form->ingredients[$index]['unit'] = $ingredient->preferred_unit->value; $this->form->ingredients[$index]['ingredient_package_id'] = null; } }
    public function quantityTypeChanged(int $index): void { if ($this->form->ingredients[$index]['quantity_type'] === 'non_exact') { $this->form->ingredients[$index]['amount'] = ''; $this->form->ingredients[$index]['unit'] = ''; $this->form->ingredients[$index]['ingredient_package_id'] = null; } else { $this->form->ingredients[$index]['description'] = ''; $this->form->ingredients[$index]['non_exact_status'] = NonExactStatus::Required->value; $this->ingredientChanged($index); } }
    public function save(UpdateRecipe $updateRecipe): void { $recipe = $updateRecipe->handle($this->user(), $this->recipe, $this->form->validated($this->user())); session()->flash('status', 'Recipe updated.'); $this->redirectRoute('recipes.show', $recipe, navigate: true); }
    #[Computed] public function ingredientOptions(): Collection { return Ingredient::query()->whereBelongsTo($this->user())->where(fn ($query) => $query->active()->orWhereHas('recipeIngredients', fn ($query) => $query->where('recipe_id', $this->recipe->id)))->with('packages')->oldest('name')->get(); }
    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6"><div><flux:heading size="xl">Edit {{ $recipe->name }}</flux:heading><flux:text class="mt-1">Serving previews always continue to use these stored source quantities.</flux:text></div><x-recipes.form :form="$form" :ingredient-options="$this->ingredientOptions" :current-image-path="$recipe->image_path" submit-label="Save changes" /></section>
