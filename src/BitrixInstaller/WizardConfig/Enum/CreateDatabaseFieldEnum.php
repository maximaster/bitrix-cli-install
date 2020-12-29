<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Enum;

use MyCLabs\Enum\Enum;

class CreateDatabaseFieldEnum extends Enum
{
    public const HOST = '__wiz_host';
    public const CREATE_USER = '__wiz_create_user';
    public const USER = '__wiz_user';
    public const PASSWORD = '__wiz_password';
    public const CREATE_DATABASE = '__wiz_create_database';
    public const DATABASE = '__wiz_database';
    public const CREATE_DATABASE_TYPE = '__wiz_create_database_type';
    public const ROOT_USER = '__wiz_root_user';
    public const ROOT_PASSWORD = '__wiz_root_password';
    public const FILE_ACCESS_PERMS = '__wiz_file_access_perms';
    public const FOLDER_ACCESS_PERMS = '__wiz_folder_access_perms';
}
