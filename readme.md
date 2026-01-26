# External files in media library

## About

This repository provides the features of the WordPress plugin _External files in media library_. The repository is used as a basis for deploying the plugin to the WordPress repository. It is not intended to run as a plugin as it is, even if that is possible for development.

## Hint

As user of the plugin you find documentation for protocols and handling in the [doc-directory](https://github.com/threadi/external-files-in-media-library/tree/master/docs/).

## Usage

After checkout go through the following steps:

### By hand

Run the following commands in this order:

1. `composer install`
2. `npm i`
3. `npm run build`
4. after that the plugin can be activated in WordPress.

### Using ant

1. copy _build/build.properties.dist_ to _build/build.properties_.
2. modify the _build/build.properties_ file - note the comments in the file.
3. after that the plugin can be activated in WordPress.

### Using Taskfile

1. Run this command: `task prepare`
2. after that the plugin can be activated in WordPress.

## Release

### From local environment by hand

1. `composer install`
2. `npm i`
3. `npm run build`
4. `composer test-install`
5. `composer test`
6. `vendor/bin/phpstan analyse`
7. `vendor/bin/phpcbf --standard=ruleset.xml .`
8. `vendor/bin/phpcs --standard=ruleset.xml .`
9. Set version nummer in _readme.txt_ and _external-files-in-media-library.php_
10. Create the release ZIP with all necessary folders and files.

### From local environment with ant

1. increase the version number in _build/build.properties_.
2. execute the following command in _build/_: `ant build`
3. after that you will find a zip file in the release directory, which could be used in WordPress to install it.

### From local environment with Taskfile

1. execute the following command in the main directory: `task release -- 5.0.0` - adjust the version number.
2. after that you will find a zip file in the release directory, which could be used in WordPress to install it.

### On GitHub

1. Create a new tag with the new version number.
2. The release zip will be created by a GitHub action.

## Translations

I recommend translating this plugin in the [WordPress Translating tool](https://translate.wordpress.org/projects/wp-plugins/external-files-in-media-library/).

For manual translation I recommend to use [PoEdit](https://poedit.net/) to translate texts for this plugin.

### Generate pot-file

Run in the main directory:

`wp i18n make-pot . languages/external-files-in-media-library.pot --exclude=svn/`

### Update translation-file

1. Open .po-file of the language in PoEdit.
2. Go to "Translate" > "Update from POT-file".
3. After this the new entries are added to the language-file.

### Export translation-file

1. Open .po-file of the language in PoEdit.
2. Go to "File" > "Save".
3. Upload the generated .mo-file and the .po-file to the plugin-folder languages/

## Check for WordPress Coding Standards

### Initialize

`composer install`

### Run

`vendor/bin/phpcs --standard=ruleset.xml .`

### Repair

`vendor/bin/phpcbf --standard=ruleset.xml .`

## Generate documentation

`vendor/bin/wp-documentor parse app --format=markdown --output=docs/hooks.md --prefix=eml_ --exclude=Section.php --exclude=Tab.php --exclude=Import.php --exclude=Export.php --exclude=Field_Base.php --exclude=Settings.php --exclude=Page.php --exclude=Rest.php`

## Check for WordPress VIP Coding Standards

Hint: this check runs against the VIP-GO-platform which is not our target for this plugin. Many warnings can be ignored.

### Run

`vendor/bin/phpcs --extensions=php --ignore=*/attributes/*,*/blocks/*,*/example/*,*/css/*,*/vendor/*,*/node_modules/*,*/svn/* --standard=WordPress-VIP-Go .`

## Check PHP compatibility

`vendor/bin/phpcs -p app --standard=PHPCompatibilityWP`

## Analyse with PHPStan

`vendor/bin/phpstan analyse`

## Check with the plugin "Plugin Check"

`wp plugin check --error-severity=7 --warning-severity=6 --include-low-severity-errors --categories=plugin_repo --format=json --slug=external-files-in-media-library .`

## PHP Unit tests

### Initialize the test environment

`composer test-install`

### Run them

`composer test`
