#!/usr/bin/env bash
set -euo pipefail

watchman shutdown-server

./.scripts/support/prepare.sh
./.scripts/native/mobile/ios/support/prepare.sh
./.scripts/native/mobile/support/patches/edge-components.sh
./.scripts/native/mobile/ios/patches/system-ui.sh
./.scripts/native/mobile/ios/patches/back-handler.sh

php artisan native:run ios --watch
