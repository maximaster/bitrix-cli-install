<?php

namespace Maximaster\BitrixCliInstall\BitrixInstaller\Event;

use Maximaster\BitrixCliInstall\BitrixInstaller\BitrixInstallConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigStep;

class InstallationStepPayloadPrepared
{
    /** @var BitrixInstallConfig */
    public $installConfig;

    /** @var WizardConfig */
    public $wizardConfig;

    /** @var WizardConfigStep */
    private $step;

    /** @var array */
    private $payload;

    public function __construct(BitrixInstallConfig $installConfig, WizardConfig $wizardConfig, WizardConfigStep $step, array $payload)
    {
        $this->installConfig = $installConfig;
        $this->wizardConfig = $wizardConfig;
        $this->step = $step;
        $this->payload = $payload;
    }
}
