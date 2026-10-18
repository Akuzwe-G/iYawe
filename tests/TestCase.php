<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $driver = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.'.$driver.'.database');
        if (in_array($driver, ['mysql', 'mariadb']) && ! str_starts_with((string) $database, 'iyawe_m4_test_')) {
            throw new \RuntimeException('Refusing database-refresh tests outside an iyawe_m4_test_ database.');
        }

        return $app;
    }
}
