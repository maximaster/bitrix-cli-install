<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer;

use InvalidArgumentException;
use Maximaster\BitrixCliInstall\BitrixRestorer\Enum\BitrixRestoreSkippableStageEnum;
use SplFileInfo;
use UnexpectedValueException;

class BitrixRestoreConfig
{
    /** @var SplFileInfo */
    public $documentRoot;

    /** @var string */
    public $restoreScriptUri;

    /** @var string */
    public $backupUri;

    /** @var string[] */
    public $skips;

    /** @var string */
    public $wizardConfig;

    public function __construct(
        SplFileInfo $documentRoot,
        string $restoreScriptUri,
        string $backupUri,
        array $skips,
        string $wizardConfig = null
    ) {
        if (!$documentRoot->isDir()) {
            throw new InvalidArgumentException(sprintf('documentRoot должен быть директорией, передано %s', $documentRoot->getPathname()));
        }

        $this->documentRoot = $documentRoot;
        $this->restoreScriptUri = $restoreScriptUri;
        $this->backupUri = $backupUri;

        $invalidSkips = [];
        foreach ($skips as $skip) {
            if (!BitrixRestoreSkippableStageEnum::isValid($skip)) {
                $invalidSkips[] = $skip;
            }
        }

        if ($invalidSkips) {
            throw new UnexpectedValueException(
                sprintf(
                    'Получены недопустимые значения для параметра skips: %s. Допустимые значения: %s',
                    implode(', ', $invalidSkips),
                    implode(', ', BitrixRestoreSkippableStageEnum::toArray())
                )
            );
        }

        $this->skips = $skips;

        $this->wizardConfig = $wizardConfig;
    }
}
