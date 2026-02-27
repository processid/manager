#!/bin/sh

DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
PHPUNIT_PATH=$( realpath "${DIR}/../../../bin/phpunit" )

${PHPUNIT_PATH} --testdox --stop-on-failure --exclude-group=legacy "$@" "${DIR}"

