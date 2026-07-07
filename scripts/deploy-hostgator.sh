#!/usr/bin/env bash
set -euo pipefail

BUILD_DIR="build/tvsumare-enterprise-3"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

cp -R public/* "$BUILD_DIR"/
mkdir -p "$BUILD_DIR/src" "$BUILD_DIR/data"
cp -R src/* "$BUILD_DIR/src"/
cp -R data/* "$BUILD_DIR/data"/ 2>/dev/null || true
cp composer.json "$BUILD_DIR/composer.json"

cd "$BUILD_DIR"
zip -r ../TVSUMARE_ENTERPRISE_3.0_BUILD_01.zip . -x "*.DS_Store"

echo "Build gerada em build/TVSUMARE_ENTERPRISE_3.0_BUILD_01.zip"
