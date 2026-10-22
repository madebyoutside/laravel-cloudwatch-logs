<?php

namespace Tests\Providers;

use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use Pagevamp\Providers\CloudWatchServiceProvider;
use Tests\TestCase;

/**
 * register(): default config merge and the cloudwatch.logger binding.
 */
class RegisterTest extends TestCase
{
    public function testRegisterMergesDefaultCloudwatchChannel()
    {
        $app = $this->app(['single' => ['driver' => 'single']]);

        (new CloudWatchServiceProvider($app))->register();

        $channels = $app['config']->get('logging.channels');

        $this->assertEquals(['driver' => 'single'], $channels['single']);
        $this->assertArrayHasKey('cloudwatch', $channels);
        $this->assertEquals('laravel_app', $channels['cloudwatch']['stream_name']);
        $this->assertEquals('laravel_app', $channels['cloudwatch']['group_name']);
        $this->assertEquals('latest', $channels['cloudwatch']['version']);
        $this->assertEquals(JsonFormatter::class, $channels['cloudwatch']['formatter']);
        $this->assertFalse($channels['cloudwatch']['disabled']);
    }

    public function testRegisterKeepsCloudwatchChannelDefinedByApp()
    {
        $app = $this->app(['cloudwatch' => $this->cloudwatchConfig(['group_name' => 'app_group'])]);

        (new CloudWatchServiceProvider($app))->register();

        $this->assertEquals(
            'app_group',
            $app['config']->get('logging.channels.cloudwatch.group_name')
        );
    }

    public function testRegisterBindsCloudwatchLoggerAsSingleton()
    {
        $app = $this->app(['cloudwatch' => $this->cloudwatchConfig()]);

        (new CloudWatchServiceProvider($app))->register();

        $logger = $app->make('cloudwatch.logger');

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertInstanceOf(CloudWatch::class, $logger->getHandlers()[0]);
        $this->assertSame($logger, $app->make('cloudwatch.logger'));
    }

    public function testRegisterDoesNotBindLoggerWhenDisabledByEnv()
    {
        $this->setDisableEnv('true');

        $app = $this->app(['single' => ['driver' => 'single']]);

        (new CloudWatchServiceProvider($app))->register();

        $this->assertFalse($app->bound('cloudwatch.logger'));
        // Default config is still merged so it lands in the cached config
        $this->assertTrue($app['config']->get('logging.channels.cloudwatch.disabled'));
    }

    public function testRegisterDoesNotBindLoggerWhenDisabledByConfig()
    {
        $app = $this->app(['cloudwatch' => $this->cloudwatchConfig(['disabled' => true])]);

        (new CloudWatchServiceProvider($app))->register();

        $this->assertFalse($app->bound('cloudwatch.logger'));
    }

    public function testAppChannelWithoutDisabledKeyFallsBackToEnv()
    {
        $this->setDisableEnv('true');

        $app = $this->app(['cloudwatch' => $this->cloudwatchConfig()]);

        (new CloudWatchServiceProvider($app))->register();

        $this->assertFalse($app->bound('cloudwatch.logger'));
    }
}
