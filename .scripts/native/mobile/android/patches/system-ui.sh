#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../../" && pwd)"

python3 - "${root_dir}" <<'PY'
import re
import sys
from pathlib import Path

root_dir = Path(sys.argv[1])


class PatchError(RuntimeError):
    pass


def locate_kotlin_function(text: str, func_name: str):
    pattern = re.compile(
        rf"(?m)^([ \t]*)(?:(?:private|public|protected|override)\s+)*fun\s+{re.escape(func_name)}\s*\("
    )
    match = pattern.search(text)
    if not match:
        raise PatchError(f"function '{func_name}' not found")

    start = text.find("{", match.end())
    if start == -1:
        raise PatchError(f"function '{func_name}' has no opening body brace")

    depth = 1
    i = start + 1
    end = None
    in_string = False
    in_triple = False
    escape = False

    while i < len(text):
        if in_triple:
            if text[i:i + 3] == '"""':
                in_triple = False
                i += 3
                continue
        elif in_string:
            if escape:
                escape = False
            elif text[i] == "\\":
                escape = True
            elif text[i] == '"':
                in_string = False
        else:
            if text[i:i + 3] == '"""':
                in_triple = True
                i += 3
                continue
            if text[i] == '"':
                in_string = True
            elif text[i] == "{":
                depth += 1
            elif text[i] == "}":
                depth -= 1
                if depth == 0:
                    end = i
                    break
        i += 1

    if end is None:
        raise PatchError(f"function '{func_name}' has no closing body brace")

    return match.group(1), start, end


def set_kotlin_function_body(text: str, func_name: str, new_body: str, label: str) -> tuple[str, bool]:
    indent, start, end = locate_kotlin_function(text, func_name)
    body_indent = indent + "    "
    indented_body = "\n".join(
        [(body_indent + line if line.strip() else line) for line in new_body.splitlines()]
    )
    replacement = text[: start + 1] + "\n" + indented_body + "\n" + indent + "}" + text[end + 1 :]

    if replacement == text:
        return text, False

    return replacement, True


def replace_once_or_error(
    text: str,
    old: str,
    new: str,
    label: str,
    already_contains: str | None = None,
) -> tuple[str, bool]:
    if old in text:
        return text.replace(old, new, 1), True

    if already_contains is not None and already_contains in text:
        return text, False

    if new in text:
        return text, False

    raise PatchError(f"pattern not found for {label}")


def replace_regex_once_or_error(
    text: str,
    pattern: str,
    replacement: str,
    label: str,
    already_contains: str | None = None,
    flags: int = re.MULTILINE | re.DOTALL,
) -> tuple[str, bool]:
    updated, count = re.subn(pattern, replacement, text, count=1, flags=flags)
    if count:
        return updated, True

    if already_contains is not None and already_contains in text:
        return text, False

    if replacement in text:
        return text, False

    raise PatchError(f"regex pattern not found for {label}")


def insert_before_or_error(text: str, anchor: str, insert: str, label: str) -> tuple[str, bool]:
    if insert in text:
        return text, False

    if anchor not in text:
        raise PatchError(f"anchor not found for {label}")

    return text.replace(anchor, f"{insert}\n{anchor}", 1), True


def patch_main_activity(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-system-ui] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        configure_status_bar_body = """val windowInsetsController = WindowInsetsControllerCompat(window, window.decorView)

val isSystemDarkMode = (resources.configuration.uiMode and
    Configuration.UI_MODE_NIGHT_MASK) == Configuration.UI_MODE_NIGHT_YES

val systemBarColor = if (isSystemDarkMode) {
    android.graphics.Color.BLACK
} else {
    android.graphics.Color.WHITE
}

// Use opaque, system-themed bars so web content doesn't bleed into system UI
window.statusBarColor = systemBarColor
window.navigationBarColor = systemBarColor

// Always match system light/dark for icon contrast
windowInsetsController.isAppearanceLightStatusBars = !isSystemDarkMode
windowInsetsController.isAppearanceLightNavigationBars = !isSystemDarkMode

Log.d(
    "StatusBar",
    "System bars style: auto (system ${if (isSystemDarkMode) \"dark\" else \"light\"} mode, requested=$statusBarStyle)"
)"""
        text, updated = set_kotlin_function_body(
            text,
            "configureStatusBar",
            configure_status_bar_body,
            "configureStatusBar body",
        )
        changed = changed or updated

        text, updated = replace_once_or_error(
            text,
            "For edge-to-edge mode, system bars are transparent to allow content to draw behind them",
            "For edge-to-edge mode, system bars follow the system light/dark theme so content does not bleed through",
            "status bar docstring",
            already_contains="For edge-to-edge mode, system bars follow the system light/dark theme so content does not bleed through",
        )
        changed = changed or updated

        field_pattern = r"(    private var pendingInsets: Insets\? = null\n)(?!    private var lastStableInsets: Insets\? = null\n)"
        field_replacement = r"\1    private var lastStableInsets: Insets? = null\n"
        updated_text, count = re.subn(field_pattern, field_replacement, text, count=1, flags=re.MULTILINE)
        if count:
            text = updated_text
            changed = True
        elif "    private var lastStableInsets: Insets? = null\n" not in text:
            raise PatchError("pattern not found for lastStableInsets field")

        lifecycle_flag_pattern = (
            r"(    private var shouldStopWatcher = false\n)"
            r"(?!    @Volatile\n    private var isMainActivityDestroyed = false\n)"
        )
        lifecycle_flag_replacement = (
            "    private var shouldStopWatcher = false\n"
            "    @Volatile\n"
            "    private var isMainActivityDestroyed = false\n"
        )
        updated_text, count = re.subn(lifecycle_flag_pattern, lifecycle_flag_replacement, text, count=1, flags=re.MULTILINE)
        if count:
            text = updated_text
            changed = True
        elif "    private var isMainActivityDestroyed = false\n" not in text:
            raise PatchError("pattern not found for lifecycle destroyed flag")

        companion_original = (
            "    companion object {\n"
            "        // Static instance holder for accessing MainActivity from other activities\n"
            "        var instance: MainActivity? = null\n"
            "            private set\n"
            "    }\n"
        )
        companion_replacement = (
            "    companion object {\n"
            "        // Static instance holder for accessing MainActivity from other activities\n"
            "        var instance: MainActivity? = null\n"
            "            private set\n"
            "        private val environmentInitMonitor = Any()\n"
            "        private var environmentInitInProgress = false\n"
            "    }\n"
        )
        text, updated = replace_once_or_error(
            text,
            companion_original,
            companion_replacement,
            "MainActivity companion init lock",
            already_contains="        private var environmentInitInProgress = false\n",
        )
        changed = changed or updated

        stable_inset_pattern = (
            r"(            pendingInsets = systemBars\n)"
            r"(?!            if \(systemBars.top > 0 \|\| systemBars.bottom > 0 \|\| systemBars.left > 0 \|\| systemBars.right > 0\) \{\n"
            r"                lastStableInsets = systemBars\n"
            r"            \}\n)"
        )
        stable_inset_replacement = (
            "            pendingInsets = systemBars\n"
            "            if (systemBars.top > 0 || systemBars.bottom > 0 || systemBars.left > 0 || systemBars.right > 0) {\n"
            "                lastStableInsets = systemBars\n"
            "            }\n"
        )
        updated_text, count = re.subn(stable_inset_pattern, stable_inset_replacement, text, count=1, flags=re.MULTILINE)
        if count:
            text = updated_text
            changed = True
        elif "            if (systemBars.top > 0 || systemBars.bottom > 0 || systemBars.left > 0 || systemBars.right > 0) {\n                lastStableInsets = systemBars\n            }\n" not in text:
            raise PatchError("pattern not found for stable inset capture")

        css_old_simple = (
            "            // Inject CSS custom properties into WebView if ready\n"
            "            if (::webViewManager.isInitialized) {\n"
            "                injectSafeAreaInsets(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)\n"
            "            }\n"
        )
        css_old_fallback = (
            "            // Inject CSS custom properties into WebView if ready\n"
            "            if (::webViewManager.isInitialized) {\n"
            "                val isZeroInsets = systemBars.top == 0 && systemBars.bottom == 0 && systemBars.left == 0 && systemBars.right == 0\n"
            "                val effectiveInsets = if (isZeroInsets) (lastStableInsets ?: systemBars) else systemBars\n"
            "                injectSafeAreaInsets(effectiveInsets.left, effectiveInsets.top, effectiveInsets.right, effectiveInsets.bottom)\n"
            "            }\n"
        )
        css_new = "            // Safe area handled by Compose insets\n"

        if css_old_simple in text:
            text = text.replace(css_old_simple, css_new, 1)
            changed = True
        elif css_old_fallback in text:
            text = text.replace(css_old_fallback, css_new, 1)
            changed = True
        elif css_new not in text:
            raise PatchError("pattern not found for safe-area insets listener replacement")

        text, updated = replace_once_or_error(
            text,
            "            // Inject safe area insets BEFORE loading any URL to prevent content shift\n"
            "            pendingInsets?.let {\n"
            "                injectSafeAreaInsets(it.left, it.top, it.right, it.bottom)\n"
            "            }\n",
            "            // Inject safe area insets BEFORE loading any URL to prevent content shift\n"
            "            injectSafeAreaInsetsToWebView()\n",
            "startup safe-area call",
            already_contains="            injectSafeAreaInsetsToWebView()\n",
        )
        changed = changed or updated

        inject_safe_area_body = """Log.d(
    "SafeArea",
    "Safe area handled by Compose layout insets"
)"""
        text, updated = set_kotlin_function_body(
            text,
            "injectSafeAreaInsets",
            inject_safe_area_body,
            "injectSafeAreaInsets body",
        )
        changed = changed or updated

        text, updated = set_kotlin_function_body(
            text,
            "injectSafeAreaInsetsToWebView",
            inject_safe_area_body,
            "injectSafeAreaInsetsToWebView body",
        )
        changed = changed or updated

        initialize_environment_body = """Thread {
    Log.d("LaravelInit", "📦 Starting async Laravel extraction...")
    if (isMainActivityDestroyed) {
        Log.w("LaravelInit", "Skipping environment init because activity is already destroyed")
        return@Thread
    }

    var acquiredInitSlot = false
    while (!acquiredInitSlot) {
        if (isMainActivityDestroyed) {
            Log.w("LaravelInit", "Skipping environment init because activity is already destroyed")
            return@Thread
        }

        synchronized(environmentInitMonitor) {
            if (!environmentInitInProgress) {
                environmentInitInProgress = true
                acquiredInitSlot = true
            }
        }

        if (!acquiredInitSlot) {
            try {
                Thread.sleep(50)
            } catch (e: InterruptedException) {
                Log.w("LaravelInit", "Environment init wait interrupted")
                return@Thread
            }
        }
    }

    try {
        laravelEnv = LaravelEnvironment(this)
        laravelEnv.initialize()

        if (isMainActivityDestroyed) {
            Log.w("LaravelInit", "Skipping onReady callback because activity was destroyed during init")
            return@Thread
        }

        Log.d("LaravelInit", "✅ Laravel environment ready — continuing")

        Handler(Looper.getMainLooper()).post {
            if (isMainActivityDestroyed || isFinishing || isDestroyed || supportFragmentManager.isDestroyed) {
                Log.w("LaravelInit", "Skipping onReady callback because activity is no longer valid")
                return@post
            }

            onReady()
        }
    } finally {
        synchronized(environmentInitMonitor) {
            environmentInitInProgress = false
        }
    }
}.start()"""

        text, updated = set_kotlin_function_body(
            text,
            "initializeEnvironmentAsync",
            initialize_environment_body,
            "initializeEnvironmentAsync body",
        )
        changed = changed or updated

        on_destroy_body = """isMainActivityDestroyed = true
super.onDestroy()
instance = null

// Post lifecycle event for plugins
NativePHPLifecycle.post(NativePHPLifecycle.Events.ON_DESTROY)

// Clean up coordinator fragment to prevent memory leaks
if (::coord.isInitialized && !supportFragmentManager.isDestroyed) {
    supportFragmentManager.beginTransaction()
        .remove(coord)
        .commitNowAllowingStateLoss()
}

if (::webViewManager.isInitialized) {
    val chromeClient = webView.webChromeClient
    if (chromeClient is WebChromeClient) {
        chromeClient.onHideCustomView()
    }
}

// Stop hot reload watcher thread
shouldStopWatcher = true
hotReloadWatcherThread?.interrupt()

if (::laravelEnv.isInitialized) {
    laravelEnv.cleanup()
}
phpBridge.shutdown()"""

        text, updated = set_kotlin_function_body(
            text,
            "onDestroy",
            on_destroy_body,
            "onDestroy body",
        )
        changed = changed or updated

        text, updated = replace_once_or_error(
            text,
            "                        AndroidView(\n"
            "                            factory = { webView },\n"
            "                            modifier = Modifier\n"
            "                                .fillMaxSize()\n"
            "                                .padding(paddingValues)\n"
            "                                .windowInsetsPadding(WindowInsets.ime),\n",
            "                        AndroidView(\n"
            "                            factory = { webView },\n"
            "                            modifier = Modifier\n"
            "                                .fillMaxSize()\n"
            "                                .padding(paddingValues)\n"
            "                                .windowInsetsPadding(WindowInsets.systemBars)\n"
            "                                .windowInsetsPadding(WindowInsets.ime),\n",
            "AndroidView system bars inset",
            already_contains="                                .windowInsetsPadding(WindowInsets.systemBars)\n",
        )
        changed = changed or updated

        text, updated = insert_before_or_error(
            text,
            "            // Splash overlay with fade animation (full screen, no insets)",
            "            SystemBarsScrim()",
            "SystemBarsScrim call",
        )
        changed = changed or updated

        system_bars_scrim_body = """val systemInDarkMode = isSystemInDarkTheme()
val barColor = if (systemInDarkMode) Color.Black else Color.White
val statusBarHeight = WindowInsets.statusBars.asPaddingValues().calculateTopPadding()
val navigationBarHeight = WindowInsets.navigationBars.asPaddingValues().calculateBottomPadding()
val extraBottom = 14.dp
val scrimBottomHeight = if (navigationBarHeight > 0.dp) {
    navigationBarHeight + extraBottom
} else {
    0.dp
}

Box(modifier = Modifier.fillMaxSize()) {
    if (statusBarHeight > 0.dp) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(statusBarHeight)
                .background(barColor)
        )
    }
    if (scrimBottomHeight > 0.dp) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(scrimBottomHeight)
                .align(Alignment.BottomCenter)
                .background(barColor)
        )
    }
}"""

        system_bars_scrim_definition = f"""    @Composable
    private fun SystemBarsScrim() {{
{chr(10).join(("        " + line) if line.strip() else line for line in system_bars_scrim_body.splitlines())}
    }}
"""

        if "private fun SystemBarsScrim()" in text:
            text, updated = set_kotlin_function_body(
                text,
                "SystemBarsScrim",
                system_bars_scrim_body,
                "SystemBarsScrim body",
            )
            changed = changed or updated
        else:
            text, updated = insert_before_or_error(
                text,
                "    /**\n     * Splash screen composable - shows custom image or fallback text\n     */",
                system_bars_scrim_definition.rstrip("\n"),
                "SystemBarsScrim definition",
            )
            changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-system-ui] patched: {path}")
        else:
            print(f"[native-system-ui] already ok: {path}")

        if "android.graphics.Color.TRANSPARENT" in text:
            print(f"[native-system-ui] warning: transparent system bars still present in {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-system-ui] error: {error} ({path})")
        return False, True


def patch_top_bar_component(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-system-ui] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        text, updated = replace_once_or_error(
            text,
            "public bool $showNavigationIcon = true,",
            "public ?bool $showNavigationIcon = null,",
            "TopBar showNavigationIcon default",
            already_contains="public ?bool $showNavigationIcon = null,",
        )
        changed = changed or updated

        text, updated = replace_once_or_error(
            text,
            "fn ($value) => $value !== null && $value !== false",
            "fn ($value) => $value !== null",
            "TopBar array_filter predicate",
            already_contains="fn ($value) => $value !== null",
        )
        changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-system-ui] patched: {path}")
        else:
            print(f"[native-system-ui] already ok: {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-system-ui] error: {error} ({path})")
        return False, True


def patch_bottom_nav_component(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-system-ui] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        text, updated = replace_once_or_error(
            text,
            "public string $labelVisibility = 'labeled',",
            "public ?string $labelVisibility = null,",
            "BottomNav labelVisibility default",
            already_contains="public ?string $labelVisibility = null,",
        )
        changed = changed or updated

        patched_method = """protected function toNativeProps(): array
    {
        return array_filter([
            'dark' => $this->dark,
            'label_visibility' => $this->labelVisibility,
            'active_color' => $this->activeColor,
        ], fn ($value) => $value !== null);
    }"""

        if patched_method in text:
            updated = False
        else:
            method_pattern_with_active_color = (
                r"protected function toNativeProps\(\): array\s*\{\s*"
                r"return \[\s*"
                r"'dark' => \$this->dark,\s*"
                r"'label_visibility' => \$this->labelVisibility,\s*"
                r"'active_color' => \$this->activeColor,\s*"
                r"'id' => 'bottom_nav',\s*"
                r"\];\s*"
                r"\}"
            )
            method_pattern_legacy = (
                r"protected function toNativeProps\(\): array\s*\{\s*"
                r"return \[\s*"
                r"'dark' => \$this->dark,\s*"
                r"'label_visibility' => \$this->labelVisibility,\s*"
                r"'id' => 'bottom_nav',\s*"
                r"\];\s*"
                r"\}"
            )

            text, updated = replace_regex_once_or_error(
                text,
                method_pattern_with_active_color,
                patched_method,
                "BottomNav toNativeProps (active_color)",
                already_contains=patched_method,
            )

            if not updated and patched_method not in text:
                text, updated = replace_regex_once_or_error(
                    text,
                    method_pattern_legacy,
                    patched_method,
                    "BottomNav toNativeProps (legacy)",
                    already_contains=patched_method,
                )

        changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-system-ui] patched: {path}")
        else:
            print(f"[native-system-ui] already ok: {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-system-ui] error: {error} ({path})")
        return False, True


def patch_edge_component(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-system-ui] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        needle = "        $target = &self::navigateToComponent($context);\n\n        // Update the placeholder with actual data\n"
        replacement = """        $target = &self::navigateToComponent($context);
        $children = $target['data']['children'] ?? [];

        $shouldSkip = false;
        if ($type === 'top_bar') {
            $title = $data['title'] ?? null;
            if ($title === null || $title === '') {
                $shouldSkip = true;
            }
        } elseif ($type === 'bottom_nav') {
            if (empty($data) && empty($children)) {
                $shouldSkip = true;
            }
        }

        if ($shouldSkip) {
            if (count($context) === 1) {
                unset(self::$components[$context[0]]);
                self::$components = array_values(self::$components);
            } else {
                $childIndex = array_pop($context);
                array_pop($context);
                array_pop($context);
                $parent = &self::navigateToComponent($context);
                if (isset($parent['data']['children'][$childIndex])) {
                    unset($parent['data']['children'][$childIndex]);
                    $parent['data']['children'] = array_values($parent['data']['children']);
                }
            }

            array_pop(self::$contextStack);
            return;
        }

        // Update the placeholder with actual data
"""

        if replacement in text:
            updated = False
        elif needle in text:
            text = text.replace(needle, replacement, 1)
            updated = True
        else:
            raise PatchError("pattern not found for Edge context skip logic")

        changed = changed or updated

        text, updated = replace_once_or_error(
            text,
            "        $target['data'] = array_merge($data, [\n            'children' => $target['data']['children'] ?? [],\n        ]);\n",
            "        $target['data'] = array_merge($data, [\n            'children' => $children,\n        ]);\n",
            "Edge children assignment",
            already_contains="            'children' => $children,",
        )
        changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-system-ui] patched: {path}")
        else:
            print(f"[native-system-ui] already ok: {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-system-ui] error: {error} ({path})")
        return False, True


for main_activity_path in [
    root_dir / "vendor/nativephp/mobile/resources/androidstudio/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt",
    root_dir / "nativephp/android/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt",
]:
    _, error = patch_main_activity(main_activity_path)
    if error:
        raise SystemExit(1)
PY
