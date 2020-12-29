<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\Event;

use Maximaster\BitrixCliInstall\BitrixInstaller\BitrixInstallConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigStep;

class InstallationStepPrepared
{
    /** @var BitrixInstallConfig */
    public $installConfig;

    /** @var WizardConfig */
    public $wizardConfig;

    /** @var WizardConfigStep */
    public $step;

    public function __construct(BitrixInstallConfig $installConfig, WizardConfig $wizardConfig, WizardConfigStep $step)
    {
        $this->installConfig = $installConfig;
        $this->wizardConfig = $wizardConfig;
        $this->step = $step;
    }
}
