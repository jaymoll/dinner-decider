<?php

use App\Actions\Ingredients\CreateIngredient;
use App\Livewire\Forms\IngredientForm;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New ingredient')] class extends Component {
    public IngredientForm $form;

    public function mount(): void { Gate::authorize('create', Ingredient::class); }
    public function addAlias(): void { $this->form->addAlias(); }
    public function removeAlias(int $index): void { $this->form->removeAlias($index); }
    public function addPackage(): void { $this->form->addPackage(); }
    public function removePackage(int $index): void { $this->form->removePackage($index); }

    public function save(CreateIngredient $createIngredient): void
    {
        $createIngredient->handle($this->user(), $this->form->validated($this->user()));
        session()->flash('status', 'Ingredient created.');
        $this->redirectRoute('ingredients.index', navigate: true);
    }

    private function user(): User { $user = Auth::user(); abort_unless($user instanceof User, 401); return $user; }
}; ?>

<section class="w-full space-y-6">
    <div><flux:heading size="xl">New ingredient</flux:heading><flux:text class="mt-1">Create a reliable measurement definition for recipes and future pantry calculations.</flux:text></div>
    <x-ingredients.form :form="$form" submit-label="Create ingredient" />
</section>
