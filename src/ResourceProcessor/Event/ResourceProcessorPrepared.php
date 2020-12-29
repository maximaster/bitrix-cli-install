<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\ResourceProcessor\Event;

use Maximaster\BitrixCliInstall\ResourceProcessor\ResourceProcessorInterface;

class ResourceProcessorPrepared
{
    /** @var ResourceProcessorInterface */
    public $processor;

    public function __construct(ResourceProcessorInterface $processor)
    {
        $this->processor = $processor;
    }
}
