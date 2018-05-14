<?php

spl_autoload_register(function ($class) {
	if (!class_exists($class) && is_file(__DIR__ . '/' . $class . '.php')) {
		require __DIR__ . '/' . $class . '.php';
	}
});
