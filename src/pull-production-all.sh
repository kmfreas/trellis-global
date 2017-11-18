#!/bin/bash
BLUE="\033[0;34m"
NOCOLOR='\033[0m'

cd ../trellis
for DIRECTORY in `ls ../sites`
do
    printf "${BLUE}\n=======\nMigrating $DIRECTORY\n=======\n${NOCOLOR}"

	./migrate-task.sh production $DIRECTORY pull
done