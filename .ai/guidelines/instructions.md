# Muttasiq TALL and Native App

This shared source code base is representing the web version primarily, the one that serves the administration panel and the API. There are special changes via NativePHP for multiple platforms; distinguished between using `is_platform` global function helper. And since it's a `WebView`, performance is our primary objective.

## Terminology
- When saying "mobile" broadly, then it's both `base` breakpoint in web small mobile screens and the native Android and IOS apps; unless explicitly told otherwise.
- When saying a "click" broadly, keep in mind that it would mean a "tap" for mobile and tablet devices; unless explicitly told otherwise. And usually that "touch" is doing the same thing a "hover" does in web big screens.

## Architecture
- Add partials as components under [resources/views/components/partials], instead of adding plain `@include`s, in order to gain Livewire Blaze speed.
- Know that app design colors are specified in [resources/css/app.css] (`@theme`) and [config/app.php] files. Those are even used by Filament in [app/Providers/FilamentServiceProvider.php].
- Size primary texts with Fitty [resources/js/packages/fitty.js] using `[data-fitty-target]` / `[data-fitty-box]` and the existing `athkar-fitty-refit` refresh flow.



- Keep main reader text shimmer on the shared helper (`resources/js/support/alpine/shimmer.js`), configured by `resources/js/support/alpine/data/athkar-app-reader.js`.
- Use the Alpine breakpoint helper store `bp` from `resources/js/support/alpine/storage/breakpointer.js` (`current`, `is()`, `isTouch()`, `isTablet()`, `shouldUseSortHandles()`).
- Keep lazy asset strategy: CSS through `@lazyCss(...)` (`app/Providers/LazyCssServiceProvider.php`) and lazy JS bundle scheduling in `resources/js/app.js` (idle import of `app-lazy.js`).
- Reuse CSS variable helpers instead of custom parsing: JS helpers in `resources/js/support/css-variables.js`, PHP theme helpers in `app/Services/Functions/theme.php`.
- Treat web as SPA-like shell with one main route (`routes/web.php`: `/`) and client-side view transitions in `resources/views/home.blade.php`.
- Use `$livewireLock` (`resources/js/support/alpine/magic/livewire-lock.js`) for action locking where repeated taps/clicks could cause duplicate requests.
- Use hash navigation via `x-hash-actions` (`resources/js/packages/alpine/hash-actions.js`) and `switch-view` events for native/web navigation consistency.
- Use Filament as the primary UI engine for notifications, modals, slideovers, forms, tables, and admin panels (see `app/Livewire/ControlPanel.php`, `app/Livewire/AthkarManager.php`).
- Keep control panel as a Filament tabbed action: settings, changelogs, and about tabs are built in `ControlPanel` with `HasControlPanelSettingsTab`, `HasControlPanelChangelogsTab`, and `HasControlPanelAboutTab`.
- Place reusable cross-feature utilities in `Support` namespaces by layer (backend: `app/Services/Support/*`, `app/Console/Commands/Support/*`; frontend: `resources/js/support/*`, `resources/css/support/*`).

## Preferences
- Do not restore reduced-motion suppression for transitions: `resources/js/overrides/livewire-transition-consistency.js` intentionally removes Livewire-injected `prefers-reduced-motion` transition styles.
- Keep interaction transitions enabled by default unless a specific task explicitly requires changing this project preference.

## Documentation
- When looking for docs, check first which versions are in `composer.json` and `package.json`.
- When implementing a new development feature, and when it's very essential and uncommon (such as the existance of this instructions file), make sure it's documented in README.

## Development
- The native apps need some modifications on the NativePHP engine. These are done via `muttasiq-patches` NativePHP plugin. It's supposedly located in [~/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq-patches] directory.
- Preferred container workflow is `lara-stacker`; project test scripts (`.scripts/testing/*.sh`) already auto-detect and run inside matching Docker app containers when available.
- Expected local helper path for stack tooling is `~/Code/Scripts/CLI_LARAVEL_lara-stacker/` (includes `lara-stacker.sh` and `scripts/rewire.sh` used by the team setup flow).
- Native engine patching is externalized to `goodm4ven/nativephp-muttasiq-patches`, enabled by `app/Providers/NativeServiceProvider.php`.
- The patch plugin is build-time only and runs `php artisan nativephp:muttasiq:patches` as a NativePHP Android pre-compile hook.
- Toggle local development of that plugin using `.scripts/composer-local-plugins-switch.sh`, which targets `~/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq-patches` by default.

## Testing
- Do not write tests unless explicitely told to.
- When told to write tests, please find first a related feature test and try to add to it, if it was simple enough.
- Feature tests must be put inside either App or Browser folders, where Browser is for PestPHP browser testing.
- PestPHP browser testing is buggy currently, and our setup is in a docker container, so make sure you're using [.scripts/testing] scripts that account for the setup.

## Debugging
- Keep `resources/js/support/debugging/alpine-transition-debugger.js` available for transition investigation; enable by setting `window.__ALPINE_TRANSITION_DEBUG__ = true`.
- `resources/js/support/alpine/data/layout-manager.js` tracks action/modal events (`open-modal`, `close-modal`, etc.) and should stay in sync with Filament modal behavior.
- For browser test instability and Playwright leftovers, rely on `.scripts/testing/support/preflight.sh` and the `.scripts/testing/` wrappers instead of manual ad-hoc test commands.

## Finishing
- When have modified CSS or JS files, use `npm run format:prettier` to format them.
- When have modified Blade-PHP files, use `format:blade` to format them.
- When have modified PHP files, ensure `php artisan pint` was ran to format them.
- When have modified PHP files, run static analysis using `vendor/bin/phpstan analyse`.
