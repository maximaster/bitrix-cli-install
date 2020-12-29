<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller;

use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigStep;

interface BitrixInstallFeedbackHandlerInterface
{
    public function __invoke(
        BitrixInstallerStage $stage,
        BitrixInstallConfig $installConfig,
        WizardConfig $wizardConfig,
        ?WizardConfigStep $step
    );
}
