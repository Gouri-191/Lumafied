<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeText implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        $trimmed = trim($value);

        // Disallow CSV/Excel formula injection prefixes
        if (preg_match('/^[=+\-@|\t\r]/', $trimmed)) {
            $fail("The :attribute contains unsafe leading formula characters (=, +, -, @, |).");
            return;
        }

        // Disallow OS command execution markers often found in DDE/CSV injection payloads
        if (preg_match('/(cmd\||powershell\||\bcalc\b|\/c\s+calc)/i', $trimmed)) {
            $fail("The :attribute contains invalid or suspicious command patterns.");
            return;
        }
    }
}
