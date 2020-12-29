<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\Exception;

use Exception;

class InstallationStepException extends Exception
{
    /** @var array */
    private $payload;

    /** @var string */
    private $response;

    public function __construct(string $message, array $payload, string $response, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->payload = $payload;
        $this->response = $response;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function response(): string
    {
        return $this->response;
    }
}
