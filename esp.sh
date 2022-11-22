#!/bin/sh

ROOT=$(pwd -P)

if [ ! -f "${ROOT}/vendor/laocc/esp/composer.json" ]; then
    echo 'not in ESP path'
    echo
    exit
fi

#echo "${ROOT}/public/cli/index.php" $*
/usr/local/php/bin/php "${ROOT}/public/cli/index.php" $*
