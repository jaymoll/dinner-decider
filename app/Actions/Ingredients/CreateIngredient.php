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

final readonly class CreateIngredient
{
    public function __construct(private UnitConverter $converter) {}

    /**
     * @param  array{name: string, category?: string|null, preferred_measurement_group: string, preferred_unit: string, is_staple?: bool, aliases?: list<string>, packages?: list<array{package_type: string, label: string, content_amount?: string|null, content_unit?: string|null}>}  $data
     */
    public function handle(User $user, array $data): Ingredient
    {
        Gate::forUser($user)->authorize('create', Ingredient::class);
        $this->assertPreferredUnit($data['preferred_measurement_group'], $data['preferred_unit']);

        return DB::transaction(function () use ($user, $data): Ingredient {
            $ingredient = Ingredient::query()->create([
                'user_id' => $user->id,
                'name' => Str::of($data['name'])->trim()->squish()->toString(),
                'normalized_name' => $this->normalizeName($data['name']),
                'category' => filled($data['category'] ?? null) ? Str::of((string) $data['category'])->trim()->squish()->toString() : null,
                'preferred_measurement_group' => MeasurementGroup::from($data['preferred_measurement_group']),
                'preferred_unit' => UnitCode::from($data['preferred_unit']),
                'is_staple' => $data['is_staple'] ?? false,
            ]);

            $this->syncAliases($ingredient, $data['aliases'] ?? []);
            $this->syncPackages($ingredient, $data['packages'] ?? []);

            return $ingredient->load(['aliases', 'packages']);
        });
    }

    /** @param list<string> $aliases */
    private function syncAliases(Ingredient $ingredient, array $aliases): void
    {
        foreach ($aliases as $alias) {
            $name = Str::of($alias)->trim()->squish()->toString();

            if ($name === '') {
                continue;
            }

            $ingredient->aliases()->create(['name' => $name, 'normalized_name' => $this->normalizeName($name)]);
        }
    }

    /** @param list<array{package_type: string, label: string, content_amount?: string|null, content_unit?: string|null}> $packages */
    private function syncPackages(Ingredient $ingredient, array $packages): void
    {
        foreach ($packages as $package) {
            $contentUnit = filled($package['content_unit'] ?? null) ? UnitCode::from((string) $package['content_unit']) : null;
            $contentAmount = filled($package['content_amount'] ?? null) ? (string) $package['content_amount'] : null;
            $normalizedContentAmount = null;

            if ($contentAmount !== null && $contentUnit !== null) {
                if (! $contentUnit->isMetricContentUnit()) {
                    throw new InvalidArgumentException('Package contents must use a mass or volume unit.');
                }

                $quantity = $this->converter->normalize(new QuantityInput($contentAmount, $contentUnit, $ingredient->id));
                $contentAmount = $quantity->amount;
                $normalizedContentAmount = $quantity->normalizedAmount;
            }

            $ingredient->packages()->create([
                'package_type' => PackageType::from($package['package_type']),
                'label' => Str::of($package['label'])->trim()->squish()->toString(),
                'content_amount' => $contentAmount,
                'content_unit' => $contentUnit,
                'normalized_content_amount' => $normalizedContentAmount,
            ]);
        }
    }

    private function assertPreferredUnit(string $group, string $unit): void
    {
        if (UnitCode::from($unit)->measurementGroup() !== MeasurementGroup::from($group)) {
            throw new InvalidArgumentException('The preferred unit must belong to the preferred measurement group.');
        }
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->trim()->squish()->lower()->toString();
    }
}
