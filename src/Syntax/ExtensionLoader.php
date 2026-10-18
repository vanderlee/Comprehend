<?php

declare(strict_types=1);

namespace Vanderlee\Comprehend\Syntax;

use Closure;
use Composer\InstalledVersions;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
use Throwable;

/** @internal Discovers metadata once and loads providers during authoring or preparation. */
final class ExtensionLoader
{
    private static ?self $default = null;

    /**
     * @var array<string, array{
     *     class: string, package: string, rules: list<string>, fluents: list<string>, provides: list<string>
     * }>
     */
    private array $providers = [];
    /** @var array<string, list<string>> Normalized interface names mapped to provider IDs. */
    private array $capabilities = [];
    /** @var array<string, string> Original interface spelling retained for autoloading and diagnostics. */
    private array $contracts = [];
    /** @var array<string, object> Published only after all declared capabilities have been validated. */
    private array $instances = [];
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
                foreach ($this->providersFromEntry($entry, $context) as $provider) {
                    ['class' => $class, 'rules' => $rules, 'fluents' => $fluents, 'provides' => $provides] = $provider;
                    $id = $package . ':' . strtolower($class);
                    $provider = compact('class', 'package', 'rules', 'fluents', 'provides');
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
                    foreach ($provides as $contract) {
                        $this->capabilities[$contract][] = $id;
                    }
                    foreach (['rules' => $rules, 'fluents' => $fluents] as $kind => $names) {
                        foreach ($names as $name) {
                            $this->index[$kind][$name][] = $id;
                        }
                    }
                }
            }
        }
    }

    /**
     * @return list<array{class: string, rules: list<string>, fluents: list<string>, provides: list<string>}>
     */
    private function providersFromEntry(mixed $entry, string $context): array
    {
        if (!is_array($entry)) {
            throw new RuntimeException('Invalid extension declaration in ' . $context);
        }
        if (array_key_exists('optimizer', $entry) || array_key_exists('provides', $entry)) {
            throw new RuntimeException(
                'Use the providers class-to-interfaces map instead of optimizer/provides in ' . $context,
            );
        }
        $classes = array_key_exists('providers', $entry) ? $entry['providers'] : [];
        if (!is_array($classes) || ($classes !== [] && array_is_list($classes))) {
            throw new RuntimeException('Invalid providers map in ' . $context);
        }
        $providers = [];
        foreach ($classes as $class => $contracts) {
            if (!is_string($class) || $class === '') {
                throw new RuntimeException('Invalid provider class in ' . $context);
            }
            $provides = array_values(array_unique($this->contractsFromMetadata($contracts, $context)));
            sort($provides);
            if ($provides === []) {
                throw new RuntimeException(
                    'Provider must advertise at least one interface in ' . $context . ': ' . $class,
                );
            }
            $key = strtolower(ltrim($class, '\\'));
            if (isset($providers[$key])) {
                throw new RuntimeException('Duplicate provider class in ' . $context . ': ' . $class);
            }
            $providers[$key] = ['class' => $class, 'rules' => [], 'fluents' => [], 'provides' => $provides];
        }
        $syntaxKey = null;
        if (array_key_exists('class', $entry)) {
            $class = $entry['class'];
            if (!is_string($class) || $class === '') {
                throw new RuntimeException('Invalid syntax provider class in ' . $context);
            }
            $rules = self::namesFromMetadata($entry['rules'] ?? null, $context);
            $fluents = self::namesFromMetadata($entry['fluents'] ?? null, $context);
            $syntaxKey = strtolower(ltrim($class, '\\'));
            $provides = $providers[$syntaxKey]['provides'] ?? [];
            $provides[] = strtolower(SyntaxExtension::class);
            $this->contracts[strtolower(SyntaxExtension::class)] = SyntaxExtension::class;
            $provides = array_values(array_unique($provides));
            sort($provides);
            $providers[$syntaxKey] = compact('class', 'rules', 'fluents', 'provides');
        } elseif (array_key_exists('rules', $entry) || array_key_exists('fluents', $entry)) {
            throw new RuntimeException('Syntax rules and fluents require a class in ' . $context);
        }
        foreach ($providers as $key => $provider) {
            if (in_array(strtolower(SyntaxExtension::class), $provider['provides'], true) && $key !== $syntaxKey) {
                throw new RuntimeException('SyntaxExtension requires class, rules and fluents metadata in ' . $context);
            }
        }
        if ($providers === []) {
            throw new RuntimeException('Extension must declare a syntax class or providers in ' . $context);
        }

        return array_values($providers);
    }

    public static function defaults(): self
    {
        return self::$default ??= new self(self::manifests());
    }

    /**
     * @template T of object
     * @param class-string<T> $contract Interface advertised by exactly one provider.
     * @return T
     */
    public function requireOne(string $contract): object
    {
        $ids = $this->providerIds($contract);
        if ($ids === []) {
            throw new RuntimeException('No extension provides ' . $contract);
        }
        if (count($ids) !== 1) {
            throw new RuntimeException('Ambiguous extension capability ' . $contract . ': ' . implode(', ', array_map(
                fn(string $id): string => $id . ' (' . implode(', ', $this->sources[$id]) . ')',
                $ids,
            )));
        }

        return $this->instance($ids[0], $contract);
    }

    /**
     * @template T of object
     * @param class-string<T> $contract
     * @return list<T> Providers in discovery order; consumers own composition and execution order.
     */
    public function all(string $contract): array
    {
        $instances = [];
        foreach ($this->providerIds($contract) as $id) {
            $instances[] = $this->instance($id, $contract);
        }

        return $instances;
    }

    /** @return list<string> */
    private function providerIds(string $contract): array
    {
        if (!interface_exists($contract)) {
            throw new InvalidArgumentException('Extension capability must be an existing interface: ' . $contract);
        }

        return $this->capabilities[strtolower(ltrim($contract, '\\'))] ?? [];
    }

    /**
     * @template T of object
     * @param class-string<T> $contract
     * @return T
     */
    private function instance(string $id, string $contract): object
    {
        $this->load($id);
        $instance = $this->instances[$id];
        if (!$instance instanceof $contract) {
            throw new RuntimeException('Loaded extension ' . $id . ' does not implement ' . $contract);
        }

        return $instance;
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
            $files = [];
            foreach (['/extensions/*.extension.json', '/extensions/*/*.extension.json'] as $pattern) {
                $matches = glob($path . $pattern);
                if ($matches === false) {
                    throw new RuntimeException(
                        'Cannot discover extension manifests in ' . $package . ' (' . $path . ')',
                    );
                }
                $files = array_merge($files, $matches);
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

    /** @return list<string> */
    private function contractsFromMetadata(mixed $contracts, string $context): array
    {
        if (!is_array($contracts) || !array_is_list($contracts)) {
            throw new RuntimeException('Invalid provides list in ' . $context);
        }
        $result = [];
        foreach ($contracts as $contract) {
            if (!is_string($contract) || $contract === '') {
                throw new RuntimeException('Invalid provides interface in ' . $context);
            }
            $name = ltrim($contract, '\\');
            foreach (explode('\\', $name) as $segment) {
                if (!preg_match('/\A[a-z_\x80-\xff][a-z0-9_\x80-\xff]*\z/i', $segment)) {
                    throw new RuntimeException('Invalid provides interface in ' . $context . ': ' . $contract);
                }
            }
            $key = strtolower($name);
            $this->contracts[$key] ??= $name;
            $result[] = $key;
        }

        return $result;
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
        if (isset($this->instances[$id])) {
            return;
        }
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
            foreach ($provider['provides'] as $key) {
                $contract = $this->contracts[$key];
                if (!interface_exists($contract)) {
                    throw new RuntimeException('Declared capability must be an existing interface: ' . $contract);
                }
                if (!$reflection->implementsInterface($contract)) {
                    throw new RuntimeException('Provider must implement ' . $contract);
                }
            }
            $extension = $reflection->newInstance();
            $factories = [];
            $catalog = new Catalog();
            $syntax = in_array(strtolower(SyntaxExtension::class), $provider['provides'], true);
            if ($syntax && $extension instanceof SyntaxExtension) {
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
            }
            $this->loaded[$id] = $factories;
            $this->instances[$id] = $extension;
        } catch (Throwable $error) {
            throw new RuntimeException(
                'Cannot load extension ' . $id . ' in ' . implode(', ', $this->sources[$id])
                . ' providing ' . implode(', ', $this->providers[$id]['provides'])
                . ': ' . $error->getMessage(),
                0,
                $error,
            );
        } finally {
            unset($this->loading[$id]);
        }
    }
}
