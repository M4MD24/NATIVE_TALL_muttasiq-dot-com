#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../../" && pwd)"

python3 - "${root_dir}" <<'PY'
from pathlib import Path
import sys

root_dir = Path(sys.argv[1])
target = root_dir / "vendor/nativephp/mobile/resources/jump/native/native.php"

if not target.exists():
    raise SystemExit(f"[native-jump] missing file: {target}")

text = target.read_text()

patched_snippet = """        $code = $response->getStatusCode();
        $status = \\Symfony\\Component\\HttpFoundation\\Response::$statusTexts[$code]
            ?? ($code === 419 ? 'Page Expired' : 'Unknown Status');
        echo "HTTP/1.1 {$code} {$status}\\r\\n";
"""

legacy_snippet = """        $code = $response->getStatusCode();
        $status = \\Symfony\\Component\\HttpFoundation\\Response::$statusTexts[$code];
        echo "HTTP/1.1 {$code} {$status}\\r\\n";
"""

if patched_snippet in text:
    print(f"[native-jump] already patched: {target}")
    raise SystemExit(0)

if legacy_snippet not in text:
    raise SystemExit(f"[native-jump] expected snippet not found in: {target}")

updated = text.replace(legacy_snippet, patched_snippet, 1)
target.write_text(updated)
print(f"[native-jump] patched: {target}")
PY
