#!/bin/sh

set -e

echo "== composer lint =="
composer lint:fix

echo "== composer type-check =="
composer type-check

echo "== composer test:orange =="
composer test:orange