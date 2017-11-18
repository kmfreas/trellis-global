#!/bin/bash
echo 'Running composer post update command'
ln -sfn vendor/sites sites
cd sites
for DIRECTORY in `ls`

do
    cd $DIRECTORY
    REMOTE=$(git remote -v | grep -c composer)
    if [ "$REMOTE" -ne "0" ]; then
        git remote remove composer
        git branch -u origin/dev dev
    fi
    cd ../
done
