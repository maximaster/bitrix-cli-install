<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer\Enum;

use MyCLabs\Enum\Enum;

/**
 * @method static self INIT_RESTORE()
 * @method static self DOWNLOAD_BACKUP()
 * @method static self UNPACK_BACKUP()
 * @method static self REQUEST_CONNECTION_PARAMETERS()
 * @method static self RESTORE_DATABASE()
 */
class BitrixRestoreStageEnum extends Enum
{
    public const INIT_RESTORE = 'init_restore';
    public const DOWNLOAD_BACKUP = 'download_backup';
    public const UNPACK_BACKUP = 'unpack_backup';
    public const REQUEST_CONNECTION_PARAMETERS = 'request_connection_parameters';
    public const RESTORE_DATABASE = 'restore_database';
}
