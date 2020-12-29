<?php

declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\ResourceProcessor\Event;

use Maximaster\BitrixCliInstall\ResourceProcessor\ResourceProcessorInterface;

class ResourceProcessorProgressed
{
    /** @var ResourceProcessorInterface */
    public $processor;

    /** @var int */
    public $current;

    /** @var int */
    public $total;

    public function __construct(ResourceProcessorInterface $processor, int $current, int $total)
    {
        $this->processor = $processor;
        $this->current = $current;
        $this->total = $total;
    }
}
