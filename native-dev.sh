#!/usr/bin/env bash
set -euo pipefail

./.scripts/prepare.sh

composer dev
