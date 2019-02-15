#!/usr/bin/env bash

mkdir ./public
mkdir ./public/assets
mkdir ./public/theme
mkdir -m 0777 ./support
mkdir ./packages
mkdir ./packages/orange
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

cat << EOF > .env
; development, testing, production
DEBUG = development
ENVIRONMENT = development

GITBRANCH = master
CHOWN = www-data
CHGRP = www-data

EOF
