<?php

require_once __DIR__ . '/../src/autoloader.php';

require_once __DIR__ . '/TestCase.php';

// backward compatibility
if (!class_exists('PHPUnit\Framework\TestCase') && class_exists('PHPUnit_Framework_TestCase')) {
	class_alias('PHPUnit_Framework_TestCase', 'PHPUnit\Framework\TestCase');
}
