#! /bin/bash
php -dxdebug.remote_enable=1 -dxdebug.remote_mode=req -dxdebug.remote_port=9000 -dxdebug.remote_host=127.0.0.1 \
bin/phpunit --bootstrap vendor/autoload.php --config app/phpunit.xml.dist

