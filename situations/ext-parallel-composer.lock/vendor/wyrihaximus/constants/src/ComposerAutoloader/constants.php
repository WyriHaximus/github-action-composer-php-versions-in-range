<?php

namespace WyriHaximus\Constants\ComposerAutoloader;

foreach (array(
    dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
) as $location) {
    if (!file_exists($location)) {
        continue;
    }

    define('WyriHaximus\Constants\ComposerAutoloader\LOCATION', $location);
}
