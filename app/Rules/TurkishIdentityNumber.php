<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a T.C. Kimlik Numarası: the 10th and 11th digits are derived from
 * the first nine, so a typo or an OCR error is rejected instead of stored.
 */
class TurkishIdentityNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[1-9][0-9]{10}$/', $value)) {
            $fail('panel.validation.tc_no_format')->translate();

            return;
        }

        $digits = array_map('intval', str_split($value));

        $odd = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $even = $digits[1] + $digits[3] + $digits[5] + $digits[7];

        $tenth = ($odd * 7 - $even) % 10;
        $eleventh = ($odd + $even + $tenth) % 10;

        if ($tenth !== $digits[9] || $eleventh !== $digits[10]) {
            $fail('panel.validation.tc_no_checksum')->translate();
        }
    }
}
