<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts::guest')] class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $key = 'login:'.strtolower($this->email);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'For mange forsøk. Prøv igjen om '.RateLimiter::availableIn($key).' sekunder.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'email' => 'Feil e-post eller passord.',
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirectIntended(route('dashboard'), navigate: true);
    }
};
?>

<div>
    <div class="mb-7 flex justify-center">
        <x-brand href="#" />
    </div>

    <x-card class="p-8">
        <h1 class="text-2xl font-bold tracking-tight">Logg inn</h1>
        <p class="mt-1.5 text-sm text-muted">Privat forvaltning av eiendomsporteføljen.</p>

        <form wire:submit="login" class="mt-6 space-y-4">
            <div>
                <label for="email" class="mb-2 block text-[13px] font-semibold text-ink-soft">E-post</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" autofocus
                    class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-3 text-[15px] outline-none focus:border-terra">
                @error('email')
                    <p class="mt-1.5 text-[13px] text-terra">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-[13px] font-semibold text-ink-soft">Passord</label>
                <input id="password" type="password" wire:model="password" autocomplete="current-password"
                    class="w-full rounded-[10px] border border-line-strong bg-surface px-3.5 py-3 text-[15px] outline-none focus:border-terra">
                @error('password')
                    <p class="mt-1.5 text-[13px] text-terra">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" wire:model="remember" class="rounded border-line-strong text-terra focus:ring-terra">
                Husk meg
            </label>

            <button type="submit"
                class="w-full rounded-[11px] bg-terra py-3.5 text-[15px] font-semibold text-white transition-opacity hover:opacity-90">
                <span wire:loading.remove wire:target="login">Logg inn</span>
                <span wire:loading wire:target="login">Logger inn …</span>
            </button>
        </form>
    </x-card>
</div>
