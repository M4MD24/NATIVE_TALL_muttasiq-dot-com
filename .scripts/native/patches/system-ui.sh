#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"

python3 - "$root_dir" <<'PY'
import re
import sys
from pathlib import Path

root_dir = Path(sys.argv[1])


def replace_kotlin_function_body(text: str, func_name: str, new_body: str) -> tuple[str, bool]:
    pattern = re.compile(rf"(?m)^(\s*)(?:private\s+)?fun\s+{re.escape(func_name)}\s*\(")
    match = pattern.search(text)
    if not match:
        return text, False

    start = text.find("{", match.end())
    if start == -1:
        return text, False

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
        return text, False

    indent = match.group(1) + "    "
    new_body = "\n".join(
        [(indent + line if line.strip() else line) for line in new_body.splitlines()]
    )
    new_text = text[: start + 1] + "\n" + new_body + "\n" + match.group(1) + "}" + text[end + 1 :]

    return (new_text, new_text != text)

def insert_after(text: str, anchor: str, insert: str) -> tuple[str, bool]:
    if insert in text:
        return text, False
    if anchor not in text:
        return text, False
    return text.replace(anchor, f"{anchor}\n{insert}", 1), True

def insert_before(text: str, anchor: str, insert: str) -> tuple[str, bool]:
    if insert in text:
        return text, False
    if anchor not in text:
        return text, False
    return text.replace(anchor, f"{insert}\n{anchor}", 1), True


def patch_main_activity(path: Path) -> bool:
    if not path.exists():
        print(f"[native-system-ui] skip missing: {path}")
        return False

    text = path.read_text()
    changed = False

    new_body = """val windowInsetsController = WindowInsetsControllerCompat(window, window.decorView)

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
    "🎨 System bars style: auto (system ${if (isSystemDarkMode) "dark" else "light"} mode, requested=$statusBarStyle)"
)"""

    text, updated = replace_kotlin_function_body(text, "configureStatusBar", new_body)
    changed = changed or updated

    text = text.replace(
        "For edge-to-edge mode, system bars are transparent to allow content to draw behind them",
        "For edge-to-edge mode, system bars follow the system light/dark theme so content does not bleed through",
    )

    scrim_call = "            SystemBarsScrim()"
    if scrim_call not in text:
        text, updated = insert_before(
            text,
            "            // Splash overlay with fade animation (full screen, no insets)",
            scrim_call,
        )
        changed = changed or updated

    scrim_def = """    @Composable
    private fun SystemBarsScrim() {
        val systemInDarkMode = isSystemInDarkTheme()
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
        }
    }
"""
    if "private fun SystemBarsScrim()" not in text:
        text, updated = insert_before(
            text,
            "    /**\n     * Splash screen composable - shows custom image or fallback text\n     */",
            scrim_def,
        )
        changed = changed or updated
    else:
        text, updated = replace_kotlin_function_body(text, "SystemBarsScrim", scrim_def.split("{", 1)[1].rsplit("}", 1)[0])
        changed = changed or updated

    top_bar_block = re.compile(
        r"\n\s*// Check if native top bar is present - if so, set top inset to 0\n"
        r"\s*// The native top bar already handles status bar spacing\n"
        r"\s*val hasTopBar = NativeUIState\.topBarData\.value != null\n"
        r"\s*if \(hasTopBar\) \{\n"
        r"\s*topPx = 0\n"
        r"\s*Log\.d\(\"SafeArea\", \"Native top bar detected - setting top inset to 0\"\)\n"
        r"\s*\}\n",
        re.MULTILINE,
    )
    text, removed = top_bar_block.subn("\n", text)
    changed = changed or bool(removed)

    if "private var lastStableInsets: Insets? = null" not in text:
        text = text.replace(
            "    private var pendingInsets: Insets? = null\n",
            "    private var pendingInsets: Insets? = null\n    private var lastStableInsets: Insets? = null\n",
        )
        changed = True

    if "lastStableInsets = systemBars" not in text:
        text = text.replace(
            "            pendingInsets = systemBars\n",
            "            pendingInsets = systemBars\n            if (systemBars.top > 0 || systemBars.bottom > 0 || systemBars.left > 0 || systemBars.right > 0) {\n                lastStableInsets = systemBars\n            }\n",
        )
        changed = True

    text = text.replace(
        "            // Inject CSS custom properties into WebView if ready\n"
        "            if (::webViewManager.isInitialized) {\n"
        "                injectSafeAreaInsets(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)\n"
        "            }\n",
        "            // Safe area handled by Compose insets\n",
    )
    text = text.replace(
        "            // Inject CSS custom properties into WebView if ready\n"
        "            if (::webViewManager.isInitialized) {\n"
        "                val isZeroInsets = systemBars.top == 0 && systemBars.bottom == 0 && systemBars.left == 0 && systemBars.right == 0\n"
        "                val effectiveInsets = if (isZeroInsets) (lastStableInsets ?: systemBars) else systemBars\n"
        "                injectSafeAreaInsets(effectiveInsets.left, effectiveInsets.top, effectiveInsets.right, effectiveInsets.bottom)\n"
        "            }\n",
        "            // Safe area handled by Compose insets\n",
    )
    if "Safe area handled by Compose insets" in text:
        changed = True

    inject_body = '''Log.d(
    "SafeArea",
    "Safe area handled by Compose layout insets"
)'''

    text, updated = replace_kotlin_function_body(text, "injectSafeAreaInsets", inject_body)
    changed = changed or updated

    inject_to_webview_body = """Log.d(
    "SafeArea",
    "Safe area handled by Compose layout insets"
)"""
    text, updated = replace_kotlin_function_body(
        text,
        "injectSafeAreaInsetsToWebView",
        inject_to_webview_body,
    )
    changed = changed or updated

    replaced_text = text.replace(
        "            // Inject safe area insets BEFORE loading any URL to prevent content shift\n"
        "            pendingInsets?.let {\n"
        "                injectSafeAreaInsets(it.left, it.top, it.right, it.bottom)\n"
        "            }\n",
        "            // Inject safe area insets BEFORE loading any URL to prevent content shift\n"
        "            injectSafeAreaInsetsToWebView()\n",
    )
    if replaced_text != text:
        text = replaced_text
        changed = True

    text = text.replace(
        "                        topBar = {\n"
        "                            NativeTopBar(\n"
        "                                onMenuClick = {\n"
        "                                    Log.d(\"Navigation\", \"🍔 Menu button clicked - opening drawer\")\n"
        "                                },\n"
        "                                onNavigate = { url ->\n"
        "                                    Log.d(\"Navigation\", \"⚡ TopBar action navigation clicked\")\n"
        "                                    navigateWithInertia(url)\n"
        "                                }\n"
        "                            )\n"
        "                        },\n"
        "                        bottomBar = {\n"
        "                            BottomNavigationContent()\n"
        "                        },\n",
        "                        topBar = {\n"
        "                            if (NativeUIState.topBarData.value != null) {\n"
        "                                NativeTopBar(\n"
        "                                    onMenuClick = {\n"
        "                                        Log.d(\"Navigation\", \"🍔 Menu button clicked - opening drawer\")\n"
        "                                    },\n"
        "                                    onNavigate = { url ->\n"
        "                                        Log.d(\"Navigation\", \"⚡ TopBar action navigation clicked\")\n"
        "                                        navigateWithInertia(url)\n"
        "                                    }\n"
        "                                )\n"
        "                            }\n"
        "                        },\n"
        "                        bottomBar = {\n"
        "                            if (NativeUIState.bottomNavData.value != null) {\n"
        "                                BottomNavigationContent()\n"
        "                            }\n"
        "                        },\n",
    )

    text = text.replace(
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
    )

    if changed:
        path.write_text(text)
        print(f"[native-system-ui] patched: {path}")
    else:
        print(f"[native-system-ui] already ok: {path}")

    if "android.graphics.Color.TRANSPARENT" in text:
        print(f"[native-system-ui] warning: transparent system bars still present in {path}")

    return changed



def patch_top_bar_component(path: Path) -> bool:
    if not path.exists():
        print(f"[native-system-ui] skip missing: {path}")
        return False

    text = path.read_text()
    changed = False

    text, updated = re.subn(
        r"public bool \$showNavigationIcon = true,",
        "public ?bool $showNavigationIcon = null,",
        text,
        count=1,
    )
    changed = changed or bool(updated)

    text, updated = re.subn(
        r"fn \(\$value\) => \$value !== null && \$value !== false",
        "fn ($value) => $value !== null",
        text,
        count=1,
    )
    changed = changed or bool(updated)

    if changed:
        path.write_text(text)
        print(f"[native-system-ui] patched: {path}")
    else:
        print(f"[native-system-ui] already ok: {path}")

    return changed


def patch_bottom_nav_component(path: Path) -> bool:
    if not path.exists():
        print(f"[native-system-ui] skip missing: {path}")
        return False

    text = path.read_text()
    changed = False

    text, updated = re.subn(
        r"public string \$labelVisibility = 'labeled'",
        "public ?string $labelVisibility = null",
        text,
        count=1,
    )
    changed = changed or bool(updated)

    text, updated = re.subn(
        r"return \[\s*'dark' => \$this->dark,\s*'label_visibility' => \$this->labelVisibility,\s*'id' => 'bottom_nav',\s*\];",
        "return array_filter([\n            'dark' => $this->dark,\n            'label_visibility' => $this->labelVisibility,\n        ], fn ($value) => $value !== null);",
        text,
        count=1,
    )
    changed = changed or bool(updated)

    if changed:
        path.write_text(text)
        print(f"[native-system-ui] patched: {path}")
    else:
        print(f"[native-system-ui] already ok: {path}")

    return changed


def patch_edge_component(path: Path) -> bool:
    if not path.exists():
        print(f"[native-system-ui] skip missing: {path}")
        return False

    text = path.read_text()
    changed = False

    needle = "        $target = &self::navigateToComponent($context);\n\n        // Update the placeholder with actual data\n"
    if needle in text:
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
        text = text.replace(needle, replacement)
        changed = True

    replaced_text = text.replace(
        "        $target['data'] = array_merge($data, [\n            'children' => $target['data']['children'] ?? [],\n        ]);\n",
        "        $target['data'] = array_merge($data, [\n            'children' => $children,\n        ]);\n",
    )
    if replaced_text != text:
        text = replaced_text
        changed = True

    if changed:
        path.write_text(text)
        print(f"[native-system-ui] patched: {path}")
    else:
        print(f"[native-system-ui] already ok: {path}")

    return changed


paths = [
    root_dir / "vendor/nativephp/mobile/resources/androidstudio/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt",
    root_dir / "nativephp/android/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt",
]

for path in paths:
    patch_main_activity(path)

patch_top_bar_component(
    root_dir / "vendor/nativephp/mobile/src/Edge/Components/Navigation/TopBar.php"
)
patch_bottom_nav_component(
    root_dir / "vendor/nativephp/mobile/src/Edge/Components/Navigation/BottomNav.php"
)
patch_edge_component(
    root_dir / "vendor/nativephp/mobile/src/Edge/Edge.php"
)
PY
