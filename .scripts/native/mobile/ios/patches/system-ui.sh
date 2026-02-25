#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../../" && pwd)"

python3 - "${root_dir}" <<'PY'
import sys
from pathlib import Path

root_dir = Path(sys.argv[1])

paths = [
    root_dir / "vendor/nativephp/mobile/resources/xcode/NativePHP/ContentView.swift",
    root_dir / "nativephp/ios/NativePHP/ContentView.swift",
]

found_any = False
for path in paths:
    if not path.exists():
        print(f"[native-ios-system-ui] skip missing: {path}")
        continue

    found_any = True
    text = path.read_text()

    has_top_safe_area = ".safeAreaInset(edge: .top" in text
    has_bottom_safe_area = ".safeAreaInset(edge: .bottom" in text
    has_native_top_bar = "NativeTopBar(" in text
    has_native_bottom_nav = "NativeBottomNavigation(" in text

    if has_top_safe_area and has_bottom_safe_area and has_native_top_bar and has_native_bottom_nav:
        print(f"[native-ios-system-ui] no patch required (upstream layout handling present): {path}")
    else:
        print(f"[native-ios-system-ui] warning: upstream UI structure changed, re-check iOS system UI behavior: {path}")

if not found_any:
    print("[native-ios-system-ui] warning: no iOS ContentView.swift file found")
PY
