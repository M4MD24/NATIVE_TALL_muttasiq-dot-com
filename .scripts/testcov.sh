#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
project_name="$(basename "${root_dir}")"
container_project_root="/var/www/html/${project_name}"
plugin_cache_relative_path="vendor/pest-plugins.json"

run_pest_coverage() {
    .scripts/run-tests-clean.sh \
        vendor/bin/pest \
        tests/Unit \
        tests/Feature/App \
        --coverage \
        --exclude-group=browser \
        "$@"
}

run_testcov_in_current_shell() (
    set -euo pipefail
    cd "${root_dir}"

    plugin_cache_file="${plugin_cache_relative_path}"
    backup_file=""

    if [[ -f "${plugin_cache_file}" ]]; then
        backup_file="${plugin_cache_file}.testcov.bak.$$"
        cp "${plugin_cache_file}" "${backup_file}"
        trap 'if [[ -n "${backup_file}" && -f "${backup_file}" ]]; then mv "${backup_file}" "${plugin_cache_file}"; fi' EXIT INT TERM
        sed -i '/"Pest\\\\Browser\\\\Plugin"/d' "${plugin_cache_file}"
    fi

    PEST_ENABLE_BROWSER_PLUGIN=0 XDEBUG_MODE=coverage run_pest_coverage "$@"
)

if ! command -v docker >/dev/null 2>&1; then
    run_testcov_in_current_shell "$@"
    exit 0
fi

container_name="${TESTCOV_CONTAINER:-}"

if [[ -z "${container_name}" ]]; then
    container_lines="$(docker ps --format '{{.Names}} {{.Label "com.docker.compose.service"}} {{.Label "com.docker.compose.project"}}' 2>/dev/null || true)"

    if [[ -n "${container_lines}" ]]; then
        container_name="$(awk '$2 == "app" && $3 == "lara-stacker" { print $1; exit }' <<<"${container_lines}")"

        if [[ -z "${container_name}" ]]; then
            container_name="$(awk '$2 == "app" { print $1; exit }' <<<"${container_lines}")"
        fi
    fi
fi

if [[ -z "${container_name}" ]]; then
    run_testcov_in_current_shell "$@"
    exit 0
fi

docker exec \
    -e PEST_ENABLE_BROWSER_PLUGIN=0 \
    -e XDEBUG_MODE=coverage \
    -w "${container_project_root}" \
    "${container_name}" \
    sh -lc '
        set -eu

        plugin_cache_file="'"${plugin_cache_relative_path}"'"
        backup_file=""

        if [ -f "${plugin_cache_file}" ]; then
            backup_file="${plugin_cache_file}.testcov.bak.$$"
            cp "${plugin_cache_file}" "${backup_file}"
            trap '"'"'if [ -n "${backup_file}" ] && [ -f "${backup_file}" ]; then mv "${backup_file}" "${plugin_cache_file}"; fi'"'"' EXIT INT TERM
            sed -i '"'"'/"Pest\\\\Browser\\\\Plugin"/d'"'"' "${plugin_cache_file}"
        fi

        .scripts/run-tests-clean.sh \
            vendor/bin/pest \
            tests/Unit \
            tests/Feature/App \
            --coverage \
            --exclude-group=browser \
            "$@"
    ' sh "$@"
