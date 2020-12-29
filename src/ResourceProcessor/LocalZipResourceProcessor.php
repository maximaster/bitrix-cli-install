<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\ResourceProcessor;

use Exception;
use Maximaster\BitrixCliInstall\PathResolver;
use ZipArchive;

class LocalZipResourceProcessor implements ResourceProcessorInterface
{
    public function supports(string $resourceUri): bool
    {
        return extension_loaded('zip') && is_file($resourceUri) && str_ends_with($resourceUri, '.zip');
    }

    /**
     * @param string $resourceUri
     * @param string|null $workingDirectory
     *
     * @return string|null
     *
     * @throws Exception
     */
    public function process(string $resourceUri, string $workingDirectory = null): ?string
    {
        $resourceUri = PathResolver::forDir($workingDirectory)->resolve($resourceUri)->getPathname();

        $zip = new ZipArchive();

        $returnCode = $zip->open($resourceUri);
        if ($returnCode !== true) {
            throw new Exception(sprintf('Не удалось открыть архив "%s". Код ошибки %d', $resourceUri, $returnCode));
        }

        if ($zip->extractTo($workingDirectory) === false) {
            throw new Exception(sprintf('Не удалось распаковать файл "%s": %s', $resourceUri, $zip->getStatusString()));
        }

        $zip->close();

        // Передаём на проверку
        return $workingDirectory;
    }
}
