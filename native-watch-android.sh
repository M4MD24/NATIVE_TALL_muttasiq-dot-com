#!/usr/bin/env bash
set -euo pipefail

watchman shutdown-server

./.scripts/support/prepare.sh
./.scripts/native/android/support/prepare.sh
./.scripts/native/android/patches/system-ui.sh
./.scripts/native/android/patches/back-handler.sh

php artisan native:run android --watch
