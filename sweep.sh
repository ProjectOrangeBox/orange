#!/bin/sh

set -e

echo "== composer lint =="
composer lint:fix

echo "== analyze rector fix =="
composer analyze:rectorFix

echo "== composer type-check =="
composer type-check

echo "== composer test:orange =="
composer test:orange