#!/bin/bash

# Activate Python virtual environment
source /opt/venv/bin/activate

# Start Nginx
service nginx start

# Start PHP-FPM
php-fpm -D

# Keep container running
tail -f /var/log/nginx/access.log