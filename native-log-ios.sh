#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_root="${script_dir}"
output_file="${script_dir}/native-log-ios.txt"

read_env_var() {
    local key="$1"
    local env_file="${2:-${project_root}/.env}"

    if [[ ! -f "${env_file}" ]]; then
        return 1
    fi

    local line
    line="$(grep -E "^${key}=" "${env_file}" | tail -n 1 || true)"

    if [[ -z "${line}" ]]; then
        return 1
    fi

    line="${line#*=}"
    line="${line%\"}"
    line="${line#\"}"
    line="${line%\'}"
    line="${line#\'}"

    printf '%s' "${line}"
}

append_section() {
    local title="$1"
    printf '\n===== %s =====\n' "${title}" >>"${output_file}"
}

run_capture() {
    local title="$1"
    shift

    append_section "${title}"
    printf '+ %s\n' "$*" >>"${output_file}"

    if "$@" >>"${output_file}" 2>&1; then
        return 0
    fi

    local status=$?
    printf 'Command failed with exit code: %s\n' "${status}" >>"${output_file}"
}

append_file_tail() {
    local title="$1"
    local file_path="$2"
    local lines="${3:-400}"

    append_section "${title}"

    if [[ ! -f "${file_path}" ]]; then
        printf 'Missing file: %s\n' "${file_path}" >>"${output_file}"
        return 0
    fi

    printf '+ tail -n %s %s\n' "${lines}" "${file_path}" >>"${output_file}"
    if tail -n "${lines}" "${file_path}" >>"${output_file}" 2>&1; then
        return 0
    fi

    local status=$?
    printf 'Command failed with exit code: %s\n' "${status}" >>"${output_file}"
}

{
    printf 'native-log-ios generated at: %s\n' "$(date -u '+%Y-%m-%d %H:%M:%S UTC')"
    printf 'working directory: %s\n' "${project_root}"
} >"${output_file}"

if ! command -v xcrun >/dev/null 2>&1; then
    append_section "Error"
    printf 'xcrun not found in PATH.\n' >>"${output_file}"
    printf 'Wrote %s\n' "${output_file}"
    exit 1
fi

app_id="${APP_ID:-${NATIVEPHP_APP_ID:-}}"
if [[ -z "${app_id}" ]]; then
    app_id="$(read_env_var "NATIVEPHP_APP_ID" || true)"
fi

booted_udid="$(xcrun simctl list devices booted | grep -Eo '[A-F0-9-]{36}' | head -n 1 || true)"

data_container=""
app_container=""
if [[ -n "${booted_udid}" && -n "${app_id}" ]]; then
    data_container="$(xcrun simctl get_app_container "${booted_udid}" "${app_id}" data 2>/dev/null || true)"
    app_container="$(xcrun simctl get_app_container "${booted_udid}" "${app_id}" app 2>/dev/null || true)"
fi

app_executable=""
if [[ -n "${app_container}" && -f "${app_container}/Info.plist" ]]; then
    app_executable="$(plutil -extract CFBundleExecutable raw -o - "${app_container}/Info.plist" 2>/dev/null || true)"
fi
app_executable="${app_executable:-NativePHP-simulator}"

append_section "Detected Values"
printf 'APP_ID=%s\n' "${app_id:-}" >>"${output_file}"
printf 'BOOTED_UDID=%s\n' "${booted_udid:-}" >>"${output_file}"
printf 'DATA_CONTAINER=%s\n' "${data_container:-}" >>"${output_file}"
printf 'APP_CONTAINER=%s\n' "${app_container:-}" >>"${output_file}"
printf 'APP_EXECUTABLE=%s\n' "${app_executable:-}" >>"${output_file}"

run_capture "Host clock" date
run_capture "Simulator clock" xcrun simctl spawn "${booted_udid:-booted}" date
run_capture "xcrun --version" xcrun --version
run_capture "Xcode version" xcodebuild -version
run_capture "Booted simulators" xcrun simctl list devices booted

if [[ -n "${booted_udid}" && -n "${app_id}" ]]; then
    run_capture "App bundle container path" xcrun simctl get_app_container "${booted_udid}" "${app_id}" app
    run_capture "App data container path" xcrun simctl get_app_container "${booted_udid}" "${app_id}" data
fi

if [[ -n "${data_container}" ]]; then
    run_capture "Application Support directory" ls -la "${data_container}/Library/Application Support"
    append_file_tail "nativephp_debug.log (tail)" "${data_container}/Library/Application Support/nativephp_debug.log" 500
    append_file_tail "laravel.log (tail)" "${data_container}/Library/Application Support/storage/logs/laravel.log" 500
    run_capture "Bundled app root (Documents/app)" ls -la "${data_container}/Documents/app"
    run_capture "Bundled Vite build directory" ls -la "${data_container}/Documents/app/public/build"
    run_capture "Bundled Vite build assets" ls -la "${data_container}/Documents/app/public/build/assets"
    run_capture "Bundled Vite manifest (head)" head -n 200 "${data_container}/Documents/app/public/build/manifest.json"
    run_capture \
        "Bundled app .env key values (selected)" \
        /bin/bash -lc "grep -E '^(APP_ENV|APP_URL|ASSET_URL|NATIVEPHP_RUNNING|NATIVEPHP_APP_ID|NATIVEPHP_APP_VERSION|VITE_DEV_SERVER_URL)=' \"${data_container}/Documents/app/.env\" || true"
    run_capture \
        "nativephp_debug.log staleness check" \
        /bin/bash -lc "if [[ ! -f \"${data_container}/Library/Application Support/nativephp_debug.log\" ]]; then echo \"missing log file: ${data_container}/Library/Application Support/nativephp_debug.log\"; else grep -Eo '\\[[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\\.[0-9]{3}\\]' \"${data_container}/Library/Application Support/nativephp_debug.log\" | tail -n 1 | sed 's/^/last_nativephp_debug_timestamp=/' || true; fi; date '+host_now=%Y-%m-%d %H:%M:%S %Z'"
else
    append_section "App Container"
    printf 'Could not resolve app data container. Ensure simulator is booted and app is installed.\n' >>"${output_file}"
fi

run_capture \
    "App process logs only (last 5m)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 5m --predicate \
    "process == \"${app_executable}\""

run_capture \
    "App + WebKit logs (last 5m)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 5m --predicate \
    "process == \"${app_executable}\" OR process == \"com.apple.WebKit.WebContent\" OR process == \"com.apple.WebKit.Networking\" OR process == \"com.apple.WebKit.GPU\""

run_capture \
    "App/WebKit navigation + load failures (last 5m)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 5m --predicate \
    "(process == \"${app_executable}\" OR process == \"com.apple.WebKit.WebContent\") AND (eventMessage CONTAINS[c] \"didFail\" OR eventMessage CONTAINS[c] \"provisional\" OR eventMessage CONTAINS[c] \"navigation\" OR eventMessage CONTAINS[c] \"Failed to load\" OR eventMessage CONTAINS[c] \"Unable to\" OR eventMessage CONTAINS[c] \"error\")"

run_capture \
    "Simulator unified logs (last 20m, NativePHP/Laravel/WebKit keywords)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 20m --predicate \
    'eventMessage CONTAINS[c] "nativephp" OR eventMessage CONTAINS[c] "laravel" OR eventMessage CONTAINS[c] "webkit" OR process CONTAINS[c] "nativephp"'

run_capture \
    "App process logs only (last 20m)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 20m --predicate \
    "process == \"${app_executable}\""

run_capture \
    "App process WebKit/navigation failures (last 20m)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 20m --predicate \
    "process == \"${app_executable}\" AND (eventMessage CONTAINS[c] \"WKErrorDomain\" OR eventMessage CONTAINS[c] \"didFail\" OR eventMessage CONTAINS[c] \"navigation\" OR eventMessage CONTAINS[c] \"provisional\" OR eventMessage CONTAINS[c] \"webview\" OR eventMessage CONTAINS[c] \"Failed to load\")"

run_capture \
    "App process JS console errors (last 20m)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 20m --predicate \
    "process == \"${app_executable}\" AND (eventMessage CONTAINS[c] \"JS error\" OR eventMessage CONTAINS[c] \"JS exception\" OR eventMessage CONTAINS[c] \"Uncaught\" OR eventMessage CONTAINS[c] \"Unhandled Promise\" OR eventMessage CONTAINS[c] \"TypeError\" OR eventMessage CONTAINS[c] \"ReferenceError\" OR eventMessage CONTAINS[c] \"SyntaxError\")"

run_capture \
    "App process JS console lines (last 20m)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 20m --predicate \
    "process == \"${app_executable}\" AND eventMessage CONTAINS \"JS \""

run_capture \
    "WebKit WebContent process JS/errors (last 20m)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 20m --predicate \
    "process == \"com.apple.WebKit.WebContent\" AND (eventMessage CONTAINS[c] \"JS\" OR eventMessage CONTAINS[c] \"Uncaught\" OR eventMessage CONTAINS[c] \"TypeError\" OR eventMessage CONTAINS[c] \"ReferenceError\" OR eventMessage CONTAINS[c] \"SyntaxError\" OR eventMessage CONTAINS[c] \"Failed\" OR eventMessage CONTAINS[c] \"error\" OR eventMessage CONTAINS[c] \"exception\")"

run_capture \
    "WebKit helper processes lifecycle/errors (last 20m)" \
    xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 20m --predicate \
    "(process == \"com.apple.WebKit.WebContent\" OR process == \"com.apple.WebKit.Networking\" OR process == \"com.apple.WebKit.GPU\") AND (eventMessage CONTAINS[c] \"launch\" OR eventMessage CONTAINS[c] \"terminated\" OR eventMessage CONTAINS[c] \"crash\" OR eventMessage CONTAINS[c] \"error\" OR eventMessage CONTAINS[c] \"fail\" OR eventMessage CONTAINS[c] \"suspend\" OR eventMessage CONTAINS[c] \"resume\")"

if [[ -n "${app_id}" ]]; then
    run_capture \
        "WebKit helper process logs scoped to app (last 20m)" \
        xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 20m --predicate \
        "(process CONTAINS[c] \"WebKit\" OR process CONTAINS[c] \"webkit\" OR process CONTAINS[c] \"BrowserEngineKit\") AND (eventMessage CONTAINS[c] \"${app_id}\" OR eventMessage CONTAINS[c] \"app<${app_id}\" OR eventMessage CONTAINS[c] \"${app_executable}\")"

    run_capture \
        "WebKit helper process errors scoped to app (last 20m)" \
        xcrun simctl spawn "${booted_udid:-booted}" log show --style compact --last 20m --predicate \
        "(process CONTAINS[c] \"WebKit\" OR process CONTAINS[c] \"webkit\" OR process CONTAINS[c] \"BrowserEngineKit\") AND (eventMessage CONTAINS[c] \"${app_id}\" OR eventMessage CONTAINS[c] \"app<${app_id}\" OR eventMessage CONTAINS[c] \"${app_executable}\") AND (eventMessage CONTAINS[c] \"error\" OR eventMessage CONTAINS[c] \"fail\" OR eventMessage CONTAINS[c] \"crash\" OR eventMessage CONTAINS[c] \"terminated\" OR eventMessage CONTAINS[c] \"provisional\" OR eventMessage CONTAINS[c] \"navigation\")"
else
    append_section "WebKit helper process logs scoped to app (last 20m)"
    printf 'Skipping section because APP_ID is empty.\n' >>"${output_file}"

    append_section "WebKit helper process errors scoped to app (last 20m)"
    printf 'Skipping section because APP_ID is empty.\n' >>"${output_file}"
fi

append_file_tail "nativephp/ios-build.log (tail)" "${project_root}/nativephp/ios-build.log" 500
append_file_tail "build-ios.txt (tail)" "${project_root}/build-ios.txt" 500

printf 'Wrote %s\n' "${output_file}"
