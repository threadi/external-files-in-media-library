#!/usr/bin/env bash
#
# Collects the files of a release and creates the release zip.
#
# Used by the GitHub Action "Build release zip" and by "ant build", so both create the same zip.
#
# Usage:
#   bin/build-release.sh prepare              Install the PHP packages for the release (without dev packages)
#                                             and generate the release autoloader.
#   bin/build-release.sh collect <version>    Copy the files of the release to dist/<slug> and set the version number.
#   bin/build-release.sh zip <file>           Create the zip <file> from dist/<slug>.
#   bin/build-release.sh restore              Install the dev packages again and remove dist (for local builds after "prepare").
#
# "npm run build" must have been run before "collect".

set -euo pipefail

# the name of the plugin folder in the zip.
SLUG="${RELEASE_SLUG:-external-files-in-media-library}"

# always work in the root directory of the plugin.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DIST="dist/${SLUG}"

# the composer binary; Ant sets it from "composer.path" in build/build.properties.
# (not named "COMPOSER", as composer uses this variable for the name of the composer.json)
COMPOSER_CMD="${COMPOSER_BIN:-composer}"

error() {
  echo "Error: $1" >&2
  exit 1
}

prepare() {
  "$COMPOSER_CMD" install --no-dev --no-scripts --no-interaction --no-progress

  # its classes are shipped in app/Dependencies (copied by Mozart during "composer install").
  # Without this line the autoloader would also know them under their original names.
  rm -rf vendor/threadi/easy-transients-for-wordpress

  "$COMPOSER_CMD" dump-autoload -oa --no-dev
}

collect() {
  local version="${1:-}"
  if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ ]]; then
    error "'$version' is not a version number like 5.5.0 or 5.5.0-beta.1."
  fi

  # start with an empty directory.
  rm -rf "dist/${SLUG}"
  mkdir -p "$DIST"

  # only what is listed here ends up in the zip. Nothing is deleted from the plugin directory.
  # rsync stops with an error if one of these paths does not exist.
  # vendor/composer/tmp-* are leftovers of interrupted downloads of composer, *~ are backup files.
  rsync -aR --exclude='.*' --exclude='node_modules/' --exclude='/vendor/composer/tmp-*' --exclude='*~' \
    "${SLUG}.php" uninstall.php readme.txt LICENSE composer.json \
    admin app gfx inc templates \
    blocks/build \
    js/commands \
    vendor/autoload.php vendor/composer \
    vendor/graham-campbell vendor/guzzlehttp vendor/psr vendor/ralouphie vendor/spatie vendor/symfony \
    vendor/threadi/crypt-for-wordpress/src \
    vendor/threadi/easy-dialog-for-wordpress/build \
    vendor/threadi/easy-directory-listing-for-wordpress/build \
    vendor/threadi/easy-directory-listing-for-wordpress/lib \
    vendor/threadi/easy-settings-for-wordpress \
    "$DIST/"

  # Driver.js is copied to admin/driverjs by "npm run build". Without it the intro would be missing silently.
  for FILE in driver.js.iife.js driver.css LICENSE; do
    if [ ! -f "$DIST/admin/driverjs/$FILE" ]; then
      error "admin/driverjs/$FILE is missing - run \"npm run build\" before."
    fi
  done

  # the release must not contain dev packages (e.g. from a "composer install" with dev packages).
  for PACKAGE in symfony/console symfony/finder symfony/filesystem psr/log psr/container; do
    if [ -d "$DIST/vendor/$PACKAGE" ]; then
      error "vendor/$PACKAGE is a dev package - run \"bin/build-release.sh prepare\" before."
    fi
  done

  # instructions for AI agents of the settings library: not needed on a website.
  rm -rf "$DIST/vendor/threadi/easy-settings-for-wordpress/skills"

  # no file of the plugin is this large: such a file is a leftover (e.g. a download) and must not be in the release.
  LARGE_FILES=$(find "$DIST" -type f -size +5M)
  if [ -n "$LARGE_FILES" ]; then
    error "These files are larger than 5 MB and would end up in the release:
$LARGE_FILES"
  fi

  # set the version number - in the copy only. "-i.bak" works with GNU and BSD sed (macOS).
  sed -i.bak "s/@@VersionNumber@@/${version}/g" "$DIST/${SLUG}.php" "$DIST/readme.txt"
  rm -f "$DIST/${SLUG}.php.bak" "$DIST/readme.txt.bak"
}

create_zip() {
  local target="${1:-}"
  [ -n "$target" ] || error "No zip file given."
  [ -d "$DIST" ] || error "$DIST does not exist - run \"bin/build-release.sh collect <version>\" before."

  # get the absolute path of the zip, as zip runs in the dist directory.
  mkdir -p "$(dirname "$target")"
  target="$(cd "$(dirname "$target")" && pwd)/$(basename "$target")"

  rm -f "$target"
  (cd dist && zip -rqX "$target" "$SLUG")
  echo "Created $target"
}

restore() {
  # remove the collected files: the zip is created and PHPCS would find all classes twice.
  rm -rf dist

  # get back the package removed by "prepare", as Mozart needs it during "composer install".
  # if composer does not know it as installed package, "composer install" below installs it.
  if "$COMPOSER_CMD" show threadi/easy-transients-for-wordpress > /dev/null 2>&1; then
    "$COMPOSER_CMD" reinstall threadi/easy-transients-for-wordpress --no-interaction --no-progress
  fi

  # install the dev packages again. This also runs Mozart.
  "$COMPOSER_CMD" install --no-interaction --no-progress
}

case "${1:-}" in
  prepare) prepare ;;
  restore) restore ;;
  collect) collect "${2:-}" ;;
  zip) create_zip "${2:-}" ;;
  *) error "Usage: $0 prepare | collect <version> | zip <file> | restore" ;;
esac
