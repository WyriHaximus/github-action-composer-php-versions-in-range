<?php

use Composer\Autoload\ClassLoader;

$autoloader = require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

exit(
    defined('WyriHaximus\Constants\Numeric\ZERO') &&
    defined('WyriHaximus\Constants\HTTPStatusCodes\NOT_FOUND') &&
    defined('WyriHaximus\Constants\Boolean\NOT_FOUND') &&
    defined('WyriHaximus\Constants\ComposerAutoloader\LOCATION') &&
    file_exists(LOCATION) &&
    $autoloader === require_once LOCATION &&
    require_once LOCATION instanceof ClassLoader ? 0 : 255
);
