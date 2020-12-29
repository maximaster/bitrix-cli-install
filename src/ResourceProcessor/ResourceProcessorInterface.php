<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\ResourceProcessor;

interface ResourceProcessorInterface
{
    /**
     * Поддерживает ли данный процессор этот тип ресурса?
     *
     * @param string $resourceUri
     *
     * @return bool
     */
    public function supports(string $resourceUri): bool;

    /**
     * @param string $resourceUri Входной ресурс для обработки
     * @param string|null $workingDirectory Контекст выполнения. Обычно это DOCUMENT_ROOT
     *
     * @return string|null Выходной ресурс. Означает, что кому-то требуется продолжить обработку, если не null
     */
    public function process(string $resourceUri, string $workingDirectory = null): ?string;
}
