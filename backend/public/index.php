<?php

$app    = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->get(App\Support\Kernel::class);
$kernel->handle();
