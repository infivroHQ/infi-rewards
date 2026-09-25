#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"
output="${1:-$root/languages/infirewards.pot}"
version="$(sed -n 's/^Stable tag: //p' readme.txt | head -n 1)"
mkdir -p "$(dirname "$output")"

mapfile -d '' php_files < <(find src -type f -name '*.php' -print0 | sort -z)
xgettext \
  --language=PHP \
  --from-code=UTF-8 \
  --package-name=infiRewards \
  --package-version="$version" \
  --msgid-bugs-address=https://infivro.com \
  --add-comments=translators: \
  --sort-output \
  --no-wrap \
  --keyword=__ \
  --keyword=_e \
  --keyword=_n:1,2 \
  --keyword=_x:1,2c \
  --keyword=_ex:1,2c \
  --keyword=esc_html__ \
  --keyword=esc_html_e \
  --keyword=esc_attr__ \
  --keyword=esc_attr_e \
  --output="$output" \
  infirewards.php "${php_files[@]}"

echo "Generated $output"
