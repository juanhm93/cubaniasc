#!/usr/bin/env bash
#
# Security smoke test against a deployed site (staging or production).
# Checks what the code alone cannot guarantee on the shared hosting: that the
# document root is /public, that private files are not served and that the
# hardening headers and production error pages are in place.
#
# The hosting's bot protection sometimes answers with a 200 "One moment, please..."
# page instead of the real response. Those answers are retried and, if they
# persist, reported as warnings instead of failures.
#
# Usage: .github/scripts/security-smoke-test.sh https://cubania.purphura.com

set -uo pipefail

BASE_URL="${1:?Usage: $0 <base-url>}"
BASE_URL="${BASE_URL%/}"
FAILURES=0
MAX_TRIES=5

BODY_FILE="$(mktemp)"
HEADERS_FILE="$(mktemp)"
trap 'rm -f "$BODY_FILE" "$HEADERS_FILE"' EXIT

fail() {
    echo "::error::$1"
    FAILURES=$((FAILURES + 1))
}

warn() {
    echo "::warning::$1"
}

pass() {
    echo "ok - $1"
}

is_bot_challenge() {
    grep -qi '<title>One moment, please' "$BODY_FILE"
}

is_hosting_not_found_page() {
    [ "$STATUS" = "200" ] && grep -qi '<title>Not Found</title>' "$BODY_FILE"
}

# fetch <url> [curl args...]: fills $STATUS, $BODY_FILE and $HEADERS_FILE.
# Returns 1 when only bot-challenge pages came back.
fetch() {
    local url="$1"
    shift

    for ((try = 1; try <= MAX_TRIES; try++)); do
        STATUS="$(curl --silent --max-time 20 --dump-header "$HEADERS_FILE" --output "$BODY_FILE" \
            --write-out '%{http_code}' "$@" "$url")"

        # With REQUIRE_APP_RESPONSE=1, the hosting's own Not Found page does not count either:
        # the check needs the response Laravel produced.
        if ! is_bot_challenge && { [ "${REQUIRE_APP_RESPONSE:-0}" != "1" ] || ! is_hosting_not_found_page; }; then
            return 0
        fi

        sleep 3
    done

    return 1
}

# The site must be up, otherwise every other check is meaningless.
if ! fetch "${BASE_URL}/up" || [ "$STATUS" != "200" ]; then
    echo "::error::${BASE_URL}/up answered ${STATUS}; the site is not up"
    exit 1
fi
pass "/up answers 200"

# Files that only exist outside /public, or must never be served.
private_paths=(
    "/.env"
    "/.env.example"
    "/.env.production.example"
    "/.git/config"
    "/composer.json"
    "/composer.lock"
    "/package.json"
    "/artisan"
    "/vendor/autoload.php"
    "/storage/logs/laravel.log"
    "/.ftp-deploy-sync-state.json"
    "/CLAUDE.md"
    "/_boost/browser-logs"
)

for path in "${private_paths[@]}"; do
    if ! fetch "${BASE_URL}${path}"; then
        warn "${path}: only the hosting's bot challenge answered; not checked"
    elif [ "$STATUS" = "403" ] || [ "$STATUS" = "404" ]; then
        pass "${path} answers ${STATUS}"
    elif is_hosting_not_found_page; then
        pass "${path} answers the hosting's Not Found page"
    else
        fail "${path} answered ${STATUS}; expected 403 or 404"
    fi
done

# Directory listings must be disabled, including where payment receipts live.
for path in "/storage/" "/storage/payments/receipts/" "/build/"; do
    if ! fetch "${BASE_URL}${path}"; then
        warn "${path}: only the hosting's bot challenge answered; not checked"
    elif grep -qi '<title>Index of' "$BODY_FILE"; then
        fail "${path} lists its directory contents"
    else
        pass "${path} does not list its contents"
    fi
done

# Hardening headers set by App\Http\Middleware\SecurityHeaders.
expected_headers=(
    "strict-transport-security: max-age="
    "x-frame-options: sameorigin"
    "x-content-type-options: nosniff"
    "referrer-policy:"
    "content-security-policy: frame-ancestors"
)

if ! REQUIRE_APP_RESPONSE=1 fetch "${BASE_URL}/"; then
    warn "/: only the hosting's interstitial pages answered; headers not checked"
else
    for header in "${expected_headers[@]}"; do
        if tr -d '\r' < "$HEADERS_FILE" | grep -qi "^${header}"; then
            pass "header ${header%%:*} present"
        else
            fail "missing or wrong header: ${header}"
        fi
    done
fi

# Errors must not leak debug details (APP_DEBUG=false).
missing="smoke-test-$(date +%s)-not-found"
debug_pattern='stack trace|whoops|"trace"|"exception"|"file":|vendor/laravel'

if ! REQUIRE_APP_RESPONSE=1 fetch "${BASE_URL}/api/v1/${missing}" --header 'Accept: application/json'; then
    warn "API 404: only the hosting's interstitial pages answered; not checked"
elif grep -qiE "$debug_pattern" "$BODY_FILE"; then
    fail "API errors include exception details; set APP_DEBUG=false on the server"
else
    pass "API errors do not leak debug details"
fi

if ! REQUIRE_APP_RESPONSE=1 fetch "${BASE_URL}/${missing}"; then
    warn "HTML 404: only the hosting's interstitial pages answered; not checked"
elif grep -qiE "$debug_pattern" "$BODY_FILE"; then
    fail "HTML errors include exception details; set APP_DEBUG=false on the server"
else
    pass "HTML errors do not leak debug details"
fi

if [ "$FAILURES" -gt 0 ]; then
    echo "${FAILURES} security check(s) failed for ${BASE_URL}"
    exit 1
fi

echo "All security checks passed for ${BASE_URL}"
