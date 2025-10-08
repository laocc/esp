#!/bin/sh

current_dir=$(pwd -P)
ROOT=""

while [ "$current_dir" != "/" ]; do
    if [ -f "${current_dir}/vendor/laocc/esp/composer.json" ]; then
        ROOT="$current_dir"
        break
    fi
    current_dir=$(dirname "$current_dir")
done

if [ -z "$ROOT" ]; then
    echo 'not in ESP path'
    echo
    exit
fi

echo "${ROOT}/public/cli/index.php" $*
echo
/usr/local/php/bin/php "${ROOT}/public/cli/index.php" $*
