#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
if [[ ! -f chronoarchive.php ]]; then
  echo "ERROR: missing chronoarchive.php" >&2
  exit 1
fi
command -v php >/dev/null || { echo "PHP CLI required" >&2; exit 1; }
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
  printf 'PHP OK: %s\n' "$file"
done < <(find . -type f -name '*.php' -not -path './vendor/*' -print0)
command -v node >/dev/null || { echo "Node.js required" >&2; exit 1; }
for dir in assets js; do
  [[ -d "$dir" ]] || continue
  while IFS= read -r -d '' file; do
    node --check "$file"
    printf 'JS OK: %s\n' "$file"
  done < <(find "$dir" -type f -name '*.js' -print0)
done
python3 scripts/check-release-metadata.py
