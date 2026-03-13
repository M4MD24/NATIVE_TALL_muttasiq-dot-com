#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
script_name="$(basename "${BASH_SOURCE[0]}")"
preflight_script="${root_dir}/.scripts/testing/support/preflight.sh"

read_lines_into_array() {
    local __target_name="$1"
    local __line=""
    eval "${__target_name}=()"

    while IFS= read -r __line; do
        eval "${__target_name}+=(\"\${__line}\")"
    done
}

if [[ ! -x "${preflight_script}" ]]; then
    echo "Missing executable preflight script at ${preflight_script}" >&2
    exit 1
fi

if [[ "$#" -eq 0 ]]; then
    echo "Usage: .scripts/testing/support/run-clean.sh <command> [args...]" >&2
    exit 64
fi

child_pid=""
detect_runtime_mode() {
    if [[ -f /.dockerenv ]]; then
        printf '%s\n' "docker"

        return
    fi

    if [[ -r /proc/1/cgroup ]] && grep -Eq '(docker|containerd|kubepods)' /proc/1/cgroup; then
        printf '%s\n' "docker"

        return
    fi

    printf '%s\n' "local"
}

print_runtime_indicator() {
    local mode
    mode="$(detect_runtime_mode)"
    echo "[testing:${script_name}] mode=${mode} command=$*" >&2
}

collect_descendant_pids() {
    local pid="$1"
    local children=()

    read_lines_into_array children < <(pgrep -P "${pid}" 2>/dev/null || true)

    if [[ "${#children[@]}" -eq 0 ]]; then
        return
    fi

    local child
    for child in "${children[@]}"; do
        collect_descendant_pids "${child}"
        echo "${child}"
    done
}

kill_process_tree() {
    local root_pid="$1"
    local descendants=()
    local targets=()

    read_lines_into_array descendants < <(collect_descendant_pids "${root_pid}" || true)

    if [[ "${#descendants[@]}" -gt 0 ]]; then
        targets=("${descendants[@]}" "${root_pid}")
    else
        targets=("${root_pid}")
    fi

    kill -TERM "${targets[@]}" >/dev/null 2>&1 || true
    sleep 0.4

    local survivors=()
    read_lines_into_array survivors < <(ps -o pid= -p "${targets[@]}" 2>/dev/null | awk '{print $1}' || true)

    if [[ "${#survivors[@]}" -gt 0 ]]; then
        kill -KILL "${survivors[@]}" >/dev/null 2>&1 || true
    fi
}

cleanup() {
    if [[ -n "${child_pid}" ]] && kill -0 "${child_pid}" >/dev/null 2>&1; then
        kill_process_tree "${child_pid}"
    fi

    "${preflight_script}" || true
}

print_runtime_indicator "$@"

"${preflight_script}"
trap cleanup EXIT INT TERM

cd "${root_dir}"

"$@" &

child_pid="$!"

wait "${child_pid}"
