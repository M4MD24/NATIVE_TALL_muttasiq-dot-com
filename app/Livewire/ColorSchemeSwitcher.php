<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

class ColorSchemeSwitcher extends Component
{
    #[Renderless]
    #[On('color-scheme-initialized')]
    #[On('color-scheme-toggled')]
    public function synchronizeColorScheme(?bool $isDarkModeOn): void
    {
        if (is_null($isDarkModeOn)) {
            return;
        }

        if (is_dark_mode_on() === $isDarkModeOn) {
            return;
        }

        session()->put('is-dark-mode-on', $isDarkModeOn);
    }

    public function render(): View
    {
        return view('livewire.color-scheme-switcher');
    }
}
