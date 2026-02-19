<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Traits\HasControlPanel;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

class ControlPanel extends Component implements HasActions, HasSchemas
{
    use HasControlPanel;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function render()
    {
        return view('livewire.control-panel');
    }
}
