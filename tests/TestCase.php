<?php

namespace Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();
        $this->setDisableEnv(null);
        parent::tearDown();
    }

    /**
     * Set (or unset with null) DISABLE_CLOUDWATCH_LOG for env().
     */
    protected function setDisableEnv(?string $value)
    {
        if (is_null($value)) {
            putenv('DISABLE_CLOUDWATCH_LOG');
            unset($_ENV['DISABLE_CLOUDWATCH_LOG'], $_SERVER['DISABLE_CLOUDWATCH_LOG']);

            return;
        }

        putenv('DISABLE_CLOUDWATCH_LOG='.$value);
        $_ENV['DISABLE_CLOUDWATCH_LOG'] = $value;
        $_SERVER['DISABLE_CLOUDWATCH_LOG'] = $value;
    }

    /**
     * Valid cloudwatch channel configuration.
     */
    protected function cloudwatchConfig(array $overrides = [])
    {
        return array_merge([
            'name' => 'test-app',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => 'key',
                'secret' => 'secret',
            ],
            'stream_name' => 'test_stream',
            'retention' => 14,
            'group_name' => 'test_group',
            'version' => 'latest',
            'formatter' => null,
        ], $overrides);
    }

    /**
     * Real Laravel container with a real config repository.
     */
    protected function app(array $channels = [])
    {
        $app = new Container();
        $app->instance('config', new Repository([
            'logging' => ['channels' => $channels],
        ]));

        return $app;
    }
}
