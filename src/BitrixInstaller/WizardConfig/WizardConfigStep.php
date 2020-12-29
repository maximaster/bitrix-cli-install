<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig;

use InvalidArgumentException;

class WizardConfigStep
{
    /** @var string */
    private $id;

    /** @var string[] */
    private $payload;

    /**
     * @param string $id
     *
     * @param string[] $payload
     */
    public function __construct(string $id, array $payload)
    {
        $this->validate($id, $payload);

        $this->id = $id;
        $this->payload = $payload;
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return string[]
     */
    public function payload(): array
    {
        return $this->payload;
    }

    private function validate(string $id, array $payload): void
    {
        if (empty($id)) {
            throw new InvalidArgumentException(sprintf('ID шага должно быть не пустой строкой. Передано: "%s"', var_export($id, true)));
        }

        $invalidFields = [];
        foreach ($payload as $field => $value) {
            if (!is_scalar($value) && $value !== null) {
                $invalidFields[] = $field;
            }
        }

        if ($invalidFields) {
            throw new InvalidArgumentException(
                sprintf('Некоторые поля шага "%s" не являются примитивами: %s', $id, implode(', ', $invalidFields))
            );
        }
    }
}
