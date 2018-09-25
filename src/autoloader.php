<?php

spl_autoload_register(function ($class) {
    $file = __DIR__ . DIRECTORY_SEPARATOR . $class . '.php';
    if (!class_exists($class) && is_file($file)) {
        /** @noinspection PhpIncludeInspection */
        require_once $file;
    }
});