#!/usr/bin/env bash
#
# To enable this hook, developers should place it in .git/hooks/.
#
# You may adapt the message length check. Currently checking it's longer than
# 15 characters.

# Load YAML parser.
. `dirname $0`/../blt/parse-yaml.sh

eval $(parse_yaml project.yml)

regex="^${project_prefix}-[0-9]+(: )[^ ].{15,}\."
if ! grep -iqE "$regex" "$1"; then
  echo "Invalid commit message. Commit messages must:"
  echo "* Contain the project prefix followed by a hyphen"
  echo "* Contain a ticket number followed by a colon and a space"
  echo "* Be at least 15 characters long and end with a period."
  echo "Valid example: ${project_prefix}-135: Added the new picture field to the article feature."
  exit 1;
fi