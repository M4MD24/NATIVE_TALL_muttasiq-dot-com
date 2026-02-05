<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class LazyCssServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Blade::directive('lazyCss', function (string $expression): string {
            $template = <<<'PHP'
                <?php
                $__lazyCssAssets = collect(\Illuminate\Support\Arr::wrap(%s))
                    ->filter()
                    ->map(fn ($path) => \Illuminate\Support\Facades\Vite::asset($path))
                    ->values();
                $__lazyCssJson = $__lazyCssAssets->toJson();
                ?>
                <meta
                    name="lazy-css-anchor"
                    content="before-app-css"
                    data-lazy-css="<?php echo e($__lazyCssJson); ?>"
                />
                <script>
                    (function () {
                        if (typeof document === 'undefined') {
                            return;
                        }

                        const anchor = document.querySelector('meta[name="lazy-css-anchor"]');

                        if (!anchor) {
                            return;
                        }

                        const parseUrls = (value) => {
                            if (!value) {
                                return [];
                            }

                            try {
                                const parsed = JSON.parse(value);

                                if (Array.isArray(parsed)) {
                                    return parsed.filter(Boolean);
                                }
                            } catch (error) {
                                return value
                                    .split(',')
                                    .map((item) => item.trim())
                                    .filter(Boolean);
                            }

                            return [];
                        };

                        const insertBeforeAnchor = (node) => {
                            if (!document.head) {
                                return;
                            }

                            document.head.insertBefore(node, anchor);
                        };

                        const loadCss = (url) => {
                            if (!url) {
                                return;
                            }

                            if (document.querySelector(`link[data-lazy-css="${url}"]`)) {
                                return;
                            }

                            const link = document.createElement('link');
                            link.rel = 'stylesheet';
                            link.href = url;
                            link.dataset.lazyCss = url;

                            insertBeforeAnchor(link);
                        };

                        const loadAll = () => {
                            const urls = parseUrls(anchor.dataset.lazyCss);

                            if (!Array.isArray(urls) || urls.length === 0) {
                                return;
                            }

                            [...new Set(urls)].forEach((url) => {
                                loadCss(url);
                            });
                        };

                        if (typeof requestIdleCallback === 'function') {
                            requestIdleCallback(loadAll);
                        } else {
                            setTimeout(loadAll, 1);
                        }
                    })();
                </script>
                PHP;

            return sprintf($template, $expression);
        });
    }
}
