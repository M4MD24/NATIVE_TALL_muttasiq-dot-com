#!/usr/bin/env bash
set -euo pipefail

./.scripts/support/prepare.sh
./.scripts/native/mobile/android/support/prepare.sh

bash ./.scripts/native/mobile/support/patches/jump-status-texts.sh

APP_NAME="Muttasiq" php artisan native:jump --android "$@"
