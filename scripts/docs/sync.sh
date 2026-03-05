#!/usr/bin/env bash
set -euo pipefail

MODE="commit"
SOURCE="repo"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --mode=*)  MODE="${1#*=}"; shift ;;
        --mode)    MODE="${2:-}"; shift 2 ;;
        --source=*) SOURCE="${1#*=}"; shift ;;
        --source)  SOURCE="${2:-}"; shift 2 ;;
        *)         shift ;;
    esac
done

if [[ "$MODE" != "commit" && "$MODE" != "deploy" ]]; then
    echo "[docs-sync] Unsupported mode: ${MODE}" >&2
    exit 1
fi

if [[ "$SOURCE" != "repo" ]]; then
    echo "[docs-sync] Unsupported source: ${SOURCE}" >&2
    exit 1
fi

if GIT_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)"; then
    cd "${GIT_ROOT}" || exit 1
fi

ARTISAN_RUNNER="${DOCS_SYNC_ARTISAN_BIN:-php artisan}"
STRICT_COMMIT="${DOCS_SYNC_STRICT_COMMIT:-0}"
DOCS_SYNC_QUEUE_CONNECTION="${DOCS_SYNC_QUEUE_CONNECTION:-sync}"

run_artisan() {
    local command="$1"
    if [[ "${ARTISAN_RUNNER}" == *" "* ]]; then
        bash -lc "${ARTISAN_RUNNER} ${command}" 2>&1
    else
        ${ARTISAN_RUNNER} ${command} 2>&1
    fi
}

can_boot_artisan() {
    run_artisan "env" >/dev/null 2>&1
}

if ! can_boot_artisan; then
    echo "[docs-sync] WARNING: Laravel app cannot boot (missing DB/Redis/config). Skipping docs sync." >&2
    exit 0
fi

echo "[docs-sync] Running docs:generate --source=${SOURCE} ..."
GEN_OUTPUT="$(run_artisan "docs:generate --source=${SOURCE} --export-openapi")" || GEN_EXIT_CODE=$?
GEN_EXIT_CODE="${GEN_EXIT_CODE:-0}"

if [[ ${GEN_EXIT_CODE} -ne 0 ]]; then
    echo "${GEN_OUTPUT}" >&2
    if [[ "${MODE}" == "commit" && "${STRICT_COMMIT}" != "1" ]]; then
        echo "[docs-sync] WARNING: docs generation failed (exit ${GEN_EXIT_CODE}); continuing." >&2
    else
        echo "[docs-sync] ERROR: docs generation failed (exit ${GEN_EXIT_CODE})." >&2
        exit ${GEN_EXIT_CODE}
    fi
else
    echo "${GEN_OUTPUT}"
fi

if [[ "${MODE}" == "commit" ]]; then
    echo "[docs-sync] Running docs:validate ..."
    VALIDATE_OUTPUT="$(run_artisan "docs:validate")" || VALIDATE_EXIT=$?
    VALIDATE_EXIT="${VALIDATE_EXIT:-0}"

    if [[ ${VALIDATE_EXIT} -ne 0 ]]; then
        echo "${VALIDATE_OUTPUT}" >&2
        if [[ "${STRICT_COMMIT}" == "1" ]]; then
            echo "[docs-sync] ERROR: docs validation failed (exit ${VALIDATE_EXIT}). Commit blocked." >&2
            exit ${VALIDATE_EXIT}
        fi
        echo "[docs-sync] WARNING: docs validation failed (exit ${VALIDATE_EXIT}); continuing." >&2
    fi
fi

echo "[docs-sync] Running docs:sync --mode=${MODE} --source=${SOURCE} ..."
if [[ "${ARTISAN_RUNNER}" == *" "* ]]; then
    SYNC_OUTPUT="$(bash -lc "QUEUE_CONNECTION=${DOCS_SYNC_QUEUE_CONNECTION} ${ARTISAN_RUNNER} docs:sync --mode=${MODE} --source=${SOURCE}" 2>&1)" || SYNC_EXIT=$?
else
    SYNC_OUTPUT="$(QUEUE_CONNECTION="${DOCS_SYNC_QUEUE_CONNECTION}" ${ARTISAN_RUNNER} docs:sync --mode="${MODE}" --source="${SOURCE}" 2>&1)" || SYNC_EXIT=$?
fi
SYNC_EXIT="${SYNC_EXIT:-0}"

if [[ ${SYNC_EXIT} -ne 0 ]]; then
    echo "${SYNC_OUTPUT}" >&2
    if [[ "${MODE}" == "commit" ]]; then
        echo "[docs-sync] WARNING: docs sync failed (exit ${SYNC_EXIT}); commit will continue." >&2
    else
        echo "[docs-sync] ERROR: docs sync failed in deploy mode (exit ${SYNC_EXIT})." >&2
        exit ${SYNC_EXIT}
    fi
else
    echo "${SYNC_OUTPUT}"
fi

if [[ "${MODE}" == "commit" ]] && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    MANIFEST_PATH="${DOCS_SYNC_MANIFEST_PATH:-docs-sync/manifest.json}"
    # shellcheck disable=SC2206
    PATHS_TO_STAGE=(docs "${MANIFEST_PATH}")
    git add -A -- "${PATHS_TO_STAGE[@]}" 2>/dev/null || true
    echo "[docs-sync] Staged docs changes for commit."
fi

exit 0
