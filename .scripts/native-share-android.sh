#!/usr/bin/env bash
set -euo pipefail

./.scripts/support/prepare.sh
./.scripts/native/mobile/android/support/prepare.sh
./.scripts/native/mobile/support/patches/edge-components.sh
./.scripts/native/mobile/android/patches/system-ui.sh
./.scripts/native/mobile/android/patches/back-handler.sh

bash ./.scripts/native/mobile/support/patches/jump-status-texts.sh

APP_NAME="Muttasiq" php artisan native:jump --android "$@"
