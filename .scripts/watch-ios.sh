#!/usr/bin/env bash
set -euo pipefail

watchman shutdown-server

./.scripts/support/prepare.sh
./.scripts/native/mobile/ios/support/prepare.sh

simulator_udid="$("./.scripts/native/mobile/ios/support/select-simulator.sh")"
echo "[native-watch:ios] using simulator ${simulator_udid}"
xcrun simctl shutdown "${simulator_udid}" >/dev/null 2>&1 || true

php artisan native:run ios "${simulator_udid}" --build=debug --watch
