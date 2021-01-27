<?php
/**
 * Created by PhpStorm.
 * User: fanxinyu
 * Date: 2021-01-27
 * Time: 09:39
 */

declare(strict_types=1);

namespace Daviswwang\Log\Facades;

use Hyperf\Utils\ApplicationContext;

class Log
{

    public static function getInstance()
    {
        return ApplicationContext::getContainer()->get(Log::class);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::getInstance()->$name(...$arguments);
    }
}