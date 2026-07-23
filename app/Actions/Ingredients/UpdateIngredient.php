<?php

namespace App\Actions\Ingredients;

use App\Data\Measurements\QuantityInput;
use App\Enums\MeasurementGroup;
use App\Enums\PackageType;
use App\Enums\UnitCode;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\Measurements\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class UpdateIngredient
{
    public function __construct(private UnitConverter $converter) {}

    /**
     * @param  array{name: string, category?: string|null, preferred_measurement_group: string, preferred_unit: string, is_staple?: bool, aliases?: list<string>, packages?: list<array{id?: int|null, package_type: string, label: string, content_amount?: string|null, content_unit?: string|null}>}  $data
     */
    public function handle(User $user, Ingredient $ingredient, array $data): Ingredient
    {
        Gate::forUser($user)->authorize('update', $ingredient);
        $group = MeasurementGroup::from($data['preferred_measurement_group']);
        $unit = UnitCode::from($data['preferred_unit']);

        if ($unit->measurementGroup() !== $group) {
            throw new InvalidArgumentException('The preferred unit must belong to the preferred measurement group.');
        }

        return DB::transaction(function () use ($ingredient, $data, $group, $unit): Ingredient {
            $ingredient->update([
                'name' => Str::of($data['name'])->trim()->squish()->toString(),
                'normalized_name' => $this->normalizeName($data['name']),
                'category' => filled($data['category'] ?? null) ? Str::of((string) $data['category'])->trim()->squish()->toString() : null,
                'preferred_measurement_group' => $group,
                'preferred_unit' => $unit,
                'is_staple' => $data['is_staple'] ?? false,
            ]);

            $ingredient->aliases()->delete();
            foreach ($data['aliases'] ?? [] as $alias) {
                $name = Str::of($alias)->trim()->squish()->toString();
                if ($name !== '') {
                    $ingredient->aliases()->create(['name' => $name, 'normalized_name' => $this->normalizeName($name)]);
                }
            }

            $retainedPackageIds = [];
            foreach ($data['packages'] ?? [] as $packageData) {
                $package = isset($packageData['id'])
                    ? $ingredient->packages()->findOrFail($packageData['id'])
                    : $ingredient->packages()->make();
                $contentUnit = filled($packageData['content_unit'] ?? null) ? UnitCode::from((string) $packageData['content_unit']) : null;
                $contentAmount = filled($packageData['content_amount'] ?? null) ? $this->numeric((string) $packageData['content_amount']) : null;
                $normalizedContentAmount = null;

                if ($contentAmount !== null && $contentUnit !== null) {
                    if (! $contentUnit->isMetricContentUnit()) {
                        throw new InvalidArgumentException('Package contents must use a mass or volume unit.');
                    }
                    $quantity = $this->converter->normalize(new QuantityInput($contentAmount, $contentUnit, $ingredient->id));
                    $contentAmount = $quantity->amount;
                    $normalizedContentAmount = $quantity->normalizedAmount;
                }

                if ($package->exists && ($package->recipeIngredients()->exists() || $package->pantryEntries()->exists())) {
                    $oldAmount = $package->content_amount;
                    $amountChanged = ($oldAmount === null) !== ($contentAmount === null)
                        || ($oldAmount !== null && $contentAmount !== null && bccomp($oldAmount, $contentAmount, 6) !== 0);
                    $unitChanged = $package->content_unit?->value !== $contentUnit?->value;

                    // Referenced package content is part of normalized historical truth; changing
                    // either half would silently reinterpret existing recipe and pantry amounts.
                    if ($amountChanged || $unitChanged) {
                        throw new InvalidArgumentException('Referenced package contents are immutable. Create a new package definition instead.');
                    }
                }

                $package->fill([
                    'package_type' => PackageType::from($packageData['package_type']),
                    'label' => Str::of($packageData['label'])->trim()->squish()->toString(),
                    'content_amount' => $contentAmount,
                    'content_unit' => $contentUnit,
                    'normalized_content_amount' => $normalizedContentAmount,
                ])->save();
                $retainedPackageIds[] = $package->id;
            }

            // Omitted referenced packages remain available to old data; only unused definitions
            // are safe to remove during form synchronization.
            $ingredient->packages()->whereNotIn('id', $retainedPackageIds)
                ->doesntHave('recipeIngredients')
                ->doesntHave('pantryEntries')
                ->delete();

            return $ingredient->refresh()->load(['aliases', 'packages']);
        });
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->trim()->squish()->lower()->toString();
    }

    /** @return numeric-string */
    private function numeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Package content must be numeric.');
        }

        return $value;
    }
}
