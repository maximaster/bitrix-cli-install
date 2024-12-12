<?php

declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Parser;

use ArrayIterator;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigStep;
use MultipleIterator;
use PatchRanger\CartesianIterator;

class WizardConfigBuilder
{
    public const STEPS_KEY = 'steps';
    public const ID_KEY = 'CurrentStepID';
    public const CARTESIAN_PRODUCT_KEY = '~cartesian_product';

    /** @var string[] */
    private $subtitutions;

    public function __construct(array $subtitutions)
    {
        $this->subtitutions = $subtitutions;
    }

    public function build(array $configData): WizardConfig
    {
        $steps = [];

        foreach ($configData[self::STEPS_KEY] as $rawStep) {
            if (!empty($rawStep[self::CARTESIAN_PRODUCT_KEY])) {
                $variationIterator = new CartesianIterator(MultipleIterator::MIT_KEYS_ASSOC);
                foreach (array_reverse($rawStep[self::CARTESIAN_PRODUCT_KEY], true) as $variation => $variationOptions) {
                    $variationIterator->attachIterator(new ArrayIterator($variationOptions), $variation);
                }

                foreach ($variationIterator as $variation) {
                    $step = $variation + $rawStep;
                    unset($step[self::CARTESIAN_PRODUCT_KEY]);
                    $steps[] = $this->createStep($rawStep[self::ID_KEY], $step);
                }
            } else {
                $steps[] = $this->createStep($rawStep[self::ID_KEY], $rawStep);
            }
        }

        return new WizardConfig($steps);
    }

    private function createStep(string $id, array $payload): WizardConfigStep
    {
        $this->replaceSubtitutions($payload);
        return new WizardConfigStep($id, $payload);
    }

    private function replaceSubtitutions(array &$payload): void
    {
        foreach ($payload as $field => &$value) {
            foreach ($this->subtitutions as $subtitutionName => $subtitutionValue) {
                $value = str_replace("%{$subtitutionName}%", $subtitutionValue, strval($value));
            }
        }
        unset($value);
    }
}
