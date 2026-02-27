#!/usr/bin/env bash
set -euo pipefail

./.scripts/support/prepare.sh
./.scripts/web/support/prepare.sh

composer dev
