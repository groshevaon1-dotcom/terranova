#!/usr/bin/env bash
# Заливка статического сайта на сервер Terra Nova.
# Запуск из корня проекта:  bash deploy-to-server.sh
set -e
KEY="/c/Users/grosh/.ssh/beget_terranova"
SRV="root@185.23.34.45"
echo "Заливаю public/ на сервер..."
tar czf - -C public . | ssh -i "$KEY" -o BatchMode=yes "$SRV" \
  'rm -rf /var/www/terranova/* && tar xzf - -C /var/www/terranova && chown -R www-data:www-data /var/www/terranova && echo "Готово, файлов: $(find /var/www/terranova -type f | wc -l)"'
