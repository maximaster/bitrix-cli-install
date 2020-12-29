<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Parser;

use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;
use SplFileObject;

interface WizardConfigParserInterface
{
    public function parse(SplFileObject $configFile): WizardConfig;
}
