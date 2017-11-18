#!/bin/bash


cd ../trellis
for DIRECTORY in `ls ../vendor/19ideas`
do
    echo $DIRECTORY
    rsync -a ../sites-cp/$DIRECTORY/web/app/uploads ../vendor/19ideas/$DIRECTORY/web/app/
    rsync -a ../sites-cp/$DIRECTORY/web/app/plugins ../vendor/19ideas/$DIRECTORY/web/app/
    rsync -a ../sites-cp/$DIRECTORY/vendor ../vendor/19ideas/$DIRECTORY
done