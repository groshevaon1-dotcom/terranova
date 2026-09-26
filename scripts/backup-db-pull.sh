#!/usr/bin/env bash
# Стягивает свежий дамп базы Terra Nova с сервера на этот ноутбук.
# Это копия ВНЕ Beget: если хостинг упадёт, база останется у вас.
#
# Запуск (в терминале приложения, Git Bash):
#   bash scripts/backup-db-pull.sh
#
# Дампы кладутся в D:\Мои документы\Claude\Бэкапы-terranova\ (вне репозитория).
# В них персональные данные — храните папку на зашифрованном диске, не выкладывайте.
set -euo pipefail

# Системный ssh.exe/scp.exe Windows — читают ssh-agent Windows (там парольная фраза ключа).
SSH="/c/Windows/System32/OpenSSH/ssh.exe"; [ -x "$SSH" ] || SSH="ssh"
SCP="/c/Windows/System32/OpenSSH/scp.exe"; [ -x "$SCP" ] || SCP="scp"
KEY="C:\\Users\\grosh\\.ssh\\beget_terranova"
SRV="root@185.23.34.45"
REMOTE_DIR="/var/backups/terranova-db"
LOCAL_DIR="/d/Мои документы/Claude/Бэкапы-terranova"

mkdir -p "$LOCAL_DIR"

# Имя самого свежего дампа на сервере.
LATEST="$("$SSH" -i "$KEY" -o BatchMode=yes "$SRV" "ls -1t $REMOTE_DIR/terranova_*.sql.gz | head -1")"
if [ -z "$LATEST" ]; then
  echo "На сервере нет дампов. Сначала должен отработать ночной бэкап (03:30)."
  echo "Можно создать вручную: ssh ... '/opt/terranova/backup-db.sh'"
  exit 1
fi

echo "Скачиваю: $LATEST"
"$SCP" -i "$KEY" -o BatchMode=yes "$SRV:$LATEST" "$LOCAL_DIR/"

NAME="$(basename "$LATEST")"
echo
echo "Готово: $LOCAL_DIR/$NAME"
echo "Размер: $(du -h "$LOCAL_DIR/$NAME" | cut -f1)"
echo
echo "Проверка целостности (первые строки дампа):"
zcat "$LOCAL_DIR/$NAME" 2>/dev/null | head -3 || gunzip -t "$LOCAL_DIR/$NAME" && echo "архив цел."
