<?php

namespace App\Enums;

enum GroceryCategory: string
{
    case VegetablesAndFruit = 'vegetables_and_fruit';
    case Dairy = 'dairy';
    case MeatAndFish = 'meat_and_fish';
    case Bakery = 'bakery';
    case DryGoods = 'dry_goods';
    case CannedAndJarredGoods = 'canned_and_jarred_goods';
    case Frozen = 'frozen';
    case HerbsAndSpices = 'herbs_and_spices';
    case Household = 'household';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public static function fromIngredientCategory(?string $category): self
    {
        $value = str($category ?? '')->lower()->toString();

        return match (true) {
            str_contains($value, 'vegetable'), str_contains($value, 'fruit'), str_contains($value, 'produce') => self::VegetablesAndFruit,
            str_contains($value, 'dairy'), str_contains($value, 'egg') => self::Dairy,
            str_contains($value, 'meat'), str_contains($value, 'fish'), str_contains($value, 'seafood') => self::MeatAndFish,
            str_contains($value, 'bread'), str_contains($value, 'bakery') => self::Bakery,
            str_contains($value, 'canned'), str_contains($value, 'jarred') => self::CannedAndJarredGoods,
            str_contains($value, 'frozen') => self::Frozen,
            str_contains($value, 'herb'), str_contains($value, 'spice') => self::HerbsAndSpices,
            str_contains($value, 'household'), str_contains($value, 'cleaning') => self::Household,
            str_contains($value, 'dry'), str_contains($value, 'grain'), str_contains($value, 'pasta') => self::DryGoods,
            default => self::Other,
        };
    }
}
