# Сервер Terra Nova — как устроено

**VPS Beget:** `185.23.34.45` (root), Ubuntu 26.04, 2 ядра, 4 ГБ, 40 ГБ.
Регион СПб. Вход по SSH-ключу `~/.ssh/beget_terranova` (пароль в `private/deploy.conf`).

## Что стоит
- **nginx 1.28** — веб-сервер, автозапуск включён
- **certbot 4.0** — для HTTPS (сертификат выпускается после привязки домена)
- Файрвол `ufw`: открыты порты 22, 80, 443

## Где сайт
- Файлы: `/var/www/terranova/`
- Конфиг nginx: `/etc/nginx/sites-available/terranova`
- Домены в конфиге: `terranov.ru`, `www.terranov.ru`

## Как обновить сайт
Из корня проекта: `bash deploy-to-server.sh` — заливает папку `public/`.

## Что осталось (сделать после переключения DNS)
1. Домен `terranov.ru` пока смотрит на GitHub Pages (адреса 185.199.108–111.153).
   Нужно в панели Beget DNS заменить эти 4 A-записи на одну: `185.23.34.45`.
   То же для `www.terranov.ru`. MX и TXT (почта) не трогать.
2. После того как DNS обновится (до неск. часов), на сервере выпустить сертификат:
   `certbot --nginx -d terranov.ru -d www.terranov.ru --agree-tos -m privacy@terranov.ru --redirect`
3. Тогда сайт откроется по https://terranov.ru с замком.

## Форма, база, админка — НЕ настроены
Требуют решения владельца (152-ФЗ, оператор ПД). Пока форма — статическая,
связь через МАКС, Telegram, почту. См. CLAUDE.md, раздел про юридику.
