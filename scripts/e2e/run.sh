#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BUILD_DIR="$ROOT/build/e2e"
STATE_FILE="$BUILD_DIR/state.json"
ENV_FILE="$BUILD_DIR/env.sh"

USE_DOCKER=0
COMPOSE=()

have_local_db_env() {
  [[ -n "${OC_E2E_DB_HOST:-}" ]] \
    && [[ -n "${OC_E2E_DB_NAME:-}" ]] \
    && [[ -n "${OC_E2E_DB_USER:-}" ]] \
    && [[ -n "${OC_E2E_DB_PORT:-}" ]] \
    && [[ ${OC_E2E_DB_PASS+x} ]]
}

setup_compose_command() {
  if docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose -f "$ROOT/scripts/e2e/docker-compose.yml" -p oc-cli-e2e)
    return
  fi

  if command -v docker-compose >/dev/null 2>&1; then
    COMPOSE=(docker-compose -f "$ROOT/scripts/e2e/docker-compose.yml" -p oc-cli-e2e)
    return
  fi

  echo "Docker Compose is required for the fallback E2E database path." >&2
  exit 1
}

cleanup() {
  status=$?

  if [[ $status -eq 0 ]]; then
    php "$ROOT/scripts/e2e/teardown.php" "$STATE_FILE" >/dev/null 2>&1 || true
  fi

  if [[ $USE_DOCKER -eq 1 && ${#COMPOSE[@]} -gt 0 ]]; then
    "${COMPOSE[@]}" down -v >/dev/null 2>&1 || true
  fi

  if [[ $status -eq 0 ]]; then
    rm -rf "$BUILD_DIR"
  else
    echo "E2E artifacts preserved at $BUILD_DIR" >&2
  fi

  exit $status
}

trap cleanup EXIT

mkdir -p "$BUILD_DIR"

if have_local_db_env; then
  echo "Using caller-provided database for E2E verification."
else
  setup_compose_command

  if ! docker info >/dev/null 2>&1; then
    echo "No local E2E database variables were provided and Docker is not available." >&2
    echo "Start Docker, or set OC_E2E_DB_HOST, OC_E2E_DB_NAME, OC_E2E_DB_USER, OC_E2E_DB_PASS, and OC_E2E_DB_PORT." >&2
    exit 1
  fi

  echo "Starting ephemeral MariaDB for E2E verification."
  "${COMPOSE[@]}" up -d mariadb
  USE_DOCKER=1

  export OC_E2E_DB_HOST=127.0.0.1
  export OC_E2E_DB_NAME=opencart_e2e
  export OC_E2E_DB_USER=oc_e2e
  export OC_E2E_DB_PASS=oc_e2e
  export OC_E2E_DB_PORT=33067
fi

php "$ROOT/scripts/e2e/provision.php"

set -a
# shellcheck source=/dev/null
source "$ENV_FILE"
set +a

APP_ENV= ./vendor/bin/phpunit --testsuite e2e
