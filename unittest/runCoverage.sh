#!/bin/sh
#
# Generate a code-coverage report for the Orange Framework core (../src).
#
# Requires a PHP with a coverage driver (PCOV or Xdebug). On this machine PCOV
# is installed into Homebrew PHP; this script auto-discovers a suitable php.
#
# Usage:
#   sh runCoverage.sh              # whole suite
#   sh runCoverage.sh RouterTest   # a single test file (name without .php)
#
# Output:
#   ./coverage/index.html          # browsable HTML report
#   a line summary is also printed to the terminal
#
set -e

DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$DIR/.." && pwd)"                 # framework root (contains src/ + unittest/)
SRC="$ROOT/src"
PHPUNIT="$DIR/../../../bin/phpunit"           # vendor/bin/phpunit (as in runUnitTests.sh)
COVERAGE_DIR="$DIR/coverage"

# run one named test file, or the whole ./tests directory
if [ -n "$1" ]; then
    TESTS="$DIR/tests/$1.php"
else
    TESTS="$DIR/tests"
fi

# Pick the first PHP that has a coverage driver loaded. Preference order:
# whatever `php` resolves to, then the Homebrew build (where PCOV is installed).
PHP=""
for cand in "$(command -v php 2>/dev/null)" /opt/homebrew/bin/php /opt/homebrew/opt/php/bin/php; do
    [ -x "$cand" ] || continue
    if "$cand" -m 2>/dev/null | grep -qiE '^(xdebug|pcov)$'; then
        PHP="$cand"
        break
    fi
done

if [ -z "$PHP" ]; then
    echo "ERROR: no PHP with a coverage driver (PCOV or Xdebug) was found." >&2
    echo "       Install one, e.g.  pecl install pcov  , then re-run." >&2
    exit 1
fi

# PCOV only instruments files under pcov.directory, and it is php.ini-only
# (not settable at runtime). PHPUnit forwards -d settings to the isolated child
# processes, so passing it here reaches every test. Harmless when the driver is
# Xdebug (it just ignores the setting).
PCOV_ARGS=""
if "$PHP" -m 2>/dev/null | grep -qi '^pcov$'; then
    PCOV_ARGS="-d pcov.enabled=1 -d pcov.directory=$ROOT"
fi

mkdir -p "$COVERAGE_DIR"
echo "Coverage php: $PHP ($("$PHP" -r 'echo PHP_VERSION;'))"

# phpunit-coverage.xml supplies the bootstrap, process isolation (required — the
# suite relies on singletons that leak state between tests) and the <source>
# include/exclude (config/, views/ and stubs/ are data, not testable logic).
XDEBUG_MODE=coverage "$PHP" $PCOV_ARGS "$PHPUNIT" \
    -c "$DIR/phpunit-coverage.xml" \
    --coverage-html "$COVERAGE_DIR" \
    --coverage-text \
    --colors \
    "$TESTS"
