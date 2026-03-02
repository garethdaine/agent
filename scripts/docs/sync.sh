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
STRICT_COMMIT="${DOCS_SYNC_STRICT_COMMIT:-0}"
DOCS_SYNC_QUEUE_CONNECTION="${DOCS_SYNC_QUEUE_CONNECTION:-sync}"

run_artisan_command() {
    local command="$1"

    if [[ "${ARTISAN_RUNNER}" == *" "* ]]; then
        bash -lc "${ARTISAN_RUNNER} ${command}"
    else
        "${ARTISAN_RUNNER}" ${command}
    fi
}

run_docs_generation() {
    run_artisan_command "docs:generate --source=${SOURCE}"
}

run_docs_validation_and_coverage() {
    run_artisan_command "docs:validate"
    local validate_exit=$?
    if [[ ${validate_exit} -ne 0 ]]; then
        return ${validate_exit}
    fi

    run_artisan_command "docs:coverage --fail-on-missing"
}

run_docs_sync() {
    local command="docs:sync --mode=${MODE} --source=${SOURCE}"

    if [[ "${ARTISAN_RUNNER}" == *" "* ]]; then
        bash -lc "QUEUE_CONNECTION=${DOCS_SYNC_QUEUE_CONNECTION} ${ARTISAN_RUNNER} ${command}"
    else
        QUEUE_CONNECTION="${DOCS_SYNC_QUEUE_CONNECTION}" "${ARTISAN_RUNNER}" ${command}
    fi
}

run_docs_generation
GEN_EXIT_CODE=$?

if [[ ${GEN_EXIT_CODE} -ne 0 ]]; then
    if [[ "${MODE}" == "commit" && "${STRICT_COMMIT}" != "1" ]]; then
        echo "[docs-sync] WARNING: docs generation failed in commit mode (exit ${GEN_EXIT_CODE}); continuing because DOCS_SYNC_STRICT_COMMIT=${STRICT_COMMIT}." >&2
    else
        echo "[docs-sync] ERROR: docs generation failed (exit ${GEN_EXIT_CODE})." >&2
        exit ${GEN_EXIT_CODE}
    fi
fi

if [[ "${MODE}" == "commit" ]]; then
    run_docs_validation_and_coverage
    QUALITY_EXIT_CODE=$?

    if [[ ${QUALITY_EXIT_CODE} -ne 0 ]]; then
        if [[ "${STRICT_COMMIT}" == "1" ]]; then
            echo "[docs-sync] ERROR: docs validation/coverage gate failed (exit ${QUALITY_EXIT_CODE}). Commit blocked." >&2
            exit ${QUALITY_EXIT_CODE}
        fi

        echo "[docs-sync] WARNING: docs validation/coverage gate failed in commit mode (exit ${QUALITY_EXIT_CODE}); continuing because DOCS_SYNC_STRICT_COMMIT=${STRICT_COMMIT}." >&2
    fi
fi

run_docs_sync

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
