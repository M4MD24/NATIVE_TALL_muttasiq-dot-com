<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'boolean',
        ];
    }

    /**
     * @return array<string, array{default: bool, label: string, help?: string}>
     */
    public static function definitions(): array
    {
        return [
            'some_setting' => [
                'default' => true,
                'label' => 'Does something, really...',
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        $defaults = [];

        foreach (self::definitions() as $key => $definition) {
            $defaults[$key] = $definition['default'];
        }

        return $defaults;
    }
}
