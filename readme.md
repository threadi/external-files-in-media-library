# External files in media library

## About

This repository provides the features of the WordPress plugin _External files in media library_. The repository is used as a basis for deploying the plugin to the WordPress repository. It is not intended to run as a plugin as it is, even if that is possible for development.

## Hint

As user of the plugin you find documentation for protocols and handling in the [doc-directory](https://github.com/threadi/external-files-in-media-library/tree/master/docs/).

## Usage

After checkout go through the following steps:

1. copy _build/build.properties.dist_ to _build/build.properties_.
2. modify the build/build.properties file - note the comments in the file.
3. after that the plugin can be activated in WordPress

## Release

### from local environment

1. increase the version number in _build/build.properties_.
2. execute the following command in _build/_: `ant build`
3. after that you will finde in the release directory a zip file which could be used in WordPress to install it.

### on GitHub

1. Create a new tag with the new version number.
2. The release zip will be created by GitHub action.

## Translations

I recommend to use [PoEdit](https://poedit.net/) to translate texts for this plugin.

### generate pot-file

Run in main directory:

`wp i18n make-pot . languages/external-files-in-media-library.pot --exclude=svn/`

### update translation-file

1. Open .po-file of the language in PoEdit.
2. Go to "Translate" > "Update from POT-file".
3. After this the new entries are added to the language-file.

### export translation-file

1. Open .po-file of the language in PoEdit.
2. Go to File > Save.
3. Upload the generated .mo-file and the .po-file to the plugin-folder languages/

## Check for WordPress Coding Standards

### Initialize

`composer install`

### Run

`vendor/bin/phpcs --extensions=php --ignore=*/attributes/*,*/blocks/*,*/example/*,*/css/*,*/vendor/*,*/node_modules/*,*/svn/*,*/app/Dependencies/* --standard=ruleset.xml .`

### Repair

`vendor/bin/phpcbf --extensions=php --ignore=*/attributes/*,*/blocks/*,*/example/*,*/css/*,*/vendor/*,*/node_modules/*,*/svn/*,*/app/Dependencies/* --standard=ruleset.xml .`

## Generate documentation

`vendor/bin/wp-documentor parse classes --format=markdown --output=doc/hooks.md --prefix=eml`

## Check for WordPress VIP Coding Standards

Hint: this check runs against the VIP-GO-platform which is not our target for this plugin. Many warnings can be ignored.

### Run

`vendor/bin/phpcs --extensions=php --ignore=*/attributes/*,*/blocks/*,*/example/*,*/css/*,*/vendor/*,*/node_modules/*,*/svn/* --standard=WordPress-VIP-Go .`

## Analyse with PHPStan

`vendor/bin/phpstan analyse`
