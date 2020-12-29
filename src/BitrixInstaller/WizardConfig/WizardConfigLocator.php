<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig;

use Exception;
use SplFileObject;

class WizardConfigLocator
{
    /** @var string[] */
    private $configDirectories;

    public function __construct(array $configDirectories)
    {
        $this->configDirectories = $configDirectories;
    }

    /**
     * @param string $relativeConfigPath
     * @param array $configDirectories
     * @param bool $useFallback
     *
     * @return SplFileObject
     *
     * @throws Exception
     */
    public function locate(string $relativeConfigPath, array $configDirectories = [], bool $useFallback = true): SplFileObject
    {
        if ($useFallback) {
            $configDirectories = array_merge($configDirectories, $this->configDirectories);
        }

        foreach ($configDirectories as $configDirectory) {
            $configPath = $configDirectory . DIRECTORY_SEPARATOR . $relativeConfigPath;
            if (file_exists($configPath)) {
                return new SplFileObject(realpath($configPath));
            }
        }

        throw new Exception(sprintf(
            'Не удалось найти конфигурацию установки по адресу "%s" по следующим путям: %s',
            $relativeConfigPath,
            implode(', ', $configDirectories)
        ));
    }

    /**
     * @return string[]
     */
    public function configDirectories(): array
    {
        return $this->configDirectories;
    }
}
