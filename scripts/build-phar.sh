#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_VERSION_FILE="$ROOT_DIR/src/BuildVersion.php"

cleanup() {
  rm -f "$BUILD_VERSION_FILE"
}

trap cleanup EXIT

if [[ -n "${OC_CLI_VERSION:-}" ]]; then
  VERSION="${OC_CLI_VERSION}"
else
  VERSION="$(php -r 'require "'"$ROOT_DIR"'/vendor/autoload.php"; echo \OpenCart\CLI\Application::resolveVersion(true);')"
fi

php "$ROOT_DIR/scripts/write-build-version.php" "$VERSION"
php -d phar.readonly=0 "$ROOT_DIR/box.phar" compile --config="$ROOT_DIR/box.json"
