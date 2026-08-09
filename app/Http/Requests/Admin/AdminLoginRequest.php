<?php

namespace App\Http\Requests\Admin;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in for the platform panel.
 *
 * Throttled harder than the merchant login, and on two keys rather than one.
 * Email+IP alone is trivially defeated by spraying one password across many
 * addresses, and the operator list is small enough that a spray is the obvious
 * attack — so the IP is also limited on its own.
 */
class AdminLoginRequest extends FormRequest
{
    /** Attempts per email+IP before lockout. */
    private const MAX_PER_ACCOUNT = 5;

    /** Attempts from one IP across all accounts. */
    private const MAX_PER_IP = 15;

    private const DECAY_SECONDS = 900;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        /*
         | `is_active` travels with the credentials rather than being checked
         | after a successful attempt. A deactivated account then fails exactly
         | like a wrong password — no session is ever created for it, and the
         | response does not tell an attacker the address is real.
         |
         | `remember` is not accepted at all: a long-lived cookie on the panel
         | that can suspend stores and move money is not worth the convenience.
         */
        $credentials = [
            ...$this->only('email', 'password'),
            'is_active' => true,
        ];

        if (! Auth::guard('admin')->attempt($credentials)) {
            RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
            RateLimiter::hit($this->ipThrottleKey(), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->ipThrottleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        $tooMany = RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_PER_ACCOUNT)
            || RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::MAX_PER_IP);

        if (! $tooMany) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->ipThrottleKey()),
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return 'admin-login|' . Str::transliterate(Str::lower($this->string('email')) . '|' . $this->ip());
    }

    private function ipThrottleKey(): string
    {
        return 'admin-login-ip|' . $this->ip();
    }
}
