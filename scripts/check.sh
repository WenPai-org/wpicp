#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

while IFS= read -r -d '' f; do
  php -l "$f" >/dev/null
done < <(find . -name "*.php" -not -path "./lib/*" -not -path "./vendor/*" -not -path "./node_modules/*" -print0)
echo "php -l ok"

for js in assets/js/*.js; do
  node --check "$js"
done
echo "node --check ok"

python3 lib/wenpai-admin-ui/scripts/wenpai-admin-lint.py --plugin-root "$root" --strict
php prototypes/wpicp/build.php --check
echo "wpicp check ok"
