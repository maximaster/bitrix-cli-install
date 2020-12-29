<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\Event;

use Exception;
use Maximaster\BitrixCliInstall\BitrixInstaller\BitrixInstallConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigStep;

class InstallationFailed
{
    /** @var BitrixInstallConfig */
    public $installConfig;

    /** @var WizardConfig */
    public $wizardConfig;

    /** @var WizardConfigStep */
    public $step;

    /** @var Exception */
    public $exception;

    public function __construct(
        BitrixInstallConfig $installConfig,
        WizardConfig $wizardConfig,
        WizardConfigStep $step,
        Exception $exception
    ) {
        $this->installConfig = $installConfig;
        $this->wizardConfig = $wizardConfig;
        $this->step = $step;
        $this->exception = $exception;
    }
}
