<?php

namespace App\Rules;

use App\Services\Measurements\QuantityInputParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use InvalidArgumentException;

final readonly class PositiveDecimalQuantity implements ValidationRule
{
    public function __construct(private QuantityInputParser $parser = new QuantityInputParser) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('The :attribute must be a positive decimal or fraction.');

            return;
        }

        try {
            $this->parser->parse((string) $value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
