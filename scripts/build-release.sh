#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"
version="$(sed -n 's/^Stable tag: //p' readme.txt | head -n 1)"
header_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' infirewards.php | head -n 1)"
header_domain="$(sed -n 's/^ \* Text Domain:[[:space:]]*//p' infirewards.php | head -n 1)"
constant_version="$(awk -F "'" '/define\( \x27INFIREWARDS_VERSION\x27/ { print $4 }' infirewards.php | head -n 1)"
if [[ -z "$version" || "$version" != "$header_version" || "$version" != "$constant_version" || "$header_domain" != "infirewards" ]]; then
  echo "Release metadata does not agree; check version and text domain." >&2
  exit 1
fi

output="${1:-$root/dist/infirewards-$version.zip}"
if [[ "$output" != /* ]]; then
  output="$root/$output"
fi
mkdir -p "$(dirname "$output")"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
package="$stage/infirewards"
mkdir -p "$package/assets" "$package/languages"
cp infirewards.php readme.txt LICENSE "$package/"
mkdir -p "$package/src"
rsync -a --exclude='.gitkeep' src/ "$package/src/"
cp -R assets/css assets/js "$package/assets/"
if [[ -d assets/images ]]; then
  rsync -a --exclude='temp/' assets/images/ "$package/assets/images/"
fi
"$root/scripts/generate-pot.sh" "$package/languages/infirewards.pot"

(
  cd "$stage"
  zip -qr "$stage/release.zip" infirewards
)
mv -f "$stage/release.zip" "$output"
echo "Built $output"
