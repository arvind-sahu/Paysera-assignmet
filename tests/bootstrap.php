<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

// PHPUnit env must win over Docker-injected variables for isolated test credentials.
$_SERVER['API_KEYS'] = $_ENV['API_KEYS'] = 'test-api-key';
putenv('API_KEYS=test-api-key');
