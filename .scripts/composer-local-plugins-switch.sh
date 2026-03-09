#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
package_name="${1:-goodm4ven/nativephp-muttasiq-patches}"
package_path="${2:-${HOME}/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq-patches}"
repository_key="${package_name##*/}"

cd "${root_dir}"

current_repository="$(composer config "repositories.${repository_key}" 2>/dev/null || true)"

if [[ -n "${current_repository}" ]] && grep -Fq '"type":"path"' <<<"${current_repository}"; then
    composer config --unset "repositories.${repository_key}"
    composer update "${package_name}" --with-all-dependencies
    echo "[composer-local-plugins-switch] disabled local path repository for ${package_name}"
    exit 0
fi

if [[ ! -d "${package_path}" ]]; then
    echo "[composer-local-plugins-switch] missing package path: ${package_path}" >&2
    exit 1
fi

composer config "repositories.${repository_key}" path "${package_path}"
composer update "${package_name}" --with-all-dependencies
echo "[composer-local-plugins-switch] enabled local path repository for ${package_name}"
