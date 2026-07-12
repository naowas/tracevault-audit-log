#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${ROOT}/.wordpress-org"
SRC="${OUT}/source"
FONT_REGULAR="$(fc-match -f '%{file}' 'Noto Sans,DejaVu Sans')"
FONT_BOLD="$(fc-match -f '%{file}' 'Noto Sans Bold,DejaVu Sans Bold')"

magick "${SRC}/icon-source.png" \
	-resize '256x256^' -gravity center -extent 256x256 -strip \
	"${OUT}/icon-256x256.png"

magick "${OUT}/icon-256x256.png" \
	-resize '128x128^' -gravity center -extent 128x128 -strip \
	"${OUT}/icon-128x128.png"

cp "${SRC}/icon.svg" "${OUT}/icon.svg"

magick "${SRC}/banner-source.png" \
	-resize '1544x500^' -gravity center -extent 1544x500 \
	-fill 'rgba(2,6,23,0.72)' -draw 'rectangle 0,0 690,500' \
	-fill 'rgba(20,184,166,0.22)' -draw 'rectangle 0,0 8,500' \
	-font "${FONT_BOLD}" -pointsize 74 -fill '#ffffff' -annotate +80+165 'TraceVault' \
	-font "${FONT_BOLD}" -pointsize 42 -fill '#ccfbf1' -annotate +84+220 'Audit Log' \
	-font "${FONT_REGULAR}" -pointsize 27 -fill '#cbd5e1' -annotate +84+282 'Security activity logging for WordPress' \
	-font "${FONT_REGULAR}" -pointsize 22 -fill '#94a3b8' -annotate +84+325 'Monitor users, content, settings, plugins, themes, and WooCommerce' \
	-strip "${OUT}/banner-1544x500.png"

magick "${OUT}/banner-1544x500.png" -resize 772x250 -strip "${OUT}/banner-772x250.png"

magick "${SRC}/screenshot-1-source.png" -strip "${OUT}/screenshot-1.png"
magick "${SRC}/screenshot-2-source.png" -strip "${OUT}/screenshot-2.png"
magick "${SRC}/screenshot-3-source.png" -strip "${OUT}/screenshot-3.png"
rm -f "${OUT}"/screenshot-{4,5}.png
