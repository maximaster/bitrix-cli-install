<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\Event;

use Maximaster\BitrixCliInstall\BitrixInstaller\BitrixInstallConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;

class InstallationFinished
{
    /** @var BitrixInstallConfig */
    public $installConfig;

    /** @var WizardConfig */
    public $wizardConfig;

    public function __construct(BitrixInstallConfig $installConfig, WizardConfig $wizardConfig)
    {
        $this->installConfig = $installConfig;
        $this->wizardConfig = $wizardConfig;
    }
}
