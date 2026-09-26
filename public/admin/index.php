<?php
// Админка Terra Nova: вход по паролю, список заявок, смена статуса, удаление (152-ФЗ).
declare(strict_types=1);
require __DIR__ . '/../api/_db.php';

session_start();
$cfg = tn_config();

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// --- CSRF ---
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

// --- Выход ---
if (isset($_GET['logout'])) {
    tn_log('admin_logout', '');
    session_destroy();
    header('Location: /admin/');
    exit;
}

// --- Вход ---
$loginError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['password']) && !isset($_SESSION['auth'])) {
    if (password_verify((string)$_POST['password'], $cfg['admin_hash'])) {
        $_SESSION['auth'] = true;
        session_regenerate_id(true);
        tn_log('admin_login', 'ok');
        header('Location: /admin/');
        exit;
    } else {
        $loginError = 'Неверный пароль';
        tn_log('admin_login', 'fail');
    }
}

$authed = !empty($_SESSION['auth']);

// --- Действия (только для авторизованных) ---
if ($authed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_ok()) {
    $db = tn_db();
    if (($_POST['action'] ?? '') === 'status' && isset($_POST['app_id'], $_POST['status'])) {
        $allowed = ['new','contacted','in_work','declined','done'];
        if (in_array($_POST['status'], $allowed, true)) {
            $st = $db->prepare('UPDATE applications SET status=? WHERE id=?');
            $st->execute([$_POST['status'], (int)$_POST['app_id']]);
            tn_log('status_change', 'app#' . (int)$_POST['app_id'] . ' -> ' . $_POST['status']);
        }
    }
    if (($_POST['action'] ?? '') === 'delete' && isset($_POST['person_id'])) {
        // Удаление персональных данных по запросу субъекта (152-ФЗ)
        $pid = (int)$_POST['person_id'];
        $db->prepare('DELETE FROM consents WHERE person_id=?')->execute([$pid]);
        $db->prepare('DELETE FROM applications WHERE person_id=?')->execute([$pid]);
        $db->prepare('DELETE FROM people WHERE id=?')->execute([$pid]);
        tn_log('dsr_delete', 'person#' . $pid);
    }
    header('Location: /admin/');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
?><!DOCTYPE html>
<html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Админка · Terra Nova</title>
<style>
  body{font:15px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#F4F3EE;color:#1B221C}
  .wrap{max-width:1100px;margin:0 auto;padding:24px}
  h1{font-size:22px}
  .top{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #D2D6C9;padding-bottom:12px;margin-bottom:20px}
  a{color:#2C6382}
  .card{background:#fff;border:1px solid #D2D6C9;border-radius:12px;padding:16px;margin-bottom:14px}
  .meta{color:#5C6659;font-size:13px}
  .row{display:flex;flex-wrap:wrap;gap:8px 24px;margin:8px 0}
  .row b{font-weight:600}
  .st{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;background:#E7EFE2}
  .st.new{background:#F2E2D0}
  form.inline{display:inline}
  select,button,input{font:inherit;padding:8px 12px;border:1px solid #D2D6C9;border-radius:8px;background:#fff}
  button{cursor:pointer;background:#2C6382;color:#fff;border-color:#2C6382}
  button.danger{background:#9E3B26;border-color:#9E3B26}
  .login{max-width:360px;margin:80px auto;background:#fff;border:1px solid #D2D6C9;border-radius:12px;padding:28px}
  .err{color:#9E3B26;font-size:14px;margin-top:8px}
  .empty{color:#5C6659;padding:40px;text-align:center}
  .dash{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px}
  .stat{background:#fff;border:1px solid #D2D6C9;border-radius:12px;padding:16px}
  .stat b{display:block;font-size:28px;line-height:1;color:#1E3D2B}
  .stat.accent b{color:#B2622D}
  .stat span{display:block;font-size:12px;color:#5C6659;margin-top:6px;text-transform:uppercase;letter-spacing:.04em}
  .dash-sub{color:#5C6659;font-size:13px;margin:-12px 0 20px}
</style></head><body>
<div class="wrap">
<?php if (!$authed): ?>
  <form class="login" method="post">
    <h1>Вход в админку</h1>
    <p class="meta">Terra Nova · заявки</p>
    <input type="password" name="password" placeholder="Пароль" style="width:100%;margin:12px 0" autofocus required>
    <button type="submit" style="width:100%">Войти</button>
    <?php if ($loginError): ?><p class="err"><?= h($loginError) ?></p><?php endif; ?>
  </form>
<?php else:
  $db = tn_db();
  $apps = $db->query(
    'SELECT a.id app_id, a.goals, a.region, a.message, a.status, a.created_at,
            p.id person_id, p.name, p.contact_phone, p.contact_telegram, p.contact_email,
            (SELECT given FROM consents c WHERE c.person_id=p.id AND c.purpose="marketing" ORDER BY c.id DESC LIMIT 1) mail
     FROM applications a JOIN people p ON p.id=a.person_id
     ORDER BY a.id DESC'
  )->fetchAll();
  $csrf = h($_SESSION['csrf']);
  $labels = ['new'=>'Новая','contacted'=>'Связались','in_work'=>'В работе','declined'=>'Отказ','done'=>'Готово'];

  // --- Сводка для дашборда ---
  $total = count($apps);
  $by = ['new'=>0,'contacted'=>0,'in_work'=>0,'declined'=>0,'done'=>0];
  $today = 0; $week = 0; $mail = 0;
  $todayStr = date('Y-m-d');
  $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
  foreach ($apps as $a) {
    if (isset($by[$a['status']])) $by[$a['status']]++;
    if (strpos((string)$a['created_at'], $todayStr) === 0) $today++;
    if ($a['created_at'] >= $weekAgo) $week++;
    if ($a['mail']) $mail++;
  }
?>
  <div class="top">
    <h1>Дашборд · Terra Nova</h1>
    <a href="/admin/?logout=1">Выйти</a>
  </div>

  <div class="dash">
    <div class="stat accent"><b><?= $total ?></b><span>Всего заявок</span></div>
    <div class="stat"><b><?= $today ?></b><span>Сегодня</span></div>
    <div class="stat"><b><?= $week ?></b><span>За 7 дней</span></div>
    <div class="stat"><b><?= $by['new'] ?></b><span>Новые</span></div>
    <div class="stat"><b><?= $by['in_work'] ?></b><span>В работе</span></div>
    <div class="stat"><b><?= $by['done'] ?></b><span>Готово</span></div>
    <div class="stat"><b><?= $by['declined'] ?></b><span>Отказ</span></div>
    <div class="stat"><b><?= $mail ?></b><span>Подписка на рассылку</span></div>
  </div>
  <p class="dash-sub">Ниже — все заявки. Меняйте статус в выпадающем списке; «Удалить данные» — по запросу человека (152-ФЗ).</p>
  <?php if (!$apps): ?>
    <div class="empty">Пока заявок нет.</div>
  <?php endif; ?>
  <?php foreach ($apps as $a): ?>
    <div class="card">
      <div class="row">
        <span><b>Заявка № <?= (int)$a['app_id'] ?></b></span>
        <span class="st <?= h($a['status']) ?>"><?= h($labels[$a['status']] ?? $a['status']) ?></span>
        <span class="meta"><?= h($a['created_at']) ?></span>
      </div>
      <div class="row">
        <span><b>Имя:</b> <?= h($a['name']) ?></span>
        <?php if ($a['contact_phone']): ?><span><b>Телефон:</b> <?= h($a['contact_phone']) ?></span><?php endif; ?>
        <?php if ($a['contact_telegram']): ?><span><b>Telegram:</b> <?= h($a['contact_telegram']) ?></span><?php endif; ?>
        <?php if ($a['contact_email']): ?><span><b>E-mail:</b> <?= h($a['contact_email']) ?></span><?php endif; ?>
      </div>
      <div class="row">
        <?php if ($a['goals']): ?><span><b>Цели:</b> <?= h($a['goals']) ?></span><?php endif; ?>
        <?php if ($a['region']): ?><span><b>Регион и бюджет:</b> <?= h($a['region']) ?></span><?php endif; ?>
        <span class="meta">Рассылка: <?= $a['mail'] ? 'да' : 'нет' ?></span>
      </div>
      <?php if ($a['message']): ?><div class="row"><span><b>Сообщение:</b> <?= nl2br(h($a['message'])) ?></span></div><?php endif; ?>
      <div class="row">
        <form class="inline" method="post">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="app_id" value="<?= (int)$a['app_id'] ?>">
          <select name="status" onchange="this.form.submit()">
            <?php foreach ($labels as $k=>$v): ?>
              <option value="<?= $k ?>" <?= $a['status']===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <form class="inline" method="post" onsubmit="return confirm('Удалить данные этого человека безвозвратно? (запрос по 152-ФЗ)')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="person_id" value="<?= (int)$a['person_id'] ?>">
          <button class="danger" type="submit">Удалить данные</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div></body></html>
