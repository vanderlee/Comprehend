<?php /** @noinspection PhpIncludeInspection */

namespace vanderlee\comprehend\library;

class Library
{
    /**
     * @var Library
     */
    private static $instances = [];

    /**
     * Map of [ symbolic name => class name ]
     * @var string[]
     */
    private $map = null;

    /**
     * Map of [ class name => class instance ]
     * @var string[]
     */
    private $classes = [];

    private function __construct($map)
    {
        $this->map = $map;
    }

    /**
     * @param $name
     * @return array
     * @throws \Exception
     */
    private function getClass($name)
    {
        if (!isset($this->map[$name])) {
            throw new \Exception("No ruleset available for `{$name}`");
        }

        $class = $this->map[$name];

        if (!class_exists($class)) {
            throw new \Exception("Ruleset `{$class}` not found");
        }

        if (!isset($this->classes[$class])) {
            $this->classes[$class] = new $class;
        }

        return $this->classes[$class];
    }

    public static function getInstance($configFile = null)
    {
        $configFile = $configFile ?: realpath(__DIR__ . '/../../../../env/library.php');

        if (!isset(self::$instances[$configFile])) {
            self::$instances[$configFile] = new Library(require $configFile);
        }

        return self::$instances[$configFile];
    }

    public function __get($name)
    {
        return $this->getClass($name);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->getClass($name);
    }
}