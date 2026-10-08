<?php

declare(strict_types=1);

namespace Passa\Cardholder\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('cardholder::layouts.cardholder')]
#[Title('Entrar · Passa')]
final class Login extends Component
{
    public const int MAX_ATTEMPTS = 5;

    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public function login(): void
    {
        $this->validate();

        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Muitas tentativas. Tente de novo em '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($key);
            $this->reset('password');

            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha inválidos.',
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $user = Auth::user();

        $this->redirectIntended($user instanceof User ? $user->homeUrl() : route('my-card'), navigate: false);
    }

    public function render(): View
    {
        return view('cardholder::login');
    }

    private function throttleKey(): string
    {
        return Str::lower($this->email).'|'.request()->ip();
    }
}
