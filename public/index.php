<?php

use App\Kernel;

$root = dirname(__DIR__);
// Kurulum yapılmadıysa sihirbaza yönlendir (aksi halde APP_SECRET/DATABASE_URL eksik olduğundan 500 verir).
if (!is_file($root.'/.env') && !is_file($root.'/.env.local') && is_file(__DIR__.'/install.php')) {
    header('Location: '.rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\').'/install.php');
    exit;
}

require_once dirname(__DIR__).'/slw-content/system/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
