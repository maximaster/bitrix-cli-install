<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Parser;

use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;
use SplFileObject;
use Symfony\Component\Yaml\Yaml;

class YamlWizardConfigParser implements WizardConfigParserInterface
{
    /** @var WizardConfigBuilder */
    private $wizardConfigBuilder;

    public function __construct(WizardConfigBuilder $wizardConfigBuilder)
    {
        $this->wizardConfigBuilder = $wizardConfigBuilder;
    }

    public function parse(SplFileObject $configFile): WizardConfig
    {
        $yamlData = Yaml::parseFile($configFile->getPathname());
        return $this->wizardConfigBuilder->build($yamlData);
    }
}
