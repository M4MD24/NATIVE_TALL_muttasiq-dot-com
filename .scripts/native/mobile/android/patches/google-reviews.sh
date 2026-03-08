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
            if text[i : i + 3] == '"""':
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
            if text[i : i + 3] == '"""':
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


def set_kotlin_function_body(text: str, func_name: str, new_body: str) -> tuple[str, bool]:
    indent, start, end = locate_kotlin_function(text, func_name)
    body_indent = indent + "    "
    indented_body = "\n".join(
        [(body_indent + line if line.strip() else line) for line in new_body.splitlines()]
    )
    replacement = text[: start + 1] + "\n" + indented_body + "\n" + indent + "}" + text[end + 1 :]

    if replacement == text:
        return text, False

    return replacement, True


def insert_import(text: str, import_line: str, after: str) -> tuple[str, bool]:
    if import_line in text:
        return text, False

    if after not in text:
        raise PatchError(f"import anchor not found for {import_line}")

    return text.replace(after, f"{after}\n{import_line}", 1), True


def remove_line(text: str, line: str) -> tuple[str, bool]:
    if line not in text:
        return text, False

    return text.replace(line, "", 1), True


def replace_once_or_error(text: str, old: str, new: str, label: str, already_contains: str | None = None) -> tuple[str, bool]:
    if old in text:
        return text.replace(old, new, 1), True

    if already_contains is not None and already_contains in text:
        return text, False

    if new in text:
        return text, False

    raise PatchError(f"pattern not found for {label}")


def patch_main_activity(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-google-reviews] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        text, updated = insert_import(
            text,
            "import androidx.activity.enableEdgeToEdge",
            "import androidx.activity.addCallback",
        )
        changed = changed or updated

        edge_to_edge_old = (
            "        // Android 15 edge-to-edge compatibility fix\n"
            "        WindowCompat.setDecorFitsSystemWindows(window, false)\n"
        )
        edge_to_edge_new = (
            "        // Android 15 edge-to-edge compatibility (and pre-35 backport)\n"
            "        enableEdgeToEdge()\n"
        )
        text, updated = replace_once_or_error(
            text,
            edge_to_edge_old,
            edge_to_edge_new,
            "edge-to-edge setup",
            already_contains="enableEdgeToEdge()",
        )
        changed = changed or updated

        if "WindowCompat.setDecorFitsSystemWindows" not in text:
            text, updated = remove_line(text, "import androidx.core.view.WindowCompat\n")
            changed = changed or updated

        text, updated = remove_line(text, "    @Suppress(\"DEPRECATION\")\n")
        changed = changed or updated

        text, updated = replace_once_or_error(
            text,
            "     * For edge-to-edge mode, system bars follow the system light/dark theme so content does not bleed through\n",
            "     * For edge-to-edge mode, we rely on the SystemBarsScrim for background and only set icon contrast\n",
            "configureStatusBar docstring",
            already_contains="SystemBarsScrim",
        )
        changed = changed or updated

        configure_status_bar_body = """val windowInsetsController = WindowInsetsControllerCompat(window, window.decorView)

val isSystemDarkMode = (resources.configuration.uiMode and
    Configuration.UI_MODE_NIGHT_MASK) == Configuration.UI_MODE_NIGHT_YES

// Match system light/dark for icon contrast
windowInsetsController.isAppearanceLightStatusBars = !isSystemDarkMode
windowInsetsController.isAppearanceLightNavigationBars = !isSystemDarkMode

Log.d(
    \"StatusBar\",
    \"System bars style: auto (system ${if (isSystemDarkMode) \"dark\" else \"light\"} mode, requested=$statusBarStyle)\"
)"""

        text, updated = set_kotlin_function_body(text, "configureStatusBar", configure_status_bar_body)
        changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-google-reviews] patched: {path}")
        else:
            print(f"[native-google-reviews] already ok: {path}")

        if "statusBarColor" in text or "navigationBarColor" in text:
            print(f"[native-google-reviews] warning: status/navigation bar color setters still present in {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-google-reviews] error: {error} ({path})")
        return False, True


for main_activity_path in [
    root_dir / "vendor/nativephp/mobile/resources/androidstudio/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt",
    root_dir / "nativephp/android/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt",
]:
    _, error = patch_main_activity(main_activity_path)
    if error:
        raise SystemExit(1)
PY
