<?php

namespace Tests\Providers;


use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Pagevamp\Providers\CloudWatchServiceProvider;

class CloudWatchServiceProviderTest extends \Tests\TestCase
{
    public function testGetLoggerShouldResolveCustomFormatterInstanceFromConfiguration()
    {
        $cloudwatchConfigs = [
            'name' => '',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => '',
                'secret' => '',
            ],
            'stream_name' => 'laravel_app',
            'retention' => 14,
            'group_name' => 'laravel_app',
            'version' => 'latest',
            'formatter' => JsonFormatter::class
        ];

        $config = \Mockery::mock(Repository::class);
        $config->shouldReceive('get')
            ->once()
            ->with('logging.channels')
            ->andReturn([
                'cloudwatch' => $cloudwatchConfigs
            ]);
        $config->shouldReceive('get')
            ->once()
            ->with('logging.channels.cloudwatch')
            ->andReturn($cloudwatchConfigs);

        $formatter = \Mockery::mock(JsonFormatter::class);

        $app = \Mockery::mock(Application::class);
        $app->shouldReceive('make')
            ->twice()
            ->with('config')
            ->andReturn($config);
        $app->shouldReceive('make')
            ->once()
            ->with(JsonFormatter::class)
            ->andReturn($formatter);

        $provider = new CloudWatchServiceProvider($app);
        $logger = $provider->getLogger();

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertNotEmpty($logger->getHandlers());
        $this->assertInstanceOf(
            JsonFormatter::class,
            $logger->getHandlers()[0]->getFormatter()
        );
    }

    public function testGetLoggerShouldResolveDefaultFormatterInstance()
    {
        $cloudwatchConfigs = [
            'name' => '',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => '',
                'secret' => '',
            ],
            'stream_name' => 'laravel_app',
            'retention' => 14,
            'group_name' => 'laravel_app',
            'version' => 'latest',
            'formatter' => null
        ];

        $config = \Mockery::mock(Repository::class);
        $config->shouldReceive('get')
            ->once()
            ->with('logging.channels')
            ->andReturn([
                'cloudwatch' => $cloudwatchConfigs
            ]);
        $config->shouldReceive('get')
            ->once()
            ->with('logging.channels.cloudwatch')
            ->andReturn($cloudwatchConfigs);

        $formatter = \Mockery::mock(JsonFormatter::class);

        $app = \Mockery::mock(Application::class);
        $app->shouldReceive('make')
            ->twice()
            ->with('config')
            ->andReturn($config);
        $app->shouldReceive('make')
            ->never()
            ->with(JsonFormatter::class)
            ->andReturn($formatter);

        $provider = new CloudWatchServiceProvider($app);
        $logger = $provider->getLogger();

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertNotEmpty($logger->getHandlers());
        $this->assertInstanceOf(
            LineFormatter::class,
            $logger->getHandlers()[0]->getFormatter()
        );
    }
}