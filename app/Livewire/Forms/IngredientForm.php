<?php

namespace App\Livewire\Forms;

use App\Enums\MeasurementGroup;
use App\Enums\PackageType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\IngredientAlias;
use App\Models\IngredientPackage;
use App\Models\User;
use App\Rules\PositiveDecimalQuantity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;
use Livewire\Form;

class IngredientForm extends Form
{
    public ?int $ingredientId = null;

    public string $name = '';

    public string $category = '';

    public string $preferred_measurement_group = 'mass';

    public string $preferred_unit = 'g';

    public bool $is_staple = false;

    /** @var array<int, string> */
    public array $aliases = [];

    /** @var array<int, array{id: int|null, key: string, package_type: string, label: string, content_amount: string, content_unit: string}> */
    public array $packages = [];

    public function setIngredient(Ingredient $ingredient): void
    {
        $ingredient->loadMissing(['aliases', 'packages']);
        $this->ingredientId = $ingredient->id;
        $this->name = $ingredient->name;
        $this->category = $ingredient->category ?? '';
        $this->preferred_measurement_group = $ingredient->preferred_measurement_group->value;
        $this->preferred_unit = $ingredient->preferred_unit->value;
        $this->is_staple = $ingredient->is_staple;
        $this->aliases = $ingredient->aliases->map(fn (IngredientAlias $alias): string => $alias->name)->values()->all();
        $this->packages = $ingredient->packages->map(fn (IngredientPackage $package): array => [
            'id' => $package->id,
            'key' => (string) Str::uuid(),
            'package_type' => $package->package_type->value,
            'label' => $package->label,
            'content_amount' => $package->content_amount ?? '',
            'content_unit' => $package->content_unit->value ?? '',
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    public function validated(User $user): array
    {
        $validator = Validator::make($this->all(), [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'preferred_measurement_group' => ['required', Rule::enum(MeasurementGroup::class)->only([MeasurementGroup::Mass, MeasurementGroup::Volume, MeasurementGroup::Count])],
            'preferred_unit' => ['required', Rule::enum(UnitCode::class)],
            'is_staple' => ['boolean'],
            'aliases' => ['array', 'max:'.config('measurements.limits.aliases_per_ingredient')],
            'aliases.*' => ['nullable', 'string', 'max:120'],
            'packages' => ['array', 'max:'.config('measurements.limits.packages_per_ingredient')],
            'packages.*.id' => ['nullable', 'integer'],
            'packages.*.package_type' => ['required', Rule::enum(PackageType::class)],
            'packages.*.label' => ['required', 'string', 'max:120'],
            'packages.*.content_amount' => ['nullable', new PositiveDecimalQuantity],
            'packages.*.content_unit' => ['nullable', Rule::enum(UnitCode::class)],
        ]);

        $validator->after(function (ValidationValidator $validator) use ($user): void {
            $unit = UnitCode::tryFrom($this->preferred_unit);
            $group = MeasurementGroup::tryFrom($this->preferred_measurement_group);
            if ($unit !== null && $group !== null && $unit->measurementGroup() !== $group) {
                $validator->errors()->add('preferred_unit', 'The preferred unit must match the measurement group.');
            }

            $normalizedName = Str::of($this->name)->trim()->squish()->lower()->toString();
            $nameExists = Ingredient::query()->whereBelongsTo($user)->where('normalized_name', $normalizedName)->when($this->ingredientId !== null, fn ($query) => $query->where('id', '!=', $this->ingredientId))->exists();
            if ($nameExists) {
                $validator->errors()->add('name', 'You already have an ingredient with this name.');
            }

            foreach ($this->packages as $index => $package) {
                $hasAmount = filled($package['content_amount']);
                $hasUnit = filled($package['content_unit']);
                if ($hasAmount !== $hasUnit) {
                    $validator->errors()->add("packages.{$index}.content_amount", 'Provide both package content amount and unit, or leave both empty.');
                }
                if ($hasUnit && ! UnitCode::from($package['content_unit'])->isMetricContentUnit()) {
                    $validator->errors()->add("packages.{$index}.content_unit", 'Package contents must use a mass or volume unit.');
                }
            }

            $normalizedAliases = collect($this->aliases)->filter()->map(fn (string $alias): string => Str::of($alias)->trim()->squish()->lower()->toString());
            if ($normalizedAliases->duplicates()->isNotEmpty()) {
                $validator->errors()->add('aliases', 'Ingredient aliases must be unique.');
            }
        });

        $validated = $validator->validate();
        $validated['aliases'] = array_values(array_filter(
            is_array($validated['aliases'] ?? null) ? $validated['aliases'] : [],
            fn (mixed $alias): bool => is_string($alias) && filled($alias),
        ));

        return $validated;
    }

    public function addAlias(): void
    {
        $this->aliases[] = '';
    }

    public function removeAlias(int $index): void
    {
        unset($this->aliases[$index]);
        $this->aliases = array_values($this->aliases);
    }

    public function addPackage(): void
    {
        $this->packages[] = ['id' => null, 'key' => (string) Str::uuid(), 'package_type' => PackageType::Can->value, 'label' => '', 'content_amount' => '', 'content_unit' => 'g'];
    }

    public function removePackage(int $index): void
    {
        unset($this->packages[$index]);
        $this->packages = array_values($this->packages);
    }
}
