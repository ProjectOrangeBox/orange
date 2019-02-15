#!/usr/bin/env bash

mkdir -p ./../support/build/config
mkdir -p ./../support/build/views/errors

cp ./../../../../application/config/application.php ./../support/build/config/application.php
cp ./../../../../application/config/auth.php ./../support/build/config/auth.php
cp ./../../../../application/config/autoload.php ./../support/build/config/autoload.php
cp ./../../../../application/config/config.php ./../support/build/config/config.php
cp ./../../../../application/config/migration.php ./../support/build/config/migration.php
cp ./../../../../application/config/nav.php ./../support/build/config/nav.php
cp ./../../../../application/config/page.php ./../support/build/config/page.php
cp ./../../../../application/config/paths.php ./../support/build/config/paths.php
cp ./../../../../application/config/routes.php ./../support/build/config/routes.php
cp ./../../../../application/config/validate.php ./../support/build/config/validate.php

cp ./../../../../public/index.php ./../support/build/index.php

cp -R ./../../../../application/views/errors/* ./../support/build/views/errors
