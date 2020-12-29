<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller;

use MyCLabs\Enum\Enum;

/**
 * @method static self PRE_INSTALL()
 * @method static self POST_INSTALL()
 *
 * @method static self PRE_STEP()
 * @method static self POST_STEP()
 */
class BitrixInstallerStage extends Enum
{
    public const PRE_INSTALL = 'pre-install';
    public const POST_INSTALL = 'post-install';

    public const PRE_STEP = 'pre-step';
    public const POST_STEP = 'post-step';
}
