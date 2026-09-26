#!/usr/bin/env bash
# Публикация сайта Terra Nova на боевой сервер.
# Сервер сам тянет объединённую версию из GitHub main — безопасно при работе
# из нескольких сессий. Перед публикацией обязательно: git push в main.
set -e
# Системный ssh.exe Windows — читает ssh-agent Windows, где хранится
# парольная фраза ключа (её достаточно ввести один раз через ssh-add).
# Так деплой работает без ввода фразы, а ключ на диске остаётся зашифрованным.
SSH="/c/Windows/System32/OpenSSH/ssh.exe"
[ -x "$SSH" ] || SSH="ssh"          # запасной вариант, если системного нет
KEY="C:\\Users\\grosh\\.ssh\\beget_terranova"
SRV="root@185.23.34.45"
echo "Публикую main на боевой сервер..."
"$SSH" -i "$KEY" -o BatchMode=yes -o StrictHostKeyChecking=accept-new "$SRV" 'bash /opt/terranova/deploy.sh'
