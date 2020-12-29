<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller;

use InvalidArgumentException;
use SplFileInfo;

class BitrixInstallConfig
{
    /** @var string */
    private $distributivePackageUri;

    /** @var SplFileInfo */
    private $documentRoot;

    /** @var string */
    private $wizardConfig;

    /** @var int */
    private $repeat;

    public function __construct(
        string $distributivePackageUri,
        SplFileInfo $documentRoot,
        string $wizardConfig,
        int $repeat
    ) {
        if (empty($distributivePackageUri)) {
            throw new InvalidArgumentException(sprintf('Аргумент distributivePackageUri не должен быть пустым'));
        }

        if (!$documentRoot->isDir()) {
            throw new InvalidArgumentException(sprintf('Аргумент documentRoot не является директорией, а "%s"', $this->documentRoot->getType()));
        }

        if (empty($wizardConfig)) {
            throw new InvalidArgumentException('Аргумент wizardConfig не должен быть пустой строкой');
        }

        if ($repeat < 0) {
            throw new InvalidArgumentException('Аргумент repeat должен быть положительным числом');
        }

        $this->distributivePackageUri = $distributivePackageUri;
        $this->documentRoot = $documentRoot;
        $this->wizardConfig = $wizardConfig;
        $this->repeat = $repeat;
    }

    public function distributivePackageUri(): string
    {
        return $this->distributivePackageUri;
    }

    public function documentRoot(): SplFileInfo
    {
        return $this->documentRoot;
    }

    public function wizardConfig(): string
    {
        return $this->wizardConfig;
    }

    public function repeat(): int
    {
        return $this->repeat;
    }
}
