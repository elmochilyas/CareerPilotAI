<?php

// Temporary: Run PHPStan analysis and output to file
$cwd = __DIR__;

Phar::loadPhar($cwd.'/vendor/phpstan/phpstan/phpstan.phar', 'phpstan.phar');
require_once 'phar://phpstan.phar/vendor/autoload.php';

use _PHPStan_02959ca10\Symfony\Component\Console\Application;
use PHPStan\Command\AnalyseCommand;
use PHPStan\Internal\ComposerHelper;

$analysisStartTime = microtime(true);
require_once 'phar://phpstan.phar/preload.php';

$reversedComposerAutoloaderProjectPaths = [$cwd];

$application = new Application('PHPStan', ComposerHelper::getPhpStanVersion());
$application->setDefaultCommand('analyse');
$application->add(new AnalyseCommand($reversedComposerAutoloaderProjectPaths, $analysisStartTime));

$_SERVER['argv'] = $argv;
$_SERVER['argc'] = count($argv);

$application->run();
