#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../" && pwd)"
stamp_file="${root_dir}/nativephp/.nativephp-mobile-version"

read_nativephp_mobile_version() {
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
' "${root_dir}/composer.lock"
}

current_version="$(read_nativephp_mobile_version || true)"
if [[ -z "${current_version}" ]]; then
    echo "[native-prepare] failed to read nativephp/mobile version from composer.lock" >&2
    exit 1
fi

should_install=0
reason=""

if [[ ! -d "${root_dir}/nativephp/android" ]]; then
    should_install=1
    reason="nativephp/android directory missing"
elif [[ ! -f "${root_dir}/nativephp/android/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt" ]]; then
    should_install=1
    reason="nativephp android project files missing"
elif [[ ! -f "${stamp_file}" ]]; then
    should_install=1
    reason="version stamp missing"
else
    installed_version="$(<"${stamp_file}")"
    if [[ "${installed_version}" != "${current_version}" ]]; then
        should_install=1
        reason="version changed (${installed_version} -> ${current_version})"
    fi
fi

if [[ "${should_install}" -eq 1 ]]; then
    echo "[native-prepare] refreshing native android project (${reason})"
    (
        cd "${root_dir}"
        php artisan native:install android --with-icu --force --no-interaction
    )
    printf '%s\n' "${current_version}" > "${stamp_file}"
fi

(
    cd "${root_dir}"
    npm run build -- --mode=android
)
