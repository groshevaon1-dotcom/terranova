#!/usr/bin/env bash
# Ночной бэкап базы Terra Nova. Пароль не светится в списке процессов:
# берём его из конфига оператора и кладём во временный defaults-файл (chmod 600).
set -euo pipefail

CONFIG="/opt/terranova-config/config.php"
DEST="/var/backups/terranova-db"
KEEP=14

mkdir -p "$DEST"
chmod 700 "$DEST"

# Достаём доступы из PHP-конфига (файл читаем только root).
eval "$(php -r '$c=require "/opt/terranova-config/config.php";
  printf("H=%s\nN=%s\nU=%s\nP=%s\n",
    escapeshellarg($c["db_host"]), escapeshellarg($c["db_name"]),
    escapeshellarg($c["db_user"]), escapeshellarg($c["db_pass"]));')"

TMP="$(mktemp)"
chmod 600 "$TMP"
cat > "$TMP" <<CNF
[client]
host=$H
user=$U
password=$P
CNF

STAMP="$(date +%F_%H%M)"
FILE="$DEST/terranova_${STAMP}.sql.gz"

mysqldump --defaults-extra-file="$TMP" \
  --single-transaction --quick --default-character-set=utf8mb4 \
  --routines --triggers "$N" | gzip -9 > "$FILE"

rm -f "$TMP"
chmod 600 "$FILE"

# Оставляем только KEEP последних файлов.
ls -1t "$DEST"/terranova_*.sql.gz | tail -n +$((KEEP+1)) | xargs -r rm -f

echo "backup ok: $FILE ($(du -h "$FILE" | cut -f1))"
