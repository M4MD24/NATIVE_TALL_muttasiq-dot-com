#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
project_name="$(basename "${root_dir}")"
container_project_root="/var/www/html/${project_name}"
run_clean_script="${root_dir}/.scripts/testing/support/run-clean.sh"
browser_tests_path="${BROWSER_TESTS_PATH:-tests/Feature/Browser}"

if [[ ! -x "${run_clean_script}" ]]; then
    echo "Missing executable script at ${run_clean_script}" >&2
    exit 1
fi

resolve_test_container() {
    if ! command -v docker >/dev/null 2>&1; then
        return 1
    fi

    local container_name="${TEST_CONTAINER:-${TESTING_CONTAINER:-${TEST_BROWSER_CONTAINER:-}}}"

    if [[ -n "${container_name}" ]]; then
        if docker ps --format '{{.Names}}' 2>/dev/null | grep -Fxq "${container_name}"; then
            printf '%s\n' "${container_name}"

            return 0
        fi

        return 1
    fi

    local container_lines
    container_lines="$(docker ps --format '{{.Names}} {{.Label "com.docker.compose.service"}} {{.Label "com.docker.compose.project"}}' 2>/dev/null || true)"

    if [[ -z "${container_lines}" ]]; then
        return 1
    fi

    container_name="$(awk '$2 == "app" && $3 == "lara-stacker" { print $1; exit }' <<<"${container_lines}")"

    if [[ -z "${container_name}" ]]; then
        container_name="$(awk '$2 == "app" { print $1; exit }' <<<"${container_lines}")"
    fi

    if [[ -z "${container_name}" ]]; then
        return 1
    fi

    printf '%s\n' "${container_name}"
}

run_browser_command() {
    if command -v timeout >/dev/null 2>&1; then
        "${run_clean_script}" timeout 5m php artisan test --compact "${browser_tests_path}" "$@"

        return
    fi

    if [[ -x /usr/bin/timeout ]]; then
        "${run_clean_script}" /usr/bin/timeout 5m php artisan test --compact "${browser_tests_path}" "$@"

        return
    fi

    "${run_clean_script}" php artisan test --compact "${browser_tests_path}" "$@"
}

run_local() (
    cd "${root_dir}"
    run_browser_command "$@"
)

run_in_container() {
    local container_name="$1"
    shift

    docker exec \
        -e "BROWSER_TESTS_PATH=${browser_tests_path}" \
        -w "${container_project_root}" \
        "${container_name}" \
        sh -lc '
            set -eu

            if command -v timeout >/dev/null 2>&1; then
                .scripts/testing/support/run-clean.sh timeout 5m php artisan test --parallel --processes=10 "${BROWSER_TESTS_PATH}" "$@"
                exit 0
            fi

            if [ -x /usr/bin/timeout ]; then
                .scripts/testing/support/run-clean.sh /usr/bin/timeout 5m php artisan test --parallel --processes=10 "${BROWSER_TESTS_PATH}" "$@"
                exit 0
            fi

            .scripts/testing/support/run-clean.sh php artisan test --parallel --processes=10 "${BROWSER_TESTS_PATH}" "$@"
        ' sh "$@"
}

container_has_playwright() {
    local container_name="$1"

    docker exec \
        -w "${container_project_root}" \
        "${container_name}" \
        sh -lc 'command -v node >/dev/null 2>&1 && node_modules/.bin/playwright --version >/dev/null 2>&1' >/dev/null 2>&1
}

if container_name="$(resolve_test_container)" && container_has_playwright "${container_name}"; then
    run_in_container "${container_name}" "$@"
    exit 0
fi

run_local "$@"
