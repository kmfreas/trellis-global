#!/bin/bash
shopt -s nullglob


ssh-add .vagrant/machines/default/virtualbox/private_key

MIGRATE_CMD="ansible-playbook migrate-task.yml -e env=$1 -e site=$2 -e mode=$3 -e sync_cmd=all"
ENVIRONMENTS=( hosts/* )
ENVIRONMENTS=( "${ENVIRONMENTS[@]##*/}" )
NUM_ARGS=3
WARN='\033[0;33m'
NC='\033[0m'

show_usage() {
  echo "Usage: migrate-task <environment> <site name> <mode>

<environment> is the environment to migrate to ("staging", "production", etc)
<site name> is the WordPress site to migrate (name defined in "wordpress_sites")

Available environments:
`( IFS=$'\n'; echo "${ENVIRONMENTS[*]}" )`

Examples:
  migrate-task staging example.com
  migrate-task production example.com
"
}

HOSTS_FILE="hosts/$1"

[[ $# -ne $NUM_ARGS || $1 = -h ]] && { show_usage; exit 0; }

if [ "$1" = "production" ] && [ "$3" = "push" ]; then
    RAND=`cat /dev/urandom | env LC_CTYPE=C tr -cd 'a-f0-9' | head -c 3`
    printf "\n${WARN}--------------\nLooks like you're trying to push to production. Type these characters to continue:\n$RAND\n--------------${NC}\n"
    read INPUT
    if [ $RAND != $INPUT ]; then
        exit;
    fi
fi

if [[ ! -e $HOSTS_FILE ]]; then
  echo "Error: $1 is not a valid environment ($HOSTS_FILE does not exist)."
  echo
  echo "Available environments:"
  ( IFS=$'\n'; echo "${ENVIRONMENTS[*]}" )
  exit 0
fi

$MIGRATE_CMD
