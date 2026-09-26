<?php
// Приём заявки с формы: проверка → база → уведомление в Telegram → /thanks/.
declare(strict_types=1);
require __DIR__ . '/_db.php';

function back_with_error(string $msg): void {
    header('Location: /?form=error#zayavka');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /#zayavka');
    exit;
}

// --- Простая защита от частых отправок: не чаще 3 в минуту с одного IP ---
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $st = tn_db()->prepare(
        "SELECT COUNT(*) FROM access_log WHERE event='submit' AND ip=? AND at > (NOW() - INTERVAL 1 MINUTE)"
    );
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 3) {
        back_with_error('too_many');
    }
} catch (Throwable $e) { /* если лог недоступен — пропускаем проверку */ }

// --- Поля ---
$name     = trim((string)($_POST['name'] ?? ''));
$phone    = trim((string)($_POST['phone'] ?? ''));
$telegram = trim((string)($_POST['telegram'] ?? ''));
$email    = trim((string)($_POST['email'] ?? ''));
$region   = trim((string)($_POST['region'] ?? ''));
$message  = trim((string)($_POST['message'] ?? ''));
$goals    = $_POST['goal'] ?? [];
$goals    = is_array($goals) ? implode(', ', array_map('strval', $goals)) : '';
$consent_pd   = !empty($_POST['consent_pd']);
$consent_mail = !empty($_POST['consent_mail']);

// Нормализуем «+7 » (предзаполнение) как пустой телефон
if (preg_replace('/\D+/', '', $phone) === '7' || preg_replace('/\D+/', '', $phone) === '') {
    $phone = '';
}

// --- Валидация ---
if ($name === '') back_with_error('no_name');
$hasContact = ($phone !== '' || $telegram !== '' || $email !== '');
if (!$hasContact) back_with_error('no_contact');
if (!$consent_pd) back_with_error('no_consent');

// --- Запись в базу ---
try {
    $db = tn_db();
    $db->beginTransaction();

    $st = $db->prepare(
        'INSERT INTO people (name, contact_phone, contact_telegram, contact_email, source, created_at)
         VALUES (?,?,?,?,?,NOW())'
    );
    $st->execute([
        mb_substr($name, 0, 255),
        $phone !== '' ? mb_substr($phone, 0, 64) : null,
        $telegram !== '' ? mb_substr($telegram, 0, 128) : null,
        $email !== '' ? mb_substr($email, 0, 255) : null,
        'site',
    ]);
    $personId = (int)$db->lastInsertId();

    $st = $db->prepare(
        'INSERT INTO applications (person_id, goals, region, message, status, created_at)
         VALUES (?,?,?,?,\'new\',NOW())'
    );
    $st->execute([
        $personId,
        mb_substr($goals, 0, 255),
        mb_substr($region, 0, 255),
        mb_substr($message, 0, 5000),
    ]);
    $appId = (int)$db->lastInsertId();

    // Согласие на обработку — обязательное
    $st = $db->prepare(
        'INSERT INTO consents (person_id, purpose, given, doc_version, page_url, given_at)
         VALUES (?,?,?,?,?,NOW())'
    );
    $st->execute([$personId, 'processing', 1, '2026-09', '/#zayavka']);

    // Согласие на рассылку — по галочке
    $st->execute([$personId, 'marketing', $consent_mail ? 1 : 0, '2026-09', '/#zayavka']);

    $db->commit();

    tn_log('submit', 'app#' . $appId);
    tn_notify_telegram($appId);

    header('Location: /thanks/');
    exit;
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    tn_log('submit_error', substr($e->getMessage(), 0, 200));
    back_with_error('db');
}
