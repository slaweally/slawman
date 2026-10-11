<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/slw-content/system/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
