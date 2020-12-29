<?php

namespace Maximaster\BitrixCliInstall\ResourceProcessor;

use Exception;
use Maximaster\BitrixCliInstall\PathResolver;

class LocalFileValidateResourceProcessor implements ResourceProcessorInterface
{
    public function supports(string $resourceUri): bool
    {
        return true;
    }

    public function process(string $resourceUri, string $workingDirectory = null): ?string
    {
        $resourceFullPath = PathResolver::forDir($workingDirectory)->resolve($resourceUri);
        if (!file_exists($resourceFullPath)) {
            throw new Exception(sprintf('Ожидалось наличие локального ресурса "%s", но он не был найден, либо к нему нет доступа', $resourceFullPath));
        }

        return null;
    }
}
