<?php

$publicDir = dirname(__DIR__, 2).'/public';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (is_string($path)) {
    $file = realpath($publicDir.$path);
    $publicRealDir = realpath($publicDir);

    if (
        $file !== false
        && $publicRealDir !== false
        && str_starts_with($file, $publicRealDir.DIRECTORY_SEPARATOR)
        && is_file($file)
    ) {
        return false;
    }
}

require $publicDir.'/index.php';