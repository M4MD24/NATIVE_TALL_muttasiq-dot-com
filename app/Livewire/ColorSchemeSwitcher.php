<?php

declare(strict_types=1);

namespace App\Livewire;

// use App\Services\Support\Enums\NotificationType;
// use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
// use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Contracts\View\View;
// use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class ColorSchemeSwitcher extends Component
{
    // use WithRateLimiting;

    #[On('color-scheme-initialized')]
    #[On('color-scheme-toggled')]
    public function synchronizeColorScheme(?bool $isDarkModeOn): void
    {
        // if (is_platform('desktop')) {
        //     try {
        //         $this->rateLimit(10);
        //     } catch (TooManyRequestsException) {
        //         notify(
        //             'heroicon-s-shield-exclamation',
        //             'توقف عن فعل هذا بسرعة!',
        //             'انتظر ريثما تستطيع المحاولة مجدّدا...',
        //             NotificationType::Danger,
        //         );

        //         return;
        //     }
        // }

        if (is_null($isDarkModeOn)) {
            return;
        }

        session()->put('is-dark-mode-on', $isDarkModeOn);
    }

    public function render(): View
    {
        return view('livewire.color-scheme-switcher');
    }
}
