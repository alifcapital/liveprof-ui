#!/usr/bin/env bash
composer install --ignore-platform-reqs -v
php bin/cli.php aggregator:install
