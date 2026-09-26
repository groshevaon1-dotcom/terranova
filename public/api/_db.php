<?php
// Общее подключение к базе. Секреты — вне веб-корня.
declare(strict_types=1);

function tn_config(): array {
    $path = '/opt/terranova-config/config.php';
    if (!is_file($path)) {
        http_response_code(500);
        exit('config missing');
    }
    return require $path;
}

function tn_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $c = tn_config();
    $dsn = "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function tn_log(string $event, string $detail = ''): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $st = tn_db()->prepare(
            'INSERT INTO access_log (event, detail, ip, at) VALUES (?,?,?,NOW())'
        );
        $st->execute([$event, mb_substr($detail, 0, 250), $ip]);
    } catch (Throwable $e) { /* лог не должен ронять запрос */ }
}

// Уведомление в Telegram — БЕЗ персональных данных.
// Только номер заявки, тип и ссылка в админку.
function tn_notify_telegram(int $appId): void {
    $c = tn_config();
    if (empty($c['telegram_token']) || empty($c['telegram_chat_id'])) return;
    $text = "Новая заявка № {$appId} на terranov.ru\nОткрыть: https://terranov.ru/admin/";
    $url = "https://api.telegram.org/bot{$c['telegram_token']}/sendMessage";
    $data = http_build_query(['chat_id' => $c['telegram_chat_id'], 'text' => $text]);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $data,
        'timeout' => 5,
    ]]);
    @file_get_contents($url, false, $ctx);
}
