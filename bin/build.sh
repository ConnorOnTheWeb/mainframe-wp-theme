#!/usr/bin/env bash
# Build a local mainframe.zip for manual testing.
# Usage: bin/build.sh [version]
# Example: bin/build.sh 1.2.3  →  mainframe-1.2.3.zip
#          bin/build.sh        →  mainframe-dev.zip

set -euo pipefail

THEME_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="${1:-dev}"
ZIPNAME="mainframe.zip"
DEST="${THEME_ROOT}/${ZIPNAME}"

cd "$THEME_ROOT"

# Remove any previous build.
rm -f "$DEST"

zip -r "$ZIPNAME" . \
  --exclude '*.git*' \
  --exclude '.github/*' \
  --exclude 'README.md' \
  --exclude 'bin/*' \
  --exclude '.DS_Store' \
  --exclude '*.zip'

echo "Built: ${DEST}"
