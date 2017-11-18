#!/bin/bash

BLUE="\033[0;34m"
NOCOLOR='\033[0m'


cd ../sites
DIR=$PWD

echo $DIR
for DIRECTORY in `ls`
do
    printf "${BLUE}\n\n================ \n $DIRECTORY \n================ \n${NOCOLOR}"
    cd $DIRECTORY
    git status --porcelain
    git stash
    git checkout production
    git pull
    cd ../../trellis/
    echo "Skip?"
    read INPUT
    if [ "y" != $INPUT ]; then

        vagrant ssh -- -t "composer remove johnpbloch/wordpress -d /srv/www/$DIRECTORY/current/"
        vagrant ssh -- -t "composer clear-cache -d /srv/www/$DIRECTORY/current/"
        vagrant ssh -- -t "composer require johnpbloch/wordpress:4.8.3 -d /srv/www/$DIRECTORY/current/"
        vagrant ssh -- -t "composer update -d /srv/www/$DIRECTORY/current/"
        cd $DIR
        cd $DIRECTORY
        git add -u
        git commit -m 'update wordpress to 4.8.3, update plugins'
        git push
        cd ../../trellis/
        ./bin/deploy.sh production $DIRECTORY
    fi

    cd $DIR
done
