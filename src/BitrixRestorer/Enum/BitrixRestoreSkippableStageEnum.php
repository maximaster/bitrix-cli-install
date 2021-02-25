<?php

declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixRestorer\Enum;

use MyCLabs\Enum\Enum;

/**
 * @method static self INIT_RESTORE()
 * @method static self DOWNLOAD_BACKUP()
 * @method static self UNPACK_BACKUP()
 * @method static self RESTORE_DATABASE()
 */
class BitrixRestoreSkippableStageEnum extends Enum
{
    public const INIT_RESTORE = BitrixRestoreStageEnum::INIT_RESTORE;
    public const DOWNLOAD_BACKUP = BitrixRestoreStageEnum::DOWNLOAD_BACKUP;
    public const UNPACK_BACKUP = BitrixRestoreStageEnum::UNPACK_BACKUP;
    public const RESTORE_DATABASE = BitrixRestoreStageEnum::RESTORE_DATABASE;
}
