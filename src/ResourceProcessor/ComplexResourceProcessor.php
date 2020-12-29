<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\ResourceProcessor;

use Exception;
use Maximaster\BitrixCliInstall\ResourceProcessor\Event\ResourceProcessorFinished;
use Psr\EventDispatcher\EventDispatcherInterface;
use Maximaster\BitrixCliInstall\ResourceProcessor\Event\ResourceProcessorPrepared;

class ComplexResourceProcessor implements ResourceProcessorInterface
{
    /** @var ProcessorCollection */
    private $processors;

    /** @var EventDispatcherInterface|null */
    private $dispatcher;

    public static function fromArray(array $processors, EventDispatcherInterface $dispatcher = null)
    {
        return new self(new ProcessorCollection($processors), $dispatcher);
    }

    public function __construct(ProcessorCollection $processors, EventDispatcherInterface $dispatcher = null)
    {
        $this->processors = $processors;
        $this->dispatcher = $dispatcher;
    }

    public function supports(string $resourceUri): bool
    {
        return (bool) $this->processors->sliceFor($resourceUri)->first();
    }

    public function process(string $resourceUri, string $workingDirectory = null): ?string
    {
        while (true) {
            $processor = $this->processors->sliceFor($resourceUri)->first();
            if (!$processor) {
                throw new Exception(sprintf('Не удалось найти обработчик для ресурса "%s"', $resourceUri));
            }

            $this->dispatcher && $this->dispatcher->dispatch(new ResourceProcessorPrepared($processor));

            $resourceUri = $processor->process($resourceUri, $workingDirectory);

            $this->dispatcher && $this->dispatcher->dispatch(new ResourceProcessorFinished($processor));

            if (!$resourceUri) {
                break;
            }
        }

        return $resourceUri;
    }
}
