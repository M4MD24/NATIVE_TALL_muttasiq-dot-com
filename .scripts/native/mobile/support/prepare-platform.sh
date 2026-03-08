#!/usr/bin/env bash
set -euo pipefail

native_root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../" && pwd)"

native_read_mobile_version() {
    php -r '
$lockPath = $argv[1] ?? null;
if (! $lockPath || ! file_exists($lockPath)) {
    exit(1);
}
$lock = json_decode(file_get_contents($lockPath), true);
if (! is_array($lock)) {
    exit(1);
}
foreach (($lock["packages"] ?? []) as $package) {
    if (($package["name"] ?? null) === "nativephp/mobile") {
        echo (string) ($package["version"] ?? "");
        exit(0);
    }
}
exit(1);
' "${native_root_dir}/composer.lock"
}

native_prepare_platform_install() {
    local platform="$1"
    local required_file="$2"
    shift 2
    local -a install_args=("$@")

    local stamp_file="${native_root_dir}/nativephp/.nativephp-mobile-version-${platform}"
    local current_version=""
    current_version="$(native_read_mobile_version || true)"

    if [[ -z "${current_version}" ]]; then
        echo "[native-prepare:${platform}] failed to read nativephp/mobile version from composer.lock" >&2
        exit 1
    fi

    local should_install=0
    local reason=""
    local platform_dir="${native_root_dir}/nativephp/${platform}"
    local required_path="${native_root_dir}/${required_file}"

    if [[ ! -d "${platform_dir}" ]]; then
        should_install=1
        reason="nativephp/${platform} directory missing"
    elif [[ ! -f "${required_path}" ]]; then
        should_install=1
        reason="${required_file} missing"
    elif [[ ! -f "${stamp_file}" ]]; then
        should_install=1
        reason="version stamp missing"
    else
        local installed_version=""
        installed_version="$(<"${stamp_file}")"
        if [[ "${installed_version}" != "${current_version}" ]]; then
            should_install=1
            reason="version changed (${installed_version} -> ${current_version})"
        fi
    fi

    if [[ "${should_install}" -eq 1 ]]; then
        echo "[native-prepare:${platform}] refreshing native ${platform} project (${reason})"
        (
            cd "${native_root_dir}"
            php artisan native:install "${platform}" "${install_args[@]}" --force --no-interaction
        )
        printf '%s\n' "${current_version}" > "${stamp_file}"
    fi
}
