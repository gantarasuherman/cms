<?php

namespace App\Rules;

use App\Services\Captcha\CaptchaServiceInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Captcha implements ValidationRule
{
    public function __construct(private readonly CaptchaServiceInterface $captcha)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->captcha->enabled()) {
            return;
        }

        if (! $this->captcha->verify(is_string($value) ? $value : null, request()->ip())) {
            $fail(__('Verifikasi CAPTCHA gagal. Silakan coba lagi.'));
        }
    }
}

