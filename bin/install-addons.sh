#!/usr/bin/env bash
#
# Install the extensions of the plugin in the WordPress of the tests, so the live tests could test their services.
#
# Called by bin/install-wp-tests.sh via "composer test-install-live" (not by "composer test-install") and by
# "composer test-install-addons".
#
# Settings (from the environment, otherwise from .env.live, see .env.live.example):
#
# EFML_ADDONS_SOURCE: "none" (default, "github" with "composer test-install-live"), "github" or a directory with the working copies of the extensions side by side
#                     (e.g. /home/me/repos), which will be linked.
# EFML_ADDONS_REF:    branch, tag or commit on GitHub (default: master).
# EFML_ADDON_<AWS|GCS|DRIVE|WEBDAV>_SOURCE / _REF: the same for a single extension, e.g. EFML_ADDON_AWS_SOURCE with the
#                     path to its working copy or "none" to skip it.

PLUGIN_DIR=$(cd "$(dirname "$0")/.." && pwd)
TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}
WP_CORE_DIR=$(echo "$WP_CORE_DIR" | sed -e "s/\/$//")
ENV_FILE=${EFML_LIVE_ENV-$PLUGIN_DIR/.env.live}

# Return the value of the given setting: from the environment, otherwise from .env.live, otherwise the default.
get_setting() {
	local KEY=$1
	local DEFAULT=$2
	local VALUE=${!KEY}

	if [ -z "$VALUE" ] && [ -f "$ENV_FILE" ]; then
		VALUE=$(grep -E "^[[:space:]]*(export[[:space:]]+)?$KEY[[:space:]]*=" "$ENV_FILE" | tail -n 1 | sed -E "s/^[^=]*=[[:space:]]*//; s/[[:space:]]+#.*$//; s/^[\"'](.*)[\"']$/\1/; s/[[:space:]]*$//")
	fi

	if [ -z "$VALUE" ]; then
		VALUE=$DEFAULT
	fi

	echo "$VALUE"
}

# Check if the given command is installed.
check_command_installed() {
	if ! command -v "$1" > /dev/null; then
		echo "Error: $1 is not installed. Please install $1 and try again."
		exit 1
	fi
}

# Return the short name of an extension for its settings.
get_short_name() {
	case "$1" in
		external-files-from-aws-s3) echo "AWS" ;;
		external-files-from-google-cloud-storage) echo "GCS" ;;
		external-files-from-google-drive) echo "DRIVE" ;;
		external-files-from-webdav) echo "WEBDAV" ;;
		*) echo "" ;;
	esac
}

# Install an extension from GitHub incl. its dependencies, like its own build does.
install_addon_from_github() {
	local SLUG=$1
	local REPO=$2
	local REF=$3
	local TARGET=$4
	check_command_installed git
	check_command_installed composer

	# get the source in the requested branch, tag or commit.
	mkdir -p "$TARGET"
	git -C "$TARGET" init --quiet
	git -C "$TARGET" remote add origin "$REPO"
	if ! git -C "$TARGET" fetch --quiet --depth 1 origin "$REF"; then
		echo "Error: could not fetch $REF from $REPO."
		exit 1
	fi
	git -C "$TARGET" checkout --quiet FETCH_HEAD
	echo "Using $SLUG from GitHub: $REF ($(git -C "$TARGET" rev-parse --short HEAD))"

	# install the PHP dependencies without the dev dependencies (the scripts of the extension remove unused parts and
	# prefix the dependencies).
	( cd "$TARGET" && composer install --no-dev --no-interaction --prefer-dist --no-progress ) || exit 1
}

# Install the given extension.
install_addon() {
	local SLUG=$1
	local REPO=$2
	local SHORT
	SHORT=$(get_short_name "$SLUG")
	local SOURCE
	SOURCE=$(get_setting "EFML_ADDON_${SHORT}_SOURCE" "$ADDONS_SOURCE")
	local REF
	REF=$(get_setting "EFML_ADDON_${SHORT}_REF" "$ADDONS_REF")
	local TARGET=$WP_CORE_DIR/wp-content/plugins/$SLUG

	# bail if this extension should not be installed.
	if [ "$SOURCE" == "none" ]; then
		return
	fi

	rm -fr "$TARGET"
	mkdir -p "$WP_CORE_DIR/wp-content/plugins"

	if [ "$SOURCE" == "github" ]; then
		install_addon_from_github "$SLUG" "$REPO" "$REF" "$TARGET"
	elif [ -d "$SOURCE/$SLUG" ] || [ -f "$SOURCE/$SLUG.php" ]; then
		# link a local copy: the directory of the extension itself or a directory with all extensions side by side.
		local DIR=$SOURCE
		if [ -d "$SOURCE/$SLUG" ]; then
			DIR=$SOURCE/$SLUG
		fi
		ln -s "$(cd "$DIR" && pwd)" "$TARGET"
		echo "Using $SLUG from $DIR"
		if [ ! -f "$TARGET/vendor/autoload.php" ]; then
			echo "Warning: $DIR contains no vendor directory, run \"composer install\" there. The extension will be skipped in the tests."
		fi
	elif [ -d "$SOURCE" ]; then
		echo "Skipping $SLUG: it is not located in $SOURCE."
	else
		echo "Error: the source of $SLUG must be \"none\", \"github\" or an existing directory, \"$SOURCE\" given."
		exit 1
	fi
}

ADDONS_SOURCE=$(get_setting EFML_ADDONS_SOURCE "${EFML_ADDONS_DEFAULT_SOURCE-none}")
ADDONS_REF=$(get_setting EFML_ADDONS_REF master)

# get the extensions from the list of the live tests.
ADDONS=$(php -r '$data = json_decode( (string) file_get_contents( $argv[1] ), true ); foreach ( (array) ( $data["addons"] ?? array() ) as $slug => $repo ) { echo $slug . " " . $repo . PHP_EOL; }' "$PLUGIN_DIR/tests/Live/services.json")

while read -r SLUG REPO; do
	if [ -n "$SLUG" ]; then
		install_addon "$SLUG" "$REPO"
	fi
done <<< "$ADDONS"
