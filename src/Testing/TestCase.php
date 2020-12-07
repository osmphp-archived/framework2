<?php

namespace Osm\Framework\Testing;

use Osm\Core\App;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * @property string $suite
 * @property Module $testing_module
 * @property BaseModule $module
 */
abstract class TestCase extends BaseTestCase
{
    public static $app_instance;

    public function __get($property) {
        global $osm_app; /* @var App $osm_app */

        switch ($property) {
            case 'testing_module':
                return $osm_app->modules['Osm_Framework_Testing'];
        }

        return null;
    }

    protected function recreateApp() {
        $dir = __DIR__;
        return static::$app_instance = App::createApp([
            'base_path' => realpath($dir . '/../../../../../'),
            'env' => 'testing',
            'area' => 'test',
        ])->boot();
    }

    protected function setUp(): void {
        // boot application instance to be used in testing
        if (!static::$app_instance) {
            $this->recreateApp();
        }
    }
}