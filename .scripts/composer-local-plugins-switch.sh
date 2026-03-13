#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
action="toggle"
if [[ "${1:-}" == "on" || "${1:-}" == "off" || "${1:-}" == "toggle" ]]; then
    action="$1"
    shift
fi

package_name="${1:-goodm4ven/nativephp-muttasiq-patches}"
package_path="${2:-${HOME}/Code/LaravelPackages/NATIVE_PLUGIN_muttasiq-patches}"
forced_version_input="${3:-}"
repository_key="${package_name##*/}"

run_package_update() {
    if [[ "${COMPOSER_LOCAL_PLUGINS_SWITCH_SKIP_UPDATE:-0}" == "1" ]]; then
        echo "[composer-local-plugins-switch] skipped composer update via COMPOSER_LOCAL_PLUGINS_SWITCH_SKIP_UPDATE=1"
        return 0
    fi

    composer update "${package_name}" --with-dependencies
}

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

find_matching_repository_keys() {
    local target_directory_name
    target_directory_name="$(basename "${package_path}")"

    php -r '
        $composer = json_decode(file_get_contents("composer.json"), true);
        $repositories = $composer["repositories"] ?? [];
        $packageName = $argv[1];
        $repositoryKey = $argv[2];
        $targetDirectoryName = $argv[3];

        foreach ($repositories as $key => $repository) {
            if (! is_array($repository)) {
                continue;
            }

            if (($repository["type"] ?? null) !== "path") {
                continue;
            }

            $versions = $repository["options"]["versions"] ?? [];
            $url = (string) ($repository["url"] ?? "");
            $urlDirectoryName = $url !== "" ? basename(rtrim($url, "/\\")) : "";

            $matchesByKey = (string) $key === $repositoryKey;
            $matchesByName = ($repository["name"] ?? null) === $repositoryKey;
            $matchesByVersion = is_array($versions) && array_key_exists($packageName, $versions);
            $matchesByDirectory = $urlDirectoryName !== "" && $urlDirectoryName === $targetDirectoryName;

            if ($matchesByKey || $matchesByName || $matchesByVersion || $matchesByDirectory) {
                echo $key . PHP_EOL;
            }
        }
    ' "${package_name}" "${repository_key}" "${target_directory_name}"
}

remove_matching_repositories() {
    local matching_repository_keys="$1"

    if [[ -z "${matching_repository_keys}" ]]; then
        return 0
    fi

    while IFS= read -r matching_repository_key; do
        if [[ -z "${matching_repository_key}" ]]; then
            continue
        fi
        composer config --unset "repositories.${matching_repository_key}"
    done <<<"${matching_repository_keys}"
}

enable_local_repository() {
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
}

cd "${root_dir}"

package_constraint="$(php -r '$composer = json_decode(file_get_contents("composer.json"), true); $pkg = $argv[1]; echo $composer["require"][$pkg] ?? $composer["require-dev"][$pkg] ?? "";' "${package_name}")"
local_forced_version="${forced_version_input:-$(detect_local_forced_version "${package_constraint}")}"
matching_repository_keys="$(find_matching_repository_keys)"

if [[ "${action}" == "off" ]]; then
    if [[ -z "${matching_repository_keys}" ]]; then
        echo "[composer-local-plugins-switch] already disabled for ${package_name}"
        exit 0
    fi

    remove_matching_repositories "${matching_repository_keys}"
    run_package_update
    echo "[composer-local-plugins-switch] disabled local path repository for ${package_name}"
    exit 0
fi

if [[ "${action}" == "toggle" && -n "${matching_repository_keys}" ]]; then
    remove_matching_repositories "${matching_repository_keys}"
    run_package_update
    echo "[composer-local-plugins-switch] disabled local path repository for ${package_name}"
    exit 0
fi

remove_matching_repositories "${matching_repository_keys}"
enable_local_repository
run_package_update
echo "[composer-local-plugins-switch] enabled local path repository for ${package_name}"
