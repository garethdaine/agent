#!/usr/bin/env bash
set -u

MODE="commit"
SOURCE="repo"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --mode=*)
            MODE="${1#*=}"
            shift
            ;;
        --mode)
            MODE="${2:-}"
            shift 2
            ;;
        --source=*)
            SOURCE="${1#*=}"
            shift
            ;;
        --source)
            SOURCE="${2:-}"
            shift 2
            ;;
        *)
            shift
            ;;
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

if [[ "${ARTISAN_RUNNER}" == *" "* ]]; then
    bash -lc "${ARTISAN_RUNNER} docs:sync --mode=${MODE} --source=${SOURCE}"
else
    "${ARTISAN_RUNNER}" docs:sync --mode="${MODE}" --source="${SOURCE}"
fi

SYNC_EXIT_CODE=$?

if [[ ${SYNC_EXIT_CODE} -ne 0 ]]; then
    if [[ "${MODE}" == "commit" ]]; then
        echo "[docs-sync] WARNING: docs sync failed in commit mode (exit ${SYNC_EXIT_CODE}); commit will continue." >&2
        exit 0
    fi

    echo "[docs-sync] ERROR: docs sync failed in deploy mode (exit ${SYNC_EXIT_CODE})." >&2
    exit ${SYNC_EXIT_CODE}
fi

if [[ "${MODE}" == "commit" ]] && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    MANIFEST_PATH="${DOCS_SYNC_MANIFEST_PATH:-storage/app/docs-sync/manifest.json}"
    STAGE_PATHS="${DOCS_SYNC_STAGE_PATHS:-docs ${MANIFEST_PATH}}"
    # shellcheck disable=SC2206
    PATHS_TO_STAGE=(${STAGE_PATHS})
    git add -A -- "${PATHS_TO_STAGE[@]}" >/dev/null 2>&1 || true
fi

exit 0

