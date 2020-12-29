<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\ResourceProcessor;

use Exception;
use Maximaster\BitrixCliInstall\ResourceProcessor\ResourceProcessorInterface;

/**
 * Проверяем корректность установки здесь
 */
class UnpackedBitrixValidatorProcessor implements ResourceProcessorInterface
{
    /** @var array */
    private $distributiveFiles;

    public function __construct(array $distributiveFiles = [])
    {
        $this->distributiveFiles = $distributiveFiles;
    }

    public function supports(string $resourceUri): bool
    {
        return is_dir($resourceUri);
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
        $expectedFiles = preg_replace('/^/', $workingDirectory . DIRECTORY_SEPARATOR, $this->distributiveFiles);

        $foundFiles = array_filter($expectedFiles, 'file_exists');

        if (count($expectedFiles) !== count($foundFiles)) {
            throw new Exception(sprintf(
                'Некоторые из обязательных для установки файлов/директорий не были обнаружены: %s',
                implode(', ', array_diff($expectedFiles, $foundFiles))
            ));
        }

        return null;
    }
}
