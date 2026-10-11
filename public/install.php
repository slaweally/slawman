<?php
// Slawman kurulum sihirbazı. Kurulum bitince kendini siler ve slw-content/install.lock yazar.
declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;

$root = dirname(__DIR__);
$lock = $root.'/slw-content/install.lock';
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);

if (is_file($lock)) {
    http_response_code(403);
    exit('Slawman zaten kurulu. Güvenlik için bu dosyayı (public/install.php) silin.');
}

session_start();
$_SESSION['t'] ??= bin2hex(random_bytes(16));

// --- Gereksinimler ---
$dirs = ['slw-content/cache', 'slw-content/log', 'slw-content/sessions', 'slw-content/uploads', 'slw-content/tmp',
    'slw-content/import-temp', 'slw-content/import-jobs', 'slw-content/backup', 'slw-content/share'];
foreach ($dirs as $d) {
    if (!is_dir("$root/$d")) {
        @mkdir("$root/$d", 0775, true);
    }
}
$checks = [
    'PHP >= 8.4 (şu an '.PHP_VERSION.')' => PHP_VERSION_ID >= 80400,
    'slw-content/system/vendor mevcut' => is_file($root.'/slw-content/system/vendor/autoload_runtime.php'),
    'public/build mevcut' => is_file($root.'/public/build/manifest.json'),
    'Kök dizin yazılabilir (.env.local için)' => is_writable($root),
];
foreach (['ctype', 'iconv', 'mbstring', 'pdo_mysql', 'xml', 'dom', 'fileinfo', 'json', 'tokenizer'] as $e) {
    $checks["PHP eklentisi: $e"] = extension_loaded($e);
}
foreach ($dirs as $d) {
    $checks["Yazılabilir: $d"] = is_writable("$root/$d");
}
$ready = !in_array(false, $checks, true);

$v = ['db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'site_url' => '',
    'site_title' => 'Slawman', 'admin_prefix' => 'backstage', 'username' => '', 'email' => ''];
$errors = [];
$log = '';

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim((string) ($_POST[$k] ?? $v[$k]));
    }
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');
    $v['site_url'] = rtrim($v['site_url'], '/');

    if (!hash_equals($_SESSION['t'], (string) ($_POST['t'] ?? ''))) {
        $errors[] = 'Oturum süresi doldu, sayfayı yenileyin.';
    }
    if (!preg_match('#^https?://[^\s/]+#', $v['site_url'])) {
        $errors[] = 'Site adresi http:// veya https:// ile başlamalı.';
    }
    if (!preg_match('/^[a-z0-9-]{3,32}$/', $v['admin_prefix'])) {
        $errors[] = 'Yönetim yolu 3-32 karakter, küçük harf/rakam/tire olmalı.';
    }
    if ($v['username'] === '' || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir admin kullanıcı adı ve e-posta girin.';
    }
    if (mb_strlen($pass) < 12 || $pass !== (string) ($_POST['password2'] ?? '')) {
        $errors[] = 'Parola en az 12 karakter olmalı ve iki alan eşleşmeli.';
    }
    if (!preg_match('/^[\w.-]+$/', $v['db_host']) || !ctype_digit($v['db_port']) || !preg_match('/^[\w$-]+$/', $v['db_name'])) {
        $errors[] = 'Veritabanı sunucu/port/ad geçersiz.';
    }

    $serverVersion = '8.0';
    if (!$errors) {
        try {
            $pdo = new PDO("mysql:host={$v['db_host']};port={$v['db_port']};dbname={$v['db_name']};charset=utf8mb4",
                $v['db_user'], $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $serverVersion = stripos($ver, 'mariadb') !== false ? (preg_replace('/^5\.5\.5-/', '', $ver).'-MariaDB') : implode('.', array_slice(explode('.', $ver), 0, 2));
            $tables = $pdo->query('SHOW TABLES')->fetchAll();
            if ($tables) {
                $errors[] = 'Veritabanı boş değil. Boş bir veritabanı kullanın.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Veritabanına bağlanılamadı: '.$e->getMessage();
        }
    }

    if (!$errors) {
        $q = static fn (string $s): string => '"'.addcslashes($s, '"\\$').'"';
        $dsn = sprintf('mysql://%s:%s@%s:%s/%s?serverVersion=%s&charset=utf8mb4', rawurlencode($v['db_user']),
            rawurlencode($dbPass), $v['db_host'], $v['db_port'], $v['db_name'], rawurlencode($serverVersion));
        $env = implode("\n", [
            'APP_ENV=prod',
            'APP_SECRET='.bin2hex(random_bytes(32)),
            'DATABASE_URL='.$q($dsn),
            'DEFAULT_URI='.$q($v['site_url']),
            'SITE_URL='.$q($v['site_url']),
            'SITE_TITLE='.$q($v['site_title']),
            'SITE_EMAIL='.$q($v['email']),
            'ADMIN_PREFIX='.$v['admin_prefix'],
            '',
        ]);
        if (file_put_contents($root.'/.env.local', $env, LOCK_EX) === false) {
            $errors[] = '.env.local yazılamadı.';
        } else {
            @chmod($root.'/.env.local', 0600);
            try {
                require $root.'/slw-content/system/vendor/autoload.php';
                (new Dotenv())->bootEnv($root.'/.env');
                // Önceki başarısız denemeden kalan derlenmiş container'ı at
                $rm = static function (string $d) use (&$rm): void {
                    foreach (glob($d.'/{,.}[!.]*', GLOB_BRACE) ?: [] as $p) {
                        is_dir($p) ? $rm($p) : @unlink($p);
                    }
                    @rmdir($d);
                };
                $rm($root.'/slw-content/cache/prod');
                $out = new BufferedOutput();
                foreach ([
                    ['doctrine:migrations:migrate', '--no-interaction' => true],
                    ['app:seed-data'],
                    ['app:create-admin', '--username' => $v['username'], '--email' => $v['email'], '--password' => $pass],
                ] as $cmd) {
                    // Her komut taze bir kernel ile çalışır: migration sonrası Doctrine bağlantı durumu temiz olmalı.
                    $kernel = new Kernel('prod', false);
                    $kernel->boot();
                    $app = new Application($kernel);
                    $app->setAutoExit(false);
                    $cmd['command'] = array_shift($cmd);
                    $in = new ArrayInput($cmd);
                    $in->setInteractive(false);
                    if ($app->run($in, $out) !== 0) {
                        throw new RuntimeException("Komut başarısız: {$cmd['command']}\n".$out->fetch());
                    }
                    $kernel->shutdown();
                }
                file_put_contents($lock, date('c'));
                @unlink(__FILE__);
                header('Location: '.$v['site_url'].'/'.$v['admin_prefix']);
                exit;
            } catch (Throwable $e) {
                @unlink($root.'/.env.local');
                $errors[] = 'Kurulum hatası: '.$e->getMessage();
            }
        }
    }
}

$f = static fn (string $k, string $label, string $type = 'text', string $extra = ''): string => sprintf(
    '<label>%s<input name="%s" type="%s" value="%s" required %s></label>', $h($label), $k, $type,
    $type === 'password' ? '' : $h($v[$k] ?? ''), $extra);
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Slawman Kurulum</title>
<style>
body{font:16px system-ui,sans-serif;background:#f4f5f7;color:#222;margin:0;padding:24px 16px}
main{max-width:560px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 1px 4px #0002}
h1{margin-top:0}h2{font-size:1.05rem;margin:1.5rem 0 .5rem;border-bottom:1px solid #ddd;padding-bottom:.25rem}
label{display:block;margin:.6rem 0 0;font-size:.9rem}input{display:block;width:100%;box-sizing:border-box;padding:.5rem;margin-top:.2rem;font:inherit}
button{margin-top:1.2rem;padding:.7rem 1.4rem;font:inherit;background:#206bc4;color:#fff;border:0;border-radius:4px;cursor:pointer}
.err{background:#fdecea;color:#8a1c14;padding:.6rem .9rem;border-radius:4px;margin:.5rem 0;white-space:pre-wrap}
ul{list-style:none;padding:0}li.ok::before{content:"✔ ";color:#2b8a3e}li.no::before{content:"✘ ";color:#c92a2a}
</style></head><body><main>
<h1>Slawman Kurulum</h1>
<h2>Gereksinimler</h2>
<ul><?php foreach ($checks as $label => $ok) { echo '<li class="', $ok ? 'ok' : 'no', '">', $h($label), '</li>'; } ?></ul>
<?php foreach ($errors as $e) { echo '<div class="err">', $h($e), '</div>'; } ?>
<?php if ($ready) { ?>
<form method="post" autocomplete="off">
<input type="hidden" name="t" value="<?= $h($_SESSION['t']) ?>">
<h2>Veritabanı (MySQL/MariaDB, boş olmalı)</h2>
<?= $f('db_host', 'Sunucu') ?><?= $f('db_port', 'Port', 'number') ?><?= $f('db_name', 'Veritabanı adı') ?><?= $f('db_user', 'Kullanıcı') ?>
<label>Parola<input name="db_pass" type="password"></label>
<h2>Site</h2>
<?= $f('site_url', 'Site adresi (örn. https://ornek.com)', 'url') ?><?= $f('site_title', 'Site başlığı') ?><?= $f('admin_prefix', 'Yönetim paneli yolu (örn. backstage)') ?>
<h2>Yönetici hesabı</h2>
<?= $f('username', 'Kullanıcı adı') ?><?= $f('email', 'E-posta', 'email') ?>
<label>Parola (en az 12 karakter)<input name="password" type="password" minlength="12" required></label>
<label>Parola (tekrar)<input name="password2" type="password" minlength="12" required></label>
<button>Kur</button>
</form>
<?php } else { ?><p>Kırmızı maddeleri düzeltip sayfayı yenileyin.</p><?php } ?>
</main></body></html>
