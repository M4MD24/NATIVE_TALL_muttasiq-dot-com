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


def insert_before_or_error(
    text: str,
    anchor: str,
    insert: str,
    label: str,
    already_contains: str | None = None,
) -> tuple[str, bool]:
    if already_contains is not None and already_contains in text:
        return text, False

    if insert in text:
        return text, False

    if anchor not in text:
        raise PatchError(f"anchor not found for {label}")

    return text.replace(anchor, f"{insert}\n{anchor}", 1), True


def locate_swift_function(text: str, func_name: str):
    pattern = re.compile(
        rf"(?m)^([ \t]*)(?:(?:@\w+\s+)|(?:private|public|internal|fileprivate|override|final|mutating|nonmutating|class|static)\s+)*func\s+{re.escape(func_name)}\s*\("
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


def set_swift_function_body(text: str, func_name: str, new_body: str) -> tuple[str, bool]:
    indent, start, end = locate_swift_function(text, func_name)
    body_indent = indent + "    "
    indented_body = "\n".join(
        [(body_indent + line if line.strip() else line) for line in new_body.splitlines()]
    )
    replacement = text[: start + 1] + "\n" + indented_body + "\n" + indent + "}" + text[end + 1 :]

    if replacement == text:
        return text, False

    return replacement, True


def patch_content_view(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-ios-back] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        if "import WebKit\nimport UIKit\nimport UIKit\n" in text:
            text = text.replace("import WebKit\nimport UIKit\nimport UIKit\n", "import WebKit\nimport UIKit\n", 1)
            changed = True

        if "import UIKit\n" not in text:
            text, updated = replace_once_or_error(
                text,
                "import WebKit\n",
                "import WebKit\nimport UIKit\n",
                "UIKit import",
            )
            changed = changed or updated

        text, updated = replace_once_or_error(
            text,
            "    class Coordinator: NSObject, WKNavigationDelegate {",
            "    class Coordinator: NSObject, WKNavigationDelegate, UIGestureRecognizerDelegate {",
            "Coordinator gesture delegate conformance",
            already_contains="UIGestureRecognizerDelegate",
        )
        changed = changed or updated

        back_handler_methods = """        @objc func handleBackEdgeGesture(_ gesture: UIScreenEdgePanGestureRecognizer) {
            guard gesture.state == .ended else {
                return
            }

            let js = "(function() { try { return !!(window.__nativeBackAction && window.__nativeBackAction()); } catch (e) { return false; } })();"

            webView?.evaluateJavaScript(js) { [weak self] value, _ in
                let handled = (value as? Bool) == true

                if handled {
                    return
                }

                if self?.webView?.canGoBack == true {
                    self?.webView?.goBack()
                }
            }
        }

        func gestureRecognizerShouldBegin(_ gestureRecognizer: UIGestureRecognizer) -> Bool {
            guard let pan = gestureRecognizer as? UIScreenEdgePanGestureRecognizer,
                  let view = pan.view else {
                return true
            }

            let velocity = pan.velocity(in: view)
            return abs(velocity.x) >= abs(velocity.y) && velocity.x > 0
        }

        func gestureRecognizer(
            _ gestureRecognizer: UIGestureRecognizer,
            shouldRecognizeSimultaneouslyWith otherGestureRecognizer: UIGestureRecognizer
        ) -> Bool {
            return true
        }

"""

        text, updated = insert_before_or_error(
            text,
            "        @objc func reloadWebView()",
            back_handler_methods,
            "back handler methods",
            already_contains="        @objc func handleBackEdgeGesture(_ gesture: UIScreenEdgePanGestureRecognizer)",
        )
        changed = changed or updated

        legacy_handled_pattern = r"let handled:\s*Bool\s*=\s*\{[\s\S]*?\}\(\)"
        updated_text, count = re.subn(
            legacy_handled_pattern,
            "let handled = (value as? Bool) == true",
            text,
            count=1,
            flags=re.MULTILINE,
        )
        if count:
            text = updated_text
            changed = True

        swipe_support_body = """webView.navigationDelegate = context.coordinator
webView.allowsBackForwardNavigationGestures = false

let backGestureName = "NativePHPBackEdgeGesture"
let hasBackGesture = webView.gestureRecognizers?.contains(where: { $0.name == backGestureName }) == true

if !hasBackGesture {
    let edgePan = UIScreenEdgePanGestureRecognizer(
        target: context.coordinator,
        action: #selector(Coordinator.handleBackEdgeGesture(_:))
    )
    edgePan.name = backGestureName
    edgePan.edges = .left
    edgePan.delegate = context.coordinator
    webView.addGestureRecognizer(edgePan)
}"""
        text, updated = set_swift_function_body(text, "addSwipeGestureSupport", swipe_support_body)
        changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-ios-back] patched: {path}")
        else:
            print(f"[native-ios-back] already ok: {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-ios-back] error: {error} ({path})")
        return False, True


paths = [
    root_dir / "vendor/nativephp/mobile/resources/xcode/NativePHP/ContentView.swift",
    root_dir / "nativephp/ios/NativePHP/ContentView.swift",
]

had_error = False
for path in paths:
    _, error = patch_content_view(path)
    had_error = had_error or error

if had_error:
    raise SystemExit(1)
PY
