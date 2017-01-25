#!/usr/bin/env bash
# Enable aliases for non-interactive shell.
shopt -s expand_aliases
composer selfupdate
# Disable xdebug.
phpenv config-rm xdebug.ini
# Enable $_ENV variables in PHP.
echo 'variables_order = "EGPCS"' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
# Ensure that always_populate_raw_post_data PHP setting: Not set to -1 does not happen.
echo "always_populate_raw_post_data = -1" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
git config --global user.name "Travis-CI"
git config --global user.email "noreply@travis-ci.org"
mysql -u root -e "CREATE DATABASE drupal; CREATE USER 'drupal'@'localhost' IDENTIFIED BY 'drupal'; GRANT ALL ON drupal.* TO 'drupal'@'localhost';"
