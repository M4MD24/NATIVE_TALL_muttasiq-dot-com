#!/usr/bin/env bash
set -euo pipefail

./.scripts/support/prepare.sh
./.scripts/native/mobile/ios/support/prepare.sh
./.scripts/native/mobile/support/patches/edge-components.sh
./.scripts/native/mobile/ios/patches/system-ui.sh
./.scripts/native/mobile/ios/patches/back-handler.sh

bash ./.scripts/native/mobile/support/patches/jump-status-texts.sh

APP_NAME="Muttasiq" php artisan native:jump --ios "$@"
