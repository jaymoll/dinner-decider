<?php

use App\Actions\Ingredients\UpdateIngredient;
use App\Livewire\Forms\IngredientForm;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit ingredient')] class extends Component {
    public Ingredient $ingredient;
    public IngredientForm $form;

    public function mount(Ingredient $ingredient): void { Gate::authorize('update', $ingredient); $this->ingredient = $ingredient; $this->form->setIngredient($ingredient); }
    public function addAlias(): void { $this->form->addAlias(); }
    public function removeAlias(int $index): void { $this->form->removeAlias($index); }
    public function addPackage(): void { $this->form->addPackage(); }
    public function removePackage(int $index): void { $this->form->removePackage($index); }

    public function save(UpdateIngredient $updateIngredient): void
    {
        $updateIngredient->handle($this->user(), $this->ingredient, $this->form->validated($this->user()));
        session()->flash('status', 'Ingredient updated.');
        $this->redirectRoute('ingredients.index', navigate: true);
    }

    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div><flux:heading size="xl">Edit {{ $ingredient->name }}</flux:heading><flux:text class="mt-1">Changes affect future recipe edits; saved recipe quantities remain explicit.</flux:text></div>
    <x-ingredients.form :form="$form" submit-label="Save changes" />
</section>
