#!/usr/bin/env bash
set -euo pipefail

watchman shutdown-server

./.scripts/support/prepare.sh
./.scripts/native/mobile/android/support/prepare.sh

php artisan native:run android --watch
