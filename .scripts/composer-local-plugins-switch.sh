#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
package_name="${1:-goodm4ven/nativephp-muttasiq-patches}"
package_path="${2:-${HOME}/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq-patches}"
repository_key="${package_name##*/}"

detect_local_forced_version() {
    local constraint="$1"

    if [[ "${constraint}" =~ ([0-9]+)\.([0-9]+)\.([0-9]+) ]]; then
        echo "${BASH_REMATCH[1]}.${BASH_REMATCH[2]}.999999"
        return 0
    fi

    if [[ "${constraint}" =~ ([0-9]+)\.([0-9]+) ]]; then
        echo "${BASH_REMATCH[1]}.${BASH_REMATCH[2]}.999999"
        return 0
    fi

    if [[ "${constraint}" =~ ([0-9]+) ]]; then
        echo "${BASH_REMATCH[1]}.999999.999999"
        return 0
    fi

    echo "1.0.999999"
}

cd "${root_dir}"

package_constraint="$(php -r '$composer = json_decode(file_get_contents("composer.json"), true); $pkg = $argv[1]; echo $composer["require"][$pkg] ?? $composer["require-dev"][$pkg] ?? "";' "${package_name}")"
local_forced_version="${3:-$(detect_local_forced_version "${package_constraint}")}"

current_repository="$(composer config "repositories.${repository_key}" 2>/dev/null || true)"

if [[ -n "${current_repository}" ]] && grep -Fq '"type":"path"' <<<"${current_repository}"; then
    composer config --unset "repositories.${repository_key}"
    composer update "${package_name}" --with-dependencies
    echo "[composer-local-plugins-switch] disabled local path repository for ${package_name}"
    exit 0
fi

if [[ ! -d "${package_path}" ]]; then
    echo "[composer-local-plugins-switch] missing package path: ${package_path}" >&2
    exit 1
fi

composer config "repositories.${repository_key}" --json "$(cat <<JSON
{
  "type": "path",
  "url": "${package_path}",
  "options": {
    "symlink": true,
    "versions": {
      "${package_name}": "${local_forced_version}"
    }
  }
}
JSON
)"
composer update "${package_name}" --with-dependencies
echo "[composer-local-plugins-switch] enabled local path repository for ${package_name}"
