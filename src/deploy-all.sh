#!/bin/bash
ENVIRONMENT=$1
BLUE="\033[0;34m"
NOCOLOR='\033[0m'

if [ -z "$1" ]
  then
    echo "Must include environment (staging, production) as parameter."
    exit;
fi
cd ../trellis
for DIRECTORY in `ls ../sites`
do
    if ! [ $DIRECTORY == "composer" ] && ! [ $DIRECTORY == "autoload.php" ]; then
        printf "${BLUE}\n=======\nDeploying $DIRECTORY\n=======\n${NOCOLOR}"
        ansible-playbook deploy.yml -e env=$ENVIRONMENT -e site=$DIRECTORY
    fi
done