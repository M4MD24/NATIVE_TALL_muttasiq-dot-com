#!/usr/bin/env bash
set -euo pipefail

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../../" && pwd)"

python3 - "${root_dir}" <<'PY'
import re
import sys
from pathlib import Path

root_dir = Path(sys.argv[1])


class PatchError(RuntimeError):
    pass


def replace_once_or_error(
    text: str,
    old: str,
    new: str,
    label: str,
    already_contains: str | None = None,
) -> tuple[str, bool]:
    if old in text:
        return text.replace(old, new, 1), True

    if already_contains is not None and already_contains in text:
        return text, False

    if new in text:
        return text, False

    raise PatchError(f"pattern not found for {label}")


def replace_regex_once_or_error(
    text: str,
    pattern: str,
    replacement: str,
    label: str,
    already_contains: str | None = None,
    flags: int = re.MULTILINE | re.DOTALL,
) -> tuple[str, bool]:
    updated, count = re.subn(pattern, replacement, text, count=1, flags=flags)
    if count:
        return updated, True

    if already_contains is not None and already_contains in text:
        return text, False

    if replacement in text:
        return text, False

    raise PatchError(f"regex pattern not found for {label}")


def patch_top_bar_component(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-edge] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        text, updated = replace_once_or_error(
            text,
            "public bool $showNavigationIcon = true,",
            "public ?bool $showNavigationIcon = null,",
            "TopBar showNavigationIcon default",
            already_contains="public ?bool $showNavigationIcon = null,",
        )
        changed = changed or updated

        text, updated = replace_once_or_error(
            text,
            "fn ($value) => $value !== null && $value !== false",
            "fn ($value) => $value !== null",
            "TopBar array_filter predicate",
            already_contains="fn ($value) => $value !== null",
        )
        changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-edge] patched: {path}")
        else:
            print(f"[native-edge] already ok: {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-edge] error: {error} ({path})")
        return False, True


def patch_bottom_nav_component(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-edge] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        text, updated = replace_once_or_error(
            text,
            "public string $labelVisibility = 'labeled',",
            "public ?string $labelVisibility = null,",
            "BottomNav labelVisibility default",
            already_contains="public ?string $labelVisibility = null,",
        )
        changed = changed or updated

        patched_method = """protected function toNativeProps(): array
    {
        return array_filter([
            'dark' => $this->dark,
            'label_visibility' => $this->labelVisibility,
            'active_color' => $this->activeColor,
        ], fn ($value) => $value !== null);
    }"""

        if patched_method in text:
            updated = False
        else:
            method_pattern_with_active_color = (
                r"protected function toNativeProps\(\): array\s*\{\s*"
                r"return \[\s*"
                r"'dark' => \$this->dark,\s*"
                r"'label_visibility' => \$this->labelVisibility,\s*"
                r"'active_color' => \$this->activeColor,\s*"
                r"'id' => 'bottom_nav',\s*"
                r"\];\s*"
                r"\}"
            )
            method_pattern_legacy = (
                r"protected function toNativeProps\(\): array\s*\{\s*"
                r"return \[\s*"
                r"'dark' => \$this->dark,\s*"
                r"'label_visibility' => \$this->labelVisibility,\s*"
                r"'id' => 'bottom_nav',\s*"
                r"\];\s*"
                r"\}"
            )

            text, updated = replace_regex_once_or_error(
                text,
                method_pattern_with_active_color,
                patched_method,
                "BottomNav toNativeProps (active_color)",
                already_contains=patched_method,
            )

            if not updated and patched_method not in text:
                text, updated = replace_regex_once_or_error(
                    text,
                    method_pattern_legacy,
                    patched_method,
                    "BottomNav toNativeProps (legacy)",
                    already_contains=patched_method,
                )

        changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-edge] patched: {path}")
        else:
            print(f"[native-edge] already ok: {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-edge] error: {error} ({path})")
        return False, True


def patch_edge_component(path: Path) -> tuple[bool, bool]:
    if not path.exists():
        print(f"[native-edge] skip missing: {path}")
        return False, False

    try:
        text = path.read_text()
        changed = False

        needle = "        $target = &self::navigateToComponent($context);\n\n        // Update the placeholder with actual data\n"
        replacement = """        $target = &self::navigateToComponent($context);
        $children = $target['data']['children'] ?? [];

        $shouldSkip = false;
        if ($type === 'top_bar') {
            $title = $data['title'] ?? null;
            if ($title === null || $title === '') {
                $shouldSkip = true;
            }
        } elseif ($type === 'bottom_nav') {
            if (empty($data) && empty($children)) {
                $shouldSkip = true;
            }
        }

        if ($shouldSkip) {
            if (count($context) === 1) {
                unset(self::$components[$context[0]]);
                self::$components = array_values(self::$components);
            } else {
                $childIndex = array_pop($context);
                array_pop($context);
                array_pop($context);
                $parent = &self::navigateToComponent($context);
                if (isset($parent['data']['children'][$childIndex])) {
                    unset($parent['data']['children'][$childIndex]);
                    $parent['data']['children'] = array_values($parent['data']['children']);
                }
            }

            array_pop(self::$contextStack);
            return;
        }

        // Update the placeholder with actual data
"""

        if replacement in text:
            updated = False
        elif needle in text:
            text = text.replace(needle, replacement, 1)
            updated = True
        else:
            raise PatchError("pattern not found for Edge context skip logic")

        changed = changed or updated

        text, updated = replace_once_or_error(
            text,
            "        $target['data'] = array_merge($data, [\n            'children' => $target['data']['children'] ?? [],\n        ]);\n",
            "        $target['data'] = array_merge($data, [\n            'children' => $children,\n        ]);\n",
            "Edge children assignment",
            already_contains="            'children' => $children,",
        )
        changed = changed or updated

        if changed:
            path.write_text(text)
            print(f"[native-edge] patched: {path}")
        else:
            print(f"[native-edge] already ok: {path}")

        return changed, False
    except PatchError as error:
        print(f"[native-edge] error: {error} ({path})")
        return False, True


for patch_fn, target_path in [
    (patch_top_bar_component, root_dir / "vendor/nativephp/mobile/src/Edge/Components/Navigation/TopBar.php"),
    (patch_bottom_nav_component, root_dir / "vendor/nativephp/mobile/src/Edge/Components/Navigation/BottomNav.php"),
    (patch_edge_component, root_dir / "vendor/nativephp/mobile/src/Edge/Edge.php"),
]:
    _, error = patch_fn(target_path)
    if error:
        raise SystemExit(1)
PY
