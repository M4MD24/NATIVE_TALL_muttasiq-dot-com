#!/usr/bin/env bash
set -euo pipefail

if [[ "$(uname -s)" != "Darwin" ]]; then
    echo "[native-ios-sim] simulator selection requires macOS (Darwin)." >&2
    exit 1
fi

if ! command -v xcrun >/dev/null 2>&1; then
    echo "[native-ios-sim] xcrun was not found. Install Xcode command line tools." >&2
    exit 1
fi

simulator_udid="$(
    xcrun simctl list devices available --json | php -r '
    $json = stream_get_contents(STDIN);
    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data["devices"]) || !is_array($data["devices"])) {
        exit(1);
    }

    $booted = null;
    $fallback = null;

    foreach ($data["devices"] as $devices) {
        if (!is_array($devices)) {
            continue;
        }

        foreach ($devices as $device) {
            if (!is_array($device)) {
                continue;
            }

            if (($device["isAvailable"] ?? false) !== true) {
                continue;
            }

            $name = (string) ($device["name"] ?? "");
            $udid = (string) ($device["udid"] ?? "");
            $state = (string) ($device["state"] ?? "");

            if ($udid === "" || strpos($name, "iPhone") === false) {
                continue;
            }

            if ($state === "Booted") {
                $booted = $udid;
                break 2;
            }

            if ($fallback === null) {
                $fallback = $udid;
            }
        }
    }

    if ($booted !== null) {
        echo $booted;
        exit(0);
    }

    if ($fallback !== null) {
        echo $fallback;
        exit(0);
    }

    exit(1);
    '
)"

if [[ -z "${simulator_udid}" ]]; then
    echo "[native-ios-sim] no available iPhone simulator was found." >&2
    exit 1
fi

echo "${simulator_udid}"
