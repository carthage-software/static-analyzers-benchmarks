<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Setup;

use CarthageSoftware\ToolChainBenchmarks\Configuration;
use CarthageSoftware\ToolChainBenchmarks\Configuration\Tool;
use CarthageSoftware\ToolChainBenchmarks\Configuration\ToolInstance;
use CarthageSoftware\ToolChainBenchmarks\Support\Output;
use Psl\File;
use Psl\Filesystem;
use Psl\Json;
use Psl\Shell;
use Psl\Str;
use Psl\Vec;

/**
 * Installs each tool version into its own isolated tools/<slug>/ directory,
 * preventing autoloader conflicts between the benchmark suite and the tools.
 */
final readonly class ToolInstaller
{
    /**
     * All tool packages to install.
     * Each entry: [package-short-name, composer-package, version].
     * Mago appears once per version — one install serves fmt + lint + analyze.
     *
     * @var list<array{non-empty-string, non-empty-string, non-empty-string}>
     */
    private const array PACKAGES = [
        ['mago',         'carthage-software/mago',    '1.20.1'],
        ['mago',         'carthage-software/mago',    '1.20.0'],
        ['mago',         'carthage-software/mago',    '1.10.0'],
        ['mago',         'carthage-software/mago',    '1.7.0'],
        ['pretty-php',   'lkrms/pretty-php',          '0.4.95'],
        ['php-cs-fixer', 'php-cs-fixer/shim',         '3.75.0'],
        ['phpcs',        'squizlabs/php_codesniffer', '3.13.0'],
        ['phpstan',      'phpstan/phpstan',           '2.1.47'],
        ['phpstan',      'phpstan/phpstan',           '2.1.39'],
        ['psalm',        'vimeo/psalm',               '6.15.1'],
        ['phan',         'phan/phan',                 '6.0.1'],
    ];

    /**
     * Plugins that need to be allowed per package.
     *
     * @var array<string, list<non-empty-string>>
     */
    private const array ALLOWED_PLUGINS = [
        'mago' => ['carthage-software/mago'],
        'phpstan' => ['phpstan/extension-installer'],
    ];

    /**
     * @param non-empty-string $rootDir
     */
    public static function install(string $rootDir): bool
    {
        Output::section('Installing isolated tool packages');

        $toolsDir = $rootDir . '/tools';
        Filesystem\create_directory($toolsDir);

        foreach (self::PACKAGES as [$name, $package, $version]) {
            $slug = Str\format('%s-%s', $name, $version);
            if (!self::installPackage($toolsDir, $name, $slug, $package, $version)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns all tool instances, expanding Mago packages into three tools each.
     *
     * @return list<ToolInstance>
     */
    public static function allTools(): array
    {
        $instances = [];
        foreach (self::PACKAGES as [$name, $package, $version]) {
            $installSlug = Str\format('%s-%s', $name, $version);
            $tools = self::toolsForPackage($name);

            foreach ($tools as $tool) {
                $slug = Str\format('%s-%s', $tool->value, $version);
                $instances[] = new ToolInstance($tool, $version, $slug, $installSlug);
            }
        }

        return $instances;
    }

    /**
     * Returns unique tool instances for a given kind, deduplicating by tool+version.
     *
     * @return list<ToolInstance>
     */
    public static function allToolsOfKind(Configuration\ToolKind $kind): array
    {
        return Vec\filter(self::allTools(), static fn(ToolInstance $t): bool => $t->tool->getKind() === $kind);
    }

    /**
     * Get all Tool cases that belong to a given package name.
     *
     * @param non-empty-string $packageName
     *
     * @return list<Tool>
     */
    private static function toolsForPackage(string $packageName): array
    {
        return Vec\filter(Tool::cases(), static fn(Tool $t): bool => $t->getPackageName() === $packageName);
    }

    private static function installPackage(
        string $toolsDir,
        string $name,
        string $slug,
        string $package,
        string $version,
    ): bool {
        $toolDir = Str\format('%s/%s', $toolsDir, $slug);
        Filesystem\create_directory($toolDir);

        $allowPlugins = [];
        foreach (self::ALLOWED_PLUGINS[$name] ?? [] as $plugin) {
            $allowPlugins[$plugin] = true;
        }

        $composerJson = Str\format('%s/composer.json', $toolDir);
        $composerContent = Json\encode([
            'require' => [
                $package => $version,
            ],
            'config' => [
                'allow-plugins' => $allowPlugins !== [] ? $allowPlugins : (object) [],
                'platform-check' => false,
            ],
        ], true);

        if (Filesystem\is_file($composerJson)) {
            Filesystem\delete_file($composerJson);
        }

        File\write($composerJson, $composerContent, File\WriteMode::MustCreate);

        $label = Str\format('%s %s', $name, $version);
        try {
            Output::withSpinner(
                Str\format('Installing %s', $label),
                static function () use ($toolDir, $name, $version): void {
                    $args = ['install', '--no-interaction'];
                    if ($name === 'phan') {
                        $args[] = '--ignore-platform-req=ext-tokenizer';
                    }

                    Shell\execute('composer', $args, $toolDir);

                    if ($name !== 'mago') {
                        return;
                    }

                    if (version_compare($version, '1.10.0', '>=')) {
                        Shell\execute($toolDir . '/vendor/bin/mago', ['--version'], $toolDir);

                        return;
                    }

                    Shell\execute('composer', ['mago:install-binary', '--no-interaction'], $toolDir);
                },
                '  ',
            );

            return true;
        } catch (Shell\Exception\FailedExecutionException $e) {
            Output::error(Str\format('Failed to install %s: %s', $label, $e->getErrorOutput()));
            return false;
        }
    }
}
