<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer\Event;

use Maximaster\BitrixCliInstall\BitrixRestorer\BitrixRestoreConfig;
use Maximaster\BitrixCliInstall\BitrixRestorer\Enum\BitrixRestoreStageEnum;
use Maximaster\BitrixCliInstall\BitrixRestorer\RestoreResponse;

class RestoreStepFinished
{
    /** @var BitrixRestoreConfig */
    public $config;

    /** @var BitrixRestoreStageEnum */
    public $stage;

    /** @var array */
    public $payload;

    /** @var RestoreResponse */
    public $response;

    public function __construct(BitrixRestoreConfig $config, BitrixRestoreStageEnum $stage, array $payload, RestoreResponse $response)
    {
        $this->config = $config;
        $this->stage = $stage;
        $this->payload = $payload;
        $this->response = $response;
    }
}
