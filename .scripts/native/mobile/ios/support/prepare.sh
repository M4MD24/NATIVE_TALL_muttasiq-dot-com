#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../../" && pwd)"

if [[ "$(uname -s)" != "Darwin" ]]; then
    echo "[native-prepare:ios] iOS prepare requires macOS (Darwin)." >&2
    exit 1
fi

source "${root_dir}/.scripts/native/mobile/support/support/prepare-platform.sh"

native_prepare_platform_install \
    "ios" \
    "nativephp/ios/NativePHP/ContentView.swift"

(
    cd "${root_dir}"
    npm run build -- --mode=ios
)
