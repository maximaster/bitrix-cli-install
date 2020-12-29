<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer\Event;

use Maximaster\BitrixCliInstall\BitrixRestorer\BitrixRestoreConfig;
use Maximaster\BitrixCliInstall\BitrixRestorer\Enum\BitrixRestoreStageEnum;

class RestoreStepPrepared
{
    /** @var BitrixRestoreConfig */
    public $config;

    /** @var BitrixRestoreStageEnum */
    public $stage;

    /** @var array */
    public $payload;

    public function __construct(BitrixRestoreConfig $config, BitrixRestoreStageEnum $stage, array $payload)
    {
        $this->config = $config;
        $this->stage = $stage;
        $this->payload = $payload;
    }
}
