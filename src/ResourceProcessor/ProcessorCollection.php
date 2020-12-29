<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\ResourceProcessor;

class ProcessorCollection
{
    /** @var ResourceProcessorInterface[] */
    private $processors;

    public function __construct(array $processors)
    {
        $this->processors = $processors;
    }

    public function first(): ?ResourceProcessorInterface
    {
        return reset($this->processors) ?: null;
    }

    public function sliceFor(string $packageUri): self
    {
        $supportiveProcessors = [];

        foreach ($this->processors as $processor) {
            if ($processor->supports($packageUri)) {
                $supportiveProcessors[] =  $processor;
            }
        }

        return new self($supportiveProcessors);
    }
}
