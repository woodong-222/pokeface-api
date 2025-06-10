#!/bin/bash
service nginx start
php-fpm -D
tail -f /var/log/nginx/access.log