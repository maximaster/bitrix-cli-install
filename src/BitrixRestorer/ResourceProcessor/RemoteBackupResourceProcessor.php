<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer\ResourceProcessor;

use Maximaster\BitrixCliInstall\ResourceProcessor\RemoteResourceProcessor;

class RemoteBackupResourceProcessor extends RemoteResourceProcessor
{
    public function process(string $resourceUri, string $workingDirectory = null): ?string
    {
        return parent::process($resourceUri, $workingDirectory);
    }
}
