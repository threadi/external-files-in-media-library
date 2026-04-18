#!/usr/bin/env bash
env COMPOSER=dev-composer.json composer install
cd vendor/threadi/easy-dialog-for-wordpress/
npm install
npm run build
cd ../../../