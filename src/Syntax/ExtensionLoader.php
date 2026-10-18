<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Syntax;

use Closure;
use Composer\InstalledVersions;
use ReflectionClass;
use RuntimeException;
use Throwable;

/** @internal Discovers metadata once and loads providers only during grammar authoring. */
final class ExtensionLoader
{
    private static ?self $default = null;

    /** @var array<string, array{class: string, package: string, rules: list<string>, fluents: list<string>}> */
    private array $providers = [];
    /** @var array<string, list<string>> Manifest locations retained for deferred loading errors. */
    private array $sources = [];
    /** @var array<string, array<string, list<string>>> */
    private array $index = ['rules' => [], 'fluents' => []];
    /** @var array<string, array<string, array<string, Closure>>> */
    private array $loaded = [];
    /** @var array<string, true> */
    private array $loading = [];

    /**
     * Package manifests are injectable so discovery can be tested without changing Composer state.
     * @param iterable<string, array<string, mixed>> $manifests
     */
    public function __construct(iterable $manifests)
    {
        foreach ($manifests as $package => $manifest) {
            $source = $manifest['_comprehend_manifest_path'] ?? $package;
            if (!is_string($source)) {
                throw new RuntimeException('Invalid manifest path in ' . $package);
            }
            $context = $package . ' (' . $source . ')';
            $extra = array_key_exists('extra', $manifest) ? $manifest['extra'] : [];
            if (!is_array($extra)) {
                throw new RuntimeException('Invalid extension metadata in ' . $context);
            }
            $metadata = array_key_exists('comprehend', $extra) ? $extra['comprehend'] : [];
            if (!is_array($metadata)) {
                throw new RuntimeException('Invalid extension metadata in ' . $context);
            }
            $entries = array_key_exists('extensions', $metadata) ? $metadata['extensions'] : [];
            if (!is_array($entries) || !array_is_list($entries)) {
                throw new RuntimeException('Invalid extension metadata in ' . $context);
            }
            foreach ($entries as $entry) {
                if (!is_array($entry) || !is_string($entry['class'] ?? null) || $entry['class'] === '') {
                    throw new RuntimeException('Invalid extension provider in ' . $context);
                }
                $class = $entry['class'];
                $rules = self::namesFromMetadata($entry['rules'] ?? null, $context);
                $fluents = self::namesFromMetadata($entry['fluents'] ?? null, $context);
                $id = $package . ':' . strtolower($class);
                $provider = compact('class', 'package', 'rules', 'fluents');
                if (isset($this->providers[$id])) {
                    if ($this->providers[$id] !== $provider) {
                        throw new RuntimeException(
                            'Conflicting extension declarations: ' . $id . ' in '
                            . implode(', ', [...$this->sources[$id], $source]),
                        );
                    }
                    $this->sources[$id][] = $source;
                    continue;
                }
                $this->providers[$id] = $provider;
                $this->sources[$id] = [$source];
                foreach (['rules' => $rules, 'fluents' => $fluents] as $kind => $names) {
                    foreach ($names as $name) {
                        $this->index[$kind][$name][] = $id;
                    }
                }
            }
        }
    }

    public static function defaults(): self
    {
        return self::$default ??= new self(self::manifests());
    }

    /** @return iterable<string, array<string, mixed>> */
    private static function manifests(): iterable
    {
        $seen = [];
        foreach (self::packageRoots() as $package => $path) {
            $path = realpath($path);
            if ($path === false || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $composerFile = $path . '/composer.json';
            if (is_file($composerFile)) {
                yield $package => self::readManifest($composerFile, $package);
            }
            $files = glob($path . '/extensions/*.extension.json');
            if ($files === false) {
                throw new RuntimeException('Cannot discover extension manifests in ' . $package . ' (' . $path . ')');
            }
            sort($files, SORT_STRING);
            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $entry = self::readManifest($file, $package);
                yield $package => [
                    '_comprehend_manifest_path' => $file,
                    'extra' => ['comprehend' => ['extensions' => [$entry]]],
                ];
            }
        }
    }

    /** @return iterable<string, string> */
    private static function packageRoots(): iterable
    {
        yield 'vanderlee/comprehend' => dirname(__DIR__, 2);
        if (!class_exists(InstalledVersions::class)) {
            return;
        }
        foreach (InstalledVersions::getAllRawData() as $installation) {
            $root = $installation['root'];
            yield $root['name'] => $root['install_path'];
            foreach ($installation['versions'] as $name => $package) {
                if (isset($package['install_path'])) {
                    yield $name => $package['install_path'];
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private static function readManifest(string $file, string $package): array
    {
        try {
            $contents = @file_get_contents($file);
            if ($contents === false) {
                throw new RuntimeException('File is unreadable');
            }
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !str_starts_with(ltrim($contents), '{')) {
                throw new RuntimeException('Expected a JSON object');
            }
        } catch (Throwable $error) {
            throw new RuntimeException('Cannot read extension metadata in ' . $package . ' (' . $file . ')', 0, $error);
        }
        $manifest['_comprehend_manifest_path'] = $file;

        return $manifest;
    }

    /** @return list<string> */
    private static function namesFromMetadata(mixed $names, string $package): array
    {
        if (!is_array($names) || !array_is_list($names)) {
            throw new RuntimeException('Invalid extension names in ' . $package);
        }
        $result = [];
        foreach ($names as $name) {
            if (!is_string($name) || !preg_match('/\A[a-z][a-z0-9_]*\z/i', $name)) {
                throw new RuntimeException('Invalid extension name in ' . $package);
            }
            $result[] = strtolower($name);
        }
        sort($result);

        return array_values(array_unique($result));
    }

    public function rule(string $name): ?Closure
    {
        return $this->resolve('rules', $name);
    }

    public function fluent(string $name): ?Closure
    {
        return $this->resolve('fluents', $name);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_values(array_unique([
            ...array_keys($this->index['rules']),
            ...array_keys($this->index['fluents']),
        ]));
    }

    private function resolve(string $kind, string $name): ?Closure
    {
        $name = strtolower($name);
        $providers = $this->index[$kind][$name] ?? [];
        if ($providers === []) {
            return null;
        }
        if (count($providers) > 1) {
            throw new RuntimeException(
                'Ambiguous extension ' . $kind . ' name ' . $name . ': ' . implode(', ', $providers),
            );
        }
        $id = $providers[0];
        if (!isset($this->loaded[$id])) {
            $this->load($id);
        }

        return $this->loaded[$id][$kind][$name];
    }

    private function load(string $id): void
    {
        if (isset($this->loading[$id])) {
            throw new RuntimeException('Recursive extension loading: ' . $id);
        }
        $this->loading[$id] = true;
        try {
            $provider = $this->providers[$id];
            if (!class_exists($provider['class'])) {
                throw new RuntimeException('Provider class does not exist');
            }
            $reflection = new ReflectionClass($provider['class']);
            if (!$reflection->implementsInterface(SyntaxExtension::class)) {
                throw new RuntimeException('Provider must implement SyntaxExtension');
            }
            $extension = $reflection->newInstance();
            if (!$extension instanceof SyntaxExtension) {
                throw new RuntimeException('Invalid extension instance');
            }
            $factories = [];
            $catalog = new Catalog();
            foreach (['rules', 'fluents'] as $kind) {
                $factories[$kind] = [];
                foreach ($extension->$kind() as $name => $factory) {
                    // Reuse explicit registration validation, including reserved names and callables.
                    $catalog = $kind === 'rules'
                        ? $catalog->defineRule($name, $factory)
                        : $catalog->defineFluent($name, $factory);
                    $factories[$kind][strtolower($name)] = Closure::fromCallable($factory);
                }
                $names = array_keys($factories[$kind]);
                sort($names);
                if ($names !== $provider[$kind]) {
                    throw new RuntimeException('Advertised ' . $kind . ' do not match provider registrations');
                }
            }
            $this->loaded[$id] = $factories;
        } catch (Throwable $error) {
            throw new RuntimeException(
                'Cannot load extension ' . $id . ' in ' . implode(', ', $this->sources[$id])
                . ': ' . $error->getMessage(),
                0,
                $error,
            );
        } finally {
            unset($this->loading[$id]);
        }
    }
}
