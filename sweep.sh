#!/usr/bin/env bash

set -euo pipefail

checks=(
  "composer lint:fix"
  "composer rector:fix"
  "composer type-check"
  "composer test:orange"
)

for check in "${checks[@]}"; do
  echo ""
  echo "==> $check"
  if ! eval "$check"; then
    echo "" >&2
    echo "FAILED: $check" >&2
    exit 1
  fi
done

echo ""
echo "All checks passed.”