<?php
declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use const JSON_THROW_ON_ERROR;

class StaticFileAvailableThemeProvider extends AbstractAvailableThemeProvider
{
    public const THEME_INDEX = 'theme-config/index.json';

    private FilesystemOperator $filesystem;

    /**
     * @internal
     */
    public function __construct(FilesystemOperator $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function getDecorated(): AbstractAvailableThemeProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(Context $context): array
    {
        if (!$this->filesystem->fileExists(self::THEME_INDEX)) {
            throw new \RuntimeException('Cannot find theme configuration. Did you run bin/console theme:dump');
        }

        return json_decode((string) $this->filesystem->read(self::THEME_INDEX), true, 512, JSON_THROW_ON_ERROR);
    }
}
