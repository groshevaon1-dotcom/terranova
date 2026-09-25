#!/usr/bin/env bash
# Публикация сайта Terra Nova на боевой сервер.
# Сервер сам тянет объединённую версию из GitHub main — безопасно при работе
# из нескольких сессий. Перед публикацией обязательно: git push в main.
set -e
KEY="/c/Users/grosh/.ssh/beget_terranova"
SRV="root@185.23.34.45"
echo "Публикую main на боевой сервер..."
ssh -i "$KEY" -o BatchMode=yes "$SRV" 'bash /opt/terranova/deploy.sh'
