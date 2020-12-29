<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\Event;

use GuzzleHttp\Client;
use Maximaster\BitrixCliInstall\BitrixInstaller\BitrixInstallConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;

class WizardClientPrepared
{
    /** @var BitrixInstallConfig */
    public $installConfig;

    /** @var WizardConfig */
    public $wizardConfig;

    /** @var Client */
    public $client;

    public function __construct(BitrixInstallConfig $installConfig, WizardConfig $wizardConfig, Client $client)
    {
        $this->installConfig = $installConfig;
        $this->wizardConfig = $wizardConfig;
        $this->client = $client;
    }
}
