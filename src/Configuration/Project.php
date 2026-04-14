<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Configuration;

enum Project: string
{
    case Psl = 'psl';
    case WordPress = 'wordpress';
    case Magento = 'magento';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::Psl => 'php-standard-library/php-standard-library',
            self::WordPress => 'wordpress-develop',
            self::Magento => 'magento/magento2',
        };
    }

    /**
     * @return non-empty-string
     */
    public function getRepo(): string
    {
        return match ($this) {
            self::Psl => 'https://github.com/php-standard-library/php-standard-library.git',
            self::WordPress => 'https://github.com/WordPress/wordpress-develop.git',
            self::Magento => 'https://github.com/magento/magento2.git',
        };
    }

    /**
     * @return non-empty-string
     */
    public function getRef(): string
    {
        return match ($this) {
            self::Psl => '5.5.x',
            self::WordPress => 'trunk',
            self::Magento => '2.4-develop',
        };
    }

    /**
     * Returns the composer command to set up this project after cloning.
     *
     * @return non-empty-string
     */
    public function getSetupCommand(): string
    {
        return 'composer update --no-interaction --quiet --ignore-platform-reqs';
    }

    /**
     * Source paths relative to the workspace root.
     * Used by tools that take paths as CLI arguments (e.g. Pretty PHP).
     *
     * @return list<non-empty-string>
     */
    public function getSourcePaths(): array
    {
        return match ($this) {
            self::Psl => ['src', 'examples'],
            self::WordPress => ['src', 'tests'],
            self::Magento => ['app', 'dev', 'phpserver', 'setup', 'pub'],
        };
    }
}
