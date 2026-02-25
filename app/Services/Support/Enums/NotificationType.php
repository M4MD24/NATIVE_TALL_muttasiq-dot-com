<?php

declare(strict_types=1);

namespace App\Services\Support\Enums;

enum NotificationType: string
{
    case Default = 'warning';
    case Danger = 'danger';
}
