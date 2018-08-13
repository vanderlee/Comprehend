<?php

namespace vanderlee\comprehend\library;

class Library
{
    private static $map = null;

    private static $instance = null;

    /**
     * @var array
     */
    private static $classes = [];

    /**
     * @param $name
     * @return array
     * @throws \Exception
     */
    private static function getClass($name)
    {
        if (self::$map === null) {
            self::$map = require_once realpath(__DIR__ . '/../../../../env/library.php');
        }

        if (!isset(self::$map[$name])) {
            throw new \Exception("No ruleset available for `{$name}`");
        }

        $class = self::$map[$name];

        if (!isset(self::$classes[$class])) {
            self::$classes[$class] = new $class;
        }

        return self::$classes[$class];
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function __get($name)
    {
        return self::getClass($name);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->getClass($name);
    }
}