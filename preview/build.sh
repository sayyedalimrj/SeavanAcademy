#!/usr/bin/env bash
set -euo pipefail

preview_source="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
academy_source="$(cd "${1:?Academy checkout required}" && pwd)"
destination="${2:?Output directory required}"

mkdir -p "$destination/assets"
cp "$preview_source"/{index.html,preview.css,preview.js} "$destination/"
cp "$academy_source/moodle/theme/seavan/style/seavan.css" "$destination/assets/"
cp "$academy_source/moodle/theme/seavan/pix/company-logo.svg" "$destination/assets/"
cp "$academy_source/moodle/local/seavanmedia/style.css" "$destination/assets/player.css"
sh "$academy_source/docker/fetch-fonts.sh" "$destination/assets"

# Create public sample media from generated color and geometry only.
ffmpeg -hide_banner -loglevel error -y -f lavfi -i 'color=c=0x173559:s=640x360:r=24:d=24' \
    -vf "drawgrid=w=32:h=32:t=1:c=white@0.12,drawbox=x=96:y=64:w=448:h=224:c=white@0.18:t=2,drawbox=x=144:y=104:w=352:h=146:c=0xFCAF17:t=2,drawbox=x=176:y=135:w=288:h=84:c=white@0.5:t=2,drawbox=x=120:y=274:w=400:h=4:c=0xFCAF17:t=fill:enable='lt(t,12)',drawbox=x=120:y=274:w=400:h=4:c=white@0.6:t=fill:enable='gte(t,12)'" \
    -an -c:v libx264 -pix_fmt yuv420p -movflags +faststart "$destination/assets/sample.mp4"
ffmpeg -hide_banner -loglevel error -y -i "$destination/assets/sample.mp4" \
    -frames:v 1 -update 1 "$destination/assets/sample.jpg"

node --check "$destination/preview.js"
