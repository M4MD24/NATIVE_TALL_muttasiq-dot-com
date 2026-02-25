#!/usr/bin/env bash
set -euo pipefail

./.scripts/support/prepare.sh
./.scripts/native/mobile/android/support/prepare.sh
./.scripts/native/mobile/support/patches/edge-components.sh
./.scripts/native/mobile/android/patches/system-ui.sh
./.scripts/native/mobile/android/patches/back-handler.sh

php artisan native:run android
