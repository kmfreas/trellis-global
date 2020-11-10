# About
This is a heavily customized version of [Trellis](https://github.com/roots/trellis). It's mainly geared towards managing multiple sites in one Trellis instance, so all your client sites can be contained in one environment for development, staging, and production. It includes features like:
- Comprehensive migrate task to easily pull/push site databases and uploads, including making database backups of each environment every time you run it
- Quickly create new sites with a custom console command written in Symfony Console
- Manage development sites with composer to keep updated with fellow devs

Checkout the personal branch to see how I use it for personal clients of mine.

## Installing
### Requirements
- composer
- ansible
- virtualbox
- vagrant
- vagrant plugins:
  - vagrant-triggers
  - vagrant-bindfs
  - vagrant-hostmanager

> Last install known to work:
> Vagrant 2.2.10
> Python 2.7 :(
> ansible 2.4.0.0

### Setup
- clone repository `git clone git@github.com:kmfreas/trellis-global.git`
- run `composer install` from repository root
- `cd trellis/`
- run `vagrant up` to setup box

## Migrating database/uploads
Migrate tasks can be run inside the trellis folder by running

`./migrate-task.sh (staging | production) domain (push | pull)`

Example:

`./migrate-task.sh production example.com pull`

A backup of the development and environment database will be saved to trellis/sql-dumps

## Creating a new site
New sites can be created by running

`./trellis-helper.php new example.com`

Currently the script is setup to create new sites for 19Ideas by posting to their bitbucket, but that can be changed in the code.

## Renaming a site
When renaming a site, trellis won't remove the old site from vagrant or from staging or production.  Therefore we have to manually remove these sites to save space, keep the server clean, and increase performance.  Before starting, make sure you have access to the DNS and have made the change to the site.
https://discourse.roots.io/t/how-to-clean-up-provisioned-deployed-and-deleted-websites

1. Change local configs in following files.  Make sure to adjust alphabetical order.
- trellis/group_vars/development/wordpress_sites.yml
- trellis/group_vars/development/vault.yml
- trellis/group_vars/staging/wordpress_sites.yml
- trellis/group_vars/staging/vault.yml
- trellis/group_vars/production/wordpress_sites.yml
- trellis/group_vars/production/vault.yml

2. Rename git repo (not necessary but preferable)

3. Adjust new repo name and folder name in composer.json.  Make sure to adjust alphabetical order.

4. Copy the site folder from sites/$SITE to another location in case you need a backup of the uploads or files that haven't been pushed to the new repo name.

5. Run `composer update`

6. Copy uploads from the backed up folder to the new site uploads folder (just saves you some time)

7. Run `vagrant provision`.  Make sure the backup task runs before the provision task starts, you'll see your full databases backed up in trellis/sql-dumps/vagrant/all.local.$TIMESTAMP.sql

8. The old database in vagrant is still there.  mysqldump that database to a file.  You can find the password for the root user by running `ansible-vault edit group_vars/development/vault.yml` from the trellis folder.  You should have a .vault_pass file with the password in it, if not ask another developer.

9. Import the dump file in vagrant by going to /srv/www/$SITE/current and running `wp db import $DUMP`

10. Run `wp search-replace $OLDSITEURL.dev $NEWSITEURL.dev` in vagrant

11. Run `ansible-playbook server.yml -e env=$ENVIRONMENT` for both environments (staging and production)

12. Deploy the new site for each environment

13. Run the migrate tasks for each environment

14. Absolutely make sure each environment looks correct on the new url, the next step will permanently erase the old site from each environment.

15. ssh into each environment (development, staging, production) and remove these files/folders. Staging and production will require you to login as ubuntu to run some of these, in which case you will need the AWS ssh key (ask another developer if you don't have it).
- /etc/nginx/sites-available/$SITE.com.conf
- /etc/nginx/sites-enabled/$SITE.com.conf
- /etc/nginx/includes.d/$SITE.com
- /srv/www/$OLDSITE/
- /etc/cron.d/wordpress-$SITE

16. Run `sudo nginx -t` in each environment, if it passes with no errors run `sudo service nginx restart`

17. Remove the old database from each mysql instance.  You can find the password for each environment by running `ansible-vault edit group_vars/$ENVIRONMENT/vault.yml`.  Once in run `drop database $OLDDATABASE;`

The old site should be removed.
