<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig;

use Exception;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Parser\WizardConfigParserInterface;

class WizardConfigFactory
{
    /** @var WizardConfigLocator */
    private $locator;

    /** @var WizardConfigParserInterface */
    private $parser;

    public function __construct(WizardConfigLocator $locator, WizardConfigParserInterface $parser)
    {
        $this->locator = $locator;
        $this->parser = $parser;
    }

    /**
     * @param string $relativeConfigPath
     * @param array $configDirectories
     * @param bool $useFallback
     *
     * @return WizardConfig
     *
     * @throws Exception
     */
    public function createFromPath(
        string $relativeConfigPath,
        array $configDirectories = [],
        bool $useFallback = true
    ): WizardConfig {
        $configFile = $this->locator->locate($relativeConfigPath, $configDirectories, $useFallback);
        return $this->parser->parse($configFile);
    }
}
