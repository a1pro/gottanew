<?php

namespace App\Rules;

use App\Support\Timezone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TimezoneIdentifier implements ValidationRule
{
     public function validate(string $attribute, mixed $value, Closure $fail):
void
     {
         $raw = is_string($value) ? trim($value) : '';

          // Let `required` / `filled` / `nullable` rules handle presence.
          if ($raw === '') {
              return;
          }

          // Normalize known aliases first.
          $normalized = Timezone::normalize($raw, '__invalid__');

          if ($normalized === '__invalid__') {
              $fail("The {$attribute} field must be a valid IANA timezone name.");
              return;
          }

        // If normalization fell back to UTC but the user didn't explicitly
// supply UTC,
        // treat this as invalid to avoid silent timezone corruption.
        if ($normalized === 'UTC' && strtoupper($raw) !== 'UTC' && $raw !==
'Etc/UTC') {
             $fail("The {$attribute} field must be a valid IANA timezone name.");
        }
    }
}
