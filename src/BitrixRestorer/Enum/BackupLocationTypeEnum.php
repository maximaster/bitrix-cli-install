<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer\Enum;

use MyCLabs\Enum\Enum;

/**
 * @method static self LOCAL()
 * @method static self REMOTE()
 */
class BackupLocationTypeEnum extends Enum
{
    public const LOCAL = 'local';
    public const REMOTE = 'remote';

    public function isRemote(): bool
    {
        return $this->equals(self::REMOTE());
    }
}
