#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../../" && pwd)"

python3 - "${root_dir}" <<'PY'
import sys
from pathlib import Path

root_dir = Path(sys.argv[1])


class PatchError(RuntimeError):
    pass


def insert_import(text: str, import_line: str, after: str) -> tuple[str, bool]:
    if import_line in text:
        return text, False

    if after not in text:
        raise PatchError(f"import anchor not found for {import_line}")

    return text.replace(after, f"{after}\n{import_line}", 1), True


def insert_after_or_error(text: str, anchor: str, insert: str, label: str) -> tuple[str, bool]:
    if insert in text:
        return text, False

    if anchor not in text:
        raise PatchError(f"anchor not found for {label}")

    return text.replace(anchor, f"{anchor}\n\n{insert}", 1), True


def insert_after_should_intercept_block(text: str, insert: str, label: str) -> tuple[str, bool]:
    if insert in text:
        return text, False

    needle = "override fun shouldInterceptRequest"
    start = text.find(needle)
    if start == -1:
        raise PatchError(f"anchor not found for {label}")

    block = text[start:]
    url_line = "val url = request.url.toString()"
    method_line = "val method = request.method"

    url_index = block.find(url_line)
    if url_index == -1:
        raise PatchError(f"anchor not found for {label}")

    method_index = block.find(method_line, url_index)
    if method_index == -1:
        raise PatchError(f"anchor not found for {label}")

    line_start = block.rfind("\n", 0, method_index) + 1
    line_end = block.find("\n", method_index)
    if line_end == -1:
        line_end = method_index + len(method_line)

    indent = block[line_start:method_index]
    insert_block = "\n\n" + "\n".join(
        [(indent + line if line.strip() else line) for line in insert.splitlines()]
    )

    new_block = block[:line_end] + insert_block + block[line_end:]
    updated = text[:start] + new_block
    return updated, True


def patch_webview_manager(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-livewire-guard] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        text, updated = insert_import(
            text,
            "import com.nativephp.mobile.bridge.LaravelEnvironment",
            "import com.nativephp.mobile.bridge.PHPBridge",
        )
        changed = changed or updated

        anchor = "                val url = request.url.toString()\n                val method = request.method"
        guard = (
            "                val livewirePath = request.url.path ?: \"\"\n"
            "                if (request.isForMainFrame && livewirePath.startsWith(\"/livewire\") && livewirePath.endsWith(\"/update\")) {\n"
            "                    Log.w(TAG, \"🚫 Blocking main-frame Livewire update navigation: $url\")\n"
            "                    val target = LaravelEnvironment.getStartURL(context)\n"
            "                    view.loadUrl(\"http://127.0.0.1$target\")\n"
            "                    return true\n"
            "                }"
        )

        prior_guard = (
            "                if (request.isForMainFrame && request.url.path == \"/livewire/update\") {\n"
            "                    Log.w(TAG, \"🚫 Blocking main-frame Livewire update navigation: $url\")\n"
            "                    val target = LaravelEnvironment.getStartURL(context)\n"
            "                    view.loadUrl(\"http://127.0.0.1$target\")\n"
            "                    return true\n"
            "                }"
        )

        if guard in text:
            updated = False
        elif prior_guard in text:
            text = text.replace(prior_guard, guard, 1)
            updated = True
        else:
            text, updated = insert_after_or_error(text, anchor, guard, "Livewire main-frame guard")
        changed = changed or updated

        intercept_guard = (
            "val livewirePath = request.url.path ?: \"\"\n"
            "if (request.isForMainFrame && livewirePath.startsWith(\"/livewire\") && livewirePath.endsWith(\"/update\")) {\n"
            "    Log.w(TAG, \"🚫 Blocking main-frame Livewire update request: $url\")\n"
            "    val target = LaravelEnvironment.getStartURL(context)\n"
            "    val html = \"\"\"\n"
            "        <!doctype html>\n"
            "        <html><head>\n"
            "            <meta http-equiv=\\\"refresh\\\" content=\\\"0;url=http://127.0.0.1$target\\\">\n"
            "        </head><body></body></html>\n"
            "    \"\"\".trimIndent()\n"
            "    return WebResourceResponse(\"text/html\", \"UTF-8\", html.byteInputStream())\n"
            "}"
        )

        prior_intercept_guard = (
            "if (request.isForMainFrame && request.url.path == \"/livewire/update\") {\n"
            "    Log.w(TAG, \"🚫 Blocking main-frame Livewire update request: $url\")\n"
            "    val target = LaravelEnvironment.getStartURL(context)\n"
            "    val html = \"\"\"\n"
            "        <!doctype html>\n"
            "        <html><head>\n"
            "            <meta http-equiv=\\\"refresh\\\" content=\\\"0;url=http://127.0.0.1$target\\\">\n"
            "        </head><body></body></html>\n"
            "    \"\"\".trimIndent()\n"
            "    return WebResourceResponse(\"text/html\", \"UTF-8\", html.byteInputStream())\n"
            "}"
        )

        if intercept_guard in text:
            updated = False
        elif prior_intercept_guard in text:
            text = text.replace(prior_intercept_guard, intercept_guard, 1)
            updated = True
        else:
            text, updated = insert_after_should_intercept_block(
                text,
                intercept_guard,
                "Livewire main-frame intercept guard",
            )
        changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-livewire-guard] patched: {path}")
        else:
            print(f"[native-livewire-guard] already ok: {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-livewire-guard] error: {error} ({path})")
        return False, True


for webview_path in [
    root_dir / "vendor/nativephp/mobile/resources/androidstudio/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt",
    root_dir / "nativephp/android/app/src/main/java/com/nativephp/mobile/network/WebViewManager.kt",
]:
    _, error = patch_webview_manager(webview_path)
    if error:
        raise SystemExit(1)
PY
