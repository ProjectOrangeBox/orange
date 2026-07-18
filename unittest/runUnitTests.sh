#!/bin/sh
# if they pass a specific test file name (without the .php) then run just that filename
if [ -n "$1" ]; then
    APPEND="/$1.php"
else
    APPEND=""
fi
# --process-isolation
# --debug

../../../bin/phpunit --display-all-issues --fail-on-deprecation --fail-on-warning --display-warnings --display-notices --display-errors --display-incomplete --process-isolation --colors --testdox --bootstrap bootstrap.php --testdox-text results.txt --testdox-html results.html ./tests$APPEND