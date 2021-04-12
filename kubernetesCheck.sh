#!/bin/bash
#
# Runs the container keepalive test and cache pruning script
#

curl -s http://127.0.0.1:5000 > /dev/null
RET=$?
if [ "$RET" != "0" ] ; then
    echo "curl http://127.0.0.1:5000 failed with return code $RET"
    exit $RET
fi

PHP=`which php`
if [ "$PHP" == "" ] ; then
    PHP=/app/.heroku/php/bin/php
fi
$PHP /app/clearCache.php $MAX_CACHE_SIZE

exit 0
