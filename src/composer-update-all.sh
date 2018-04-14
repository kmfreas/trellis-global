#!/bin/bash
cd ../sites
DIR=$PWD
WPVERSION='4.9.5'
GREEN="\033[0;32m"
NOCOLOR='\033[0m'

SITES=`ls | tr "\n" " "`

for DIRECTORY in `ls`
do
    printf "\n\n================ \n $DIRECTORY \n================ \n"
    echo "Skip?"
    read INPUT
    if [ "y" != $INPUT ]; then
        cd $DIRECTORY
        git status --porcelain
        git stash
        git checkout production
        git pull

        cd ../../trellis/
        vagrant ssh -- -t "composer remove johnpbloch/wordpress -d /srv/www/$DIRECTORY/current/"
        vagrant ssh -- -t "composer clear-cache -d /srv/www/$DIRECTORY/current/"
        vagrant ssh -- -t "composer require johnpbloch/wordpress:$WPVERSION -d /srv/www/$DIRECTORY/current/"
        vagrant ssh -- -t "composer update -d /srv/www/$DIRECTORY/current/"
        cd $DIR
        cd $DIRECTORY
        git add -u
        git commit -m "update wordpress to $WPVERSION, update plugins"
        git push
        cd ../../trellis/
        ./bin/deploy.sh production $DIRECTORY
    fi

    cd $DIR
done

printf "${GREEN}\n\nUpdate complete. You should check all sites to ensure the update did not break anything. Run the below string to open all sites in Chrome\n\n"
printf "/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome $SITES${NOCOLOR}\n\n"
