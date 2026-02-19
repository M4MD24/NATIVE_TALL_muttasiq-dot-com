#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../../" && pwd)"

python3 - "${root_dir}" <<'PY'
import sys
from pathlib import Path

root_dir = Path(sys.argv[1])


def patch_main_activity(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-back-handler] skip missing: {path}")
        return False, False

    text = path.read_text()

    if "window.__nativeBackAction" in text:
        print(f"[native-back-handler] already ok: {path}")
        return False, False

    original_block = (
        "        onBackPressedDispatcher.addCallback(this) {\n"
        "            if (webView.canGoBack()) {\n"
        "                webView.goBack()\n"
        "            } else {\n"
        "                finish()\n"
        "            }\n"
        "        }\n"
    )

    new_block = (
        "        onBackPressedDispatcher.addCallback(this) {\n"
        "            val js =\n"
        "                \"(function() { try { return window.__nativeBackAction && window.__nativeBackAction(); } \" +\n"
        "                    \"catch (e) { return false; } })();\"\n"
        "\n"
        "            webView.evaluateJavascript(js) { value ->\n"
        "                val handled = value?.trim()?.trim('\\\"') == \"true\"\n"
        "                if (handled) {\n"
        "                    return@evaluateJavascript\n"
        "                }\n"
        "\n"
        "                if (webView.canGoBack()) {\n"
        "                    webView.goBack()\n"
        "                } else {\n"
        "                    finish()\n"
        "                }\n"
        "            }\n"
        "        }\n"
    )

    if original_block not in text:
        print(f"[native-back-handler] error: expected onBackPressed block not found in {path}")
        return False, True

    path.write_text(text.replace(original_block, new_block, 1))
    print(f"[native-back-handler] patched: {path}")
    return True, False


paths = [
    root_dir
    / "vendor/nativephp/mobile/resources/androidstudio/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt",
    root_dir / "nativephp/android/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt",
]

had_error = False
for path in paths:
    _, error = patch_main_activity(path)
    had_error = had_error or error

if had_error:
    raise SystemExit(1)
PY
