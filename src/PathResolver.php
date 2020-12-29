<?php

namespace Maximaster\BitrixCliInstall;

use Exception;
use InvalidArgumentException;
use SplFileInfo;

class PathResolver
{
    /** @var string[] */
    private $contexts = [];

    public static function forDir(?string $dir): self
    {
        return new self($dir ? [ $dir ] : []);
    }

    public function __construct(array $contexts = [])
    {
        foreach ($contexts as $context) {
            if (!is_dir($context)) {
                throw new InvalidArgumentException(
                    sprintf('contexts should contain absolute directory paths that exist. "%s" is not', $context)
                );
            }

            $this->contexts[] = $context;
        }
    }

    public function resolve(string $path): SplFileInfo
    {
        if (substr($path, 0, 1) === '/') {
            return new SplFileInfo($path);
        }

        foreach ($this->contexts as $context) {
            $fullPath = $context . DIRECTORY_SEPARATOR . $path;

            if (file_exists($fullPath)) {
                return new SplFileInfo($fullPath);
            }
        }

        throw new Exception(sprintf("Path '%s' can't be found in contexts: %s", $path, implode(', ', $this->contexts)));
    }
}
