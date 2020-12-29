<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer;

use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface;

class RestoreResponse
{
    /** @var ResponseInterface */
    public $httpResponse;

    /**
     * Храним отдельно, т.к. из ResponseInterface можно получить ->getBody()->getContents() лишь один раз, повторные
     * вызовы дадут пустую строку
     *
     * @var string
     */
    public $responseBody;

    /** @var int|null Если int, то в диапазоне [0, 100] */
    public $progress;

    public function __construct(ResponseInterface $httpResponse, string $responseBody, ?int $progress)
    {
        if ($progress !== null && $progress < 0 || $progress > 100) {
            throw new OutOfBoundsException(sprintf('Аргумент progrees должен быть в диапазоне от 0 до 100 или равен null'));
        }

        $this->httpResponse = $httpResponse;
        $this->responseBody = $responseBody;
        $this->progress = $progress;
    }
}
