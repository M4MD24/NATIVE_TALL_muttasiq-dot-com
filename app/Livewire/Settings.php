<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Traits\HasSettings;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

class Settings extends Component implements HasActions, HasSchemas
{
    use HasSettings;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function render()
    {
        return view('livewire.settings');
    }
}
