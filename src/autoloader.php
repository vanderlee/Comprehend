<?php

spl_autoload_register(function ($class) {
    static $prefix = 'Vanderlee\\Comprehend\\';
    if (stripos($class, $prefix) === 0) {
        $file = __DIR__ . DIRECTORY_SEPARATOR . str_ireplace($prefix, '', $class) . '.php';
        if (is_file($file)) {
            /** @noinspection PhpIncludeInspection */
            require_once $file;
        }
    }
});