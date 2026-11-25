#!/bin/sh
set -eu
dest="${1:?font destination required}"
mkdir -p "$dest"
base=https://raw.githubusercontent.com/rastikerdar/vazirmatn/v33.003
for file in Vazirmatn-Regular.woff2 Vazirmatn-Bold.woff2; do
  if [ ! -s "$dest/$file" ]; then
    curl -fsSL --connect-timeout 10 --max-time 40 --retry 1 "$base/fonts/webfonts/$file" -o "$dest/$file.tmp" || { rm -f "$dest/$file.tmp"; echo "Optional font unavailable: $file" >&2; continue; }
    mv "$dest/$file.tmp" "$dest/$file"
  fi
done
curl -fsSL --connect-timeout 10 --max-time 40 "$base/OFL.txt" -o "$dest/Vazirmatn-OFL.txt" || rm -f "$dest/Vazirmatn-OFL.txt"
