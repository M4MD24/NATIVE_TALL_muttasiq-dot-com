# Muttasiq TALL and Native App

This shared source code base is representing the web version primarily, the one that serves the administration panel and the API. There are special changes via NativePHP for multiple platforms; distinguished between using `is_platform` global function helper. And since it's a `WebView`, performance is our primary objective.

## Terminology
- When saying "mobile" broadly, then it's both `base` breakpoint in web small mobile screens and the native Android and IOS apps; unless explicitly told otherwise.
- When saying a "click" broadly, keep in mind that it would mean a "tap" for mobile and tablet devices; unless explicitly told otherwise. And usually that "touch" is doing the same thing a "hover" does in web big screens.



(ARCH - never using normal laravel partials, but rather always components put in [resources/views/components/partials] in order ot utilize Livewire Blaze speed)
(ARCH - colors in app.css, app.php, etc; not using random colors, we even override filament colors for both schemes)
(ARCH - using fitty with main texts)
(ARCH - using shimmer with main texts)
(ARCH - bp helper)
(DEV - docker via lara-stacker at ~/Code/Scripts/CLI_LARAVEL_lara-stacker/)
(ARCH - lazy css and js)
(ARCH - css variables)
(ARCH - SPA with a single home web route for now)
(debugging - like alpine-transition-debugger, etc)
(ARCH - livewire-lock)
(ARCH - hash-actions to facilitate navigation in native)
(PREFS - NEVER using css reduce motion, and in fact we override livewire-transition-consistency to not do it!)
(ARCH - relying on filament php for notifications, modals, slideovers, tables, input forms and administration ALWAYS)
(ARCH - we have a control panel filament action that is tabbed to settings, main feature changelogs, and about us links)
(ARCH - oraganize helper stuff within `support/Support` folders always, AFTER putting them in their proper and standradized main folder suiting their type, the things that can techincally be used in any other app; like putting Enums in Services within App, but also putting support enums in App\Services\Support\Enums\ folder!)



## Documentation
- When looking for docs, check first which versions are in `composer.json` and `package.json`.
- When implementing a new development feature, and when it's very essential and uncommon (such as the existance of this instructions file), make sure it's documented in README.

## Development
- The native apps need some modifications on the NativePHP engine. These are done via `muttasiq-patches` NativePHP plugin. It's supposedly located in [~/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq-patches] directory.

## Testing
- Do not write tests unless explicitely told to.
- When told to write tests, please find first a related feature test and try to add to it, if it was simple enough.
- Feature tests must be put inside either App or Browser folders, where Browser is for PestPHP browser testing.
- PestPHP browser testing is buggy currently, and our setup is in a docker container, so make sure you're using [.scripts/testing] scripts that account for the setup.

## Finishing
- When have modified CSS or JS files, use `npm run format:prettier` to format them.
- When have modified Blade-PHP files, use `format:blade` to format them.
- When have modified PHP files, ensure `php artisan pint` was ran to format them.
- When have modified PHP files, run static analysis using `vendor/bin/phpstan analyse`.
