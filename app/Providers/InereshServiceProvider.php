<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class InereshServiceProvider extends ServiceProvider
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
        Blade::directive('ineresh', function (string $expression): string {
            return <<<'HTML'
                <script>
                    document.addEventListener('alpine:init', () => {
                        Alpine.directive('ineresh', (el, {
                            expression
                        }, {
                            evaluate,
                            cleanup
                        }) => {
                            const handler = () => evaluate(expression);

                            handler(); // ? Runs at the first time

                            let rafId;

                            const updateHandler = ({
                                el: updatedEl
                            }) => {
                                if (updatedEl.contains(el) || updatedEl === el) {
                                    if (rafId) cancelAnimationFrame(rafId);
                                    rafId = requestAnimationFrame(() => {
                                        handler();
                                    });
                                }
                            };

                            const registerHook = () => {
                                if (!window.Livewire?.hook) {
                                    return;
                                }

                                window.Livewire.hook('morph.updated', updateHandler);
                            };

                            if (window.Livewire?.hook) {
                                registerHook();
                            } else {
                                document.addEventListener('livewire:init', registerHook, { once: true });
                            }

                            cleanup(() => {
                                if (rafId) cancelAnimationFrame(rafId);
                            });
                        });
                    });
                </script>
            HTML;
        });
    }
}
