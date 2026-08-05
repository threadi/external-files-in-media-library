#!/usr/bin/env bash
env COMPOSER=dev-composer.json composer install
cd vendor/threadi/easy-directory-listing-for-wordpress/
npm install
npm run build
cd ../../../
