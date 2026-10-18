<?php

declare(strict_types=1);

namespace Tests\Fixtures\Syntax;

use Composer\InstalledVersions;

/** Temporary Composer installation exercised in fresh processes to isolate the loader cache. */
final class DiscoveryProject
{
    public readonly string $directory;
    /** @var list<string> */
    private array $files = [];
    /** @var list<string> */
    private array $directories = [];

    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/comprehend-files-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->directories[] = $this->directory;
        $this->write('dependency/.keep', '');
    }

    /** @param string|array<string, mixed> $contents */
    public function write(string $relative, string|array $contents): void
    {
        $file = $this->directory . '/' . $relative;
        $missing = [];
        for ($parent = dirname($file); !is_dir($parent); $parent = dirname($parent)) {
            $missing[] = $parent;
        }
        foreach (array_reverse($missing) as $directory) {
            mkdir($directory);
            $this->directories[] = $directory;
        }
        file_put_contents($file, is_array($contents) ? json_encode($contents, JSON_THROW_ON_ERROR) : $contents);
        if (!in_array($file, $this->files, true)) {
            $this->files[] = $file;
        }
    }

    /** @return array{status: int, output: string} */
    public function run(string $code): array
    {
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $installation = [
            'root' => ['name' => 'fixture/root', 'install_path' => $this->directory],
            'versions' => [
                'fixture/dependency' => ['install_path' => $this->directory . '/dependency'],
                // The same resolved root must not be discovered twice.
                'fixture/root-alias' => ['install_path' => $this->directory . '/.'],
            ],
        ];
        $this->write('check.php', '<?php require ' . var_export($autoload, true) . ';'
            . InstalledVersions::class . '::reload(' . var_export($installation, true) . ');' . $code);
        $output = [];
        $status = 0;
        exec(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->directory . '/check.php') . ' 2>&1',
            $output,
            $status,
        );

        return ['status' => $status, 'output' => implode("\n", $output)];
    }

    public function clean(): void
    {
        foreach (array_reverse($this->files) as $file) {
            unlink($file);
        }
        foreach (array_reverse($this->directories) as $directory) {
            rmdir($directory);
        }
    }
}
