#!/usr/bin/env bash

# This script is used for internal testing of BLT.
# It will generate a sibling directory for BLT named `blted8`.
# The new sample project will have a symlink to BLT in blted8/vendor/acquia/blt.

set -ev

export PATH=${COMPOSER_BIN}:${PATH}

${BLT_DIR}/vendor/bin/robo create:symlinked-project

export PATH=${BLT_DIR}/../blted8/vendor/bin:$PATH

# The local.hostname must be set to 127.0.0.1:8888 because we are using drush runserver to run the site on Travis CI.
yaml-cli update:value ../blted8/blt/project.yml project.local.hostname '127.0.0.1:8888'

# Define BLT's deployment endpoints.
yaml-cli update:value ../blted8/blt/project.yml git.remotes.0 bolt8@svn-5223.devcloud.hosting.acquia.com:bolt8.git
yaml-cli update:value ../blted8/blt/project.yml git.remotes.1 git@github.com:acquia-pso/blted8.git

# Ensure that at least one module gets enabled in CI env.
yaml-cli update:value ../blted8/blt/project.yml modules.ci.enable.0 views_ui
