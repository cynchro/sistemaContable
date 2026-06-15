<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Override env for tests — phpunit.xml env vars take precedence
$_ENV['APP_ENV']   = $_ENV['APP_ENV'] ?? 'testing';
$_ENV['APP_DEBUG'] = 'true';

App\Support\Config::setPath(dirname(__DIR__) . '/config');
