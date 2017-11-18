#! /bin/bash

cd /srv/www/
for DIRECTORY in `ls`

do
    cd $DIRECTORY/current/
    composer install
    cd /srv/www/
done
