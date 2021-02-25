<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer;

use InvalidArgumentException;
use SplFileInfo;

class BitrixRestoreConfig
{
    /** @var SplFileInfo */
    public $documentRoot;

    /** @var string */
    public $restoreScriptUri;

    /** @var string */
    public $backupUri;

    /** @var bool */
    public $skipDbRestore;

    /** @var string */
    public $wizardConfig;

    public function __construct(
        SplFileInfo $documentRoot,
        string $restoreScriptUri,
        string $backupUri,
        bool $skipDbRestore,
        string $wizardConfig = null
    ) {
        if (!$documentRoot->isDir()) {
            throw new InvalidArgumentException(sprintf('documentRoot должен быть директорией, передано %s', $documentRoot->getPathname()));
        }

        $this->documentRoot = $documentRoot;
        $this->restoreScriptUri = $restoreScriptUri;
        $this->backupUri = $backupUri;
        $this->skipDbRestore = $skipDbRestore;
        $this->wizardConfig = $wizardConfig;
    }
}
