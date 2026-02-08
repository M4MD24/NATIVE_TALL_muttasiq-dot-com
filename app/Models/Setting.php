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
            'does_automatically_switch_completed_athkar' => [
                'default' => true,
                'label' => '1. الانتقال التلقائي عند اكتمال عدد الذكر.',
            ],
            'does_clicking_switch_athkar_too' => [
                'default' => true,
                'label' => '2. الضغط والنقر يقوم بالانتقال أيضا للذكر التالي، وليس مجرد السحب فحسب.',
                'help' => 'ولكن إن قمت بالعودة للأذكار التامة، أو كان الخيار الأذكار (1) معطلا، فالضغط يقوم بزيادة العدّ.',
            ],
            'does_prevent_switching_athkar_until_completion' => [
                'default' => true,
                'label' => '3. المنع من الانتقال بين الأذكار حتى إنهائها أولًا.',
                'help' => 'وكذلك يقوم بالسماح بإعادة استعراض أذكار الصباح والمساء حتى عند إتمامها.',
            ],
            'does_skip_notice_panels' => [
                'default' => false,
                'label' => '4. تجاوز رسائل التعريف أو التهنئة وما شابه.',
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
