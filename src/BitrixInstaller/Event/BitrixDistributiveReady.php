<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\Event;

use Maximaster\BitrixCliInstall\BitrixInstaller\BitrixInstallConfig;

class BitrixDistributiveReady
{
    /** @var BitrixInstallConfig */
    public $installConfig;

    public function __construct(BitrixInstallConfig $installConfig)
    {
        $this->installConfig = $installConfig;
    }
}
