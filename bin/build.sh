#!/usr/bin/env bash

mkdir ./public
mkdir ./public/assets
mkdir ./public/theme
mkdir -m 0777 ./support
mkdir ./packages
mkdir ./packages/projectorangebox
mkdir ./var
mkdir -m 0777 ./var/cache
mkdir -m 0777 ./var/downloads
mkdir -m 0777 ./var/uploads
mkdir -m 0777 ./var/logs
mkdir -m 0777 ./var/email
mkdir -m 0777 ./var/sessions
mkdir -m 0777 ./var/tmp

cat << EOF > ./public/.htaccess
Options +FollowSymLinks

RewriteEngine On

RewriteBase /

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

RewriteRule ^(.*)$ index.php/$1 [L]
EOF

cat << EOF > ./composer.json
{
	"minimum-stability": "dev",
	"require": {
		"codeigniter/framework": "^3.1",
		"mmucklo/krumo": "*",
		"zordius/lightncandy": "dev-master",
		"league/climate": "dev-master"
	}
}
EOF

export APPLICATIONHASH=`date +%s | openssl md5`;

echo What is your servers Domain Name?
echo ie. http://dev.example.com/

read VARNAME


cat << EOF > .env
; development, testing, production
DEBUG = development
ENVIRONMENT = development

GITBRANCH = master
CHOWN = www-data
CHGRP = www-data

encryption_key = $APPLICATIONHASH

DOMAIN = $VARNAME

EOF

git clone https://github.com/ProjectOrangeBox/orange.git ./packages/projectorangebox/orange
git clone https://github.com/ProjectOrangeBox/orange-theme.git ./packages/projectorangebox/orange-theme
git clone https://github.com/ProjectOrangeBox/orange_extras.git ./packages/projectorangebox/extras

composer update

cp -R ./vendor/codeigniter/framework/application ./

rm -fdr ./application/cache
rm -fdr ./application/core
rm -fdr ./application/hooks
rm -fdr ./application/logs
rm -fdr ./application/third_party

rm ./application/index.html
rm ./application/config/index.html
rm ./application/controllers/index.html
rm ./application/helpers/index.html
rm ./application/language/index.html
rm ./application/libraries/index.html
rm ./application/models/index.html
rm ./application/views/index.html
rm ./application/views/errors/index.html
rm ./application/views/errors/cli/index.html
rm ./application/views/errors/html/index.html
rm ./application/language/english/index.html

cp ./packages/projectorangebox/orange/support/build/index.php ./public/index.php
cp ./packages/projectorangebox/orange/support/build/config/* ./application/config
cp -R ./packages/projectorangebox/orange/support/build/views/* ./application/views

cp ./packages/projectorangebox/orange/support/build/controllers/MainController.php ./application/controllers/MainController.php
cp ./packages/projectorangebox/orange/support/build/views/main/index.php ./application/views/main/index.php

