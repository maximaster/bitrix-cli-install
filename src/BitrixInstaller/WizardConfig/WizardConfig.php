<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig;

use Exception;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Enum\WizardConfigStepIdEnum;

class WizardConfig
{
    /** @var WizardConfigStep[] */
    private $steps;

    /**
     * @param WizardConfigStep[] $steps
     */
    public function __construct(array $steps)
    {
        $this->steps = $steps;
    }

    /**
     * @return WizardConfigStep[]
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public function step(int $idx): ?WizardConfigStep
    {
        return $this->steps[$idx] ?? null;
    }

    public function findFirstStepOfId(WizardConfigStepIdEnum $id): ?WizardConfigStep
    {
        $idValue = $id->getValue();

        foreach ($this->steps as $step) {
            if ($idValue === $step->id()) {
                return $step;
            }
        }

        return null;
    }

    public function getFirstStepOfId(WizardConfigStepIdEnum $id): WizardConfigStep
    {
        $step = $this->findFirstStepOfId($id);
        if (!$step) {
            throw new Exception(sprintf('Не удалось найти шаг "%s"', $id->getValue()));
        }

        return $step;
    }
}
