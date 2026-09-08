#!/bin/bash
# Database credential loader for scripts running ON the NAS.
#
# Distinct from load-credentials.sh, which is for a developer workstation and
# validates SSH and deployment settings against a relative .env.credentials.
# This one loads only the database settings, from an absolute path, so the
# unattended production scripts have no credentials of their own.
#
# Source it, do not execute it:
#
#   source "$(dirname "${BASH_SOURCE[0]}")/load-db-credentials.sh"
#
# Override the location with CIRCULATION_CREDENTIALS when running from a
# checkout rather than the deployed web root.

CREDENTIALS_FILE="${CIRCULATION_CREDENTIALS:-/volume1/web/circulation/.env.credentials}"

if [ ! -r "$CREDENTIALS_FILE" ]; then
    echo "ERROR: credential file not readable at ${CREDENTIALS_FILE}" >&2
    echo "       Create it with DB_USER and DB_PASSWORD, then chmod 600." >&2
    echo "       See docs/operations-reference.md, 'Server-side credential file'." >&2
    return 1 2>/dev/null || exit 1
fi

set -a
# shellcheck disable=SC1090
. "$CREDENTIALS_FILE"
set +a

# Accept either naming style: .env.credentials.example uses PROD_DB_*, while
# Apache and the web code use DB_*.
DB_USER="${DB_USER:-$PROD_DB_USERNAME}"
DB_PASSWORD="${DB_PASSWORD:-$PROD_DB_PASSWORD}"
DB_NAME="${DB_NAME:-$PROD_DB_DATABASE}"
DB_SOCKET="${DB_SOCKET:-$PROD_DB_SOCKET}"

: "${DB_USER:?DB_USER or PROD_DB_USERNAME missing from ${CREDENTIALS_FILE}}"
: "${DB_PASSWORD:?DB_PASSWORD or PROD_DB_PASSWORD missing from ${CREDENTIALS_FILE}}"

# Scripts refer to the password as DB_PASS.
DB_PASS="$DB_PASSWORD"
