#!/usr/bin/env bash
# Предпросмотр: что изменится на боевом сайте при следующей публикации.
# Показывает разницу между тем, что сейчас на сервере, и main в GitHub.
set -e
KEY="/c/Users/grosh/.ssh/beget_terranova"
SRV="root@185.23.34.45"
ssh -i "$KEY" -o BatchMode=yes "$SRV" 'cd /opt/terranova && git fetch --quiet origin && \
  echo "На сервере: $(git rev-parse --short HEAD)  |  В GitHub: $(git rev-parse --short origin/main)" && \
  echo "--- Коммиты, которые опубликуются ---" && \
  git log --oneline HEAD..origin/main && \
  echo "--- Изменённые файлы ---" && \
  git diff --stat HEAD..origin/main'
