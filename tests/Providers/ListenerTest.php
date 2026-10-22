<?php

namespace Tests\Providers;

use Illuminate\Log\Events\MessageLogged;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Pagevamp\Providers\CloudWatchServiceProvider;
use Tests\TestCase;

/**
 * boot(): the listener registered on Laravel's log, called directly with
 * both event formats.
 */
class ListenerTest extends TestCase
{
    public function testBootRegistersOneListener()
    {
        [$app, $log] = $this->appWithFakeLog();

        (new CloudWatchServiceProvider($app))->boot();

        $this->assertCount(1, $log->listeners);
    }

    public function testBootDoesNotRegisterListenerWhenDisabled()
    {
        $this->setDisableEnv('true');

        [$app, $log] = $this->appWithFakeLog();

        (new CloudWatchServiceProvider($app))->boot();

        $this->assertCount(0, $log->listeners);
    }

    public function testMessageLoggedEventIsForwardedToCloudwatchLogger()
    {
        [$app, $log] = $this->appWithFakeLog();
        $handler = $this->bindCloudwatchLogger($app);

        (new CloudWatchServiceProvider($app))->boot();

        $log->listeners[0](new MessageLogged('warning', 'Stock low', ['sku' => 'A1']));

        $this->assertTrue($handler->hasWarningThatContains('Stock low'));
        $this->assertEquals(['sku' => 'A1'], $handler->getRecords()[0]->context);
    }

    public function testLegacyThreeArgumentsAreForwardedToCloudwatchLogger()
    {
        [$app, $log] = $this->appWithFakeLog();
        $handler = $this->bindCloudwatchLogger($app);

        (new CloudWatchServiceProvider($app))->boot();

        $log->listeners[0]('info', 'User logged in', ['id' => 123]);

        $this->assertTrue($handler->hasInfoThatContains('User logged in'));
        $this->assertEquals(['id' => 123], $handler->getRecords()[0]->context);
    }

    public function testErrorExceptionMessageGoesToCloudwatchLogger()
    {
        [$app, $log] = $this->appWithFakeLog();
        $handler = $this->bindCloudwatchLogger($app);

        (new CloudWatchServiceProvider($app))->boot();

        $log->listeners[0]('error', new \ErrorException('Division by zero'), []);

        $this->assertTrue($handler->hasErrorThatContains('Division by zero'));
    }

    public function testBootDoesNotRegisterListenerWhenDisabledByConfig()
    {
        [$app, $log] = $this->appWithFakeLog(['cloudwatch' => $this->cloudwatchConfig(['disabled' => true])]);

        (new CloudWatchServiceProvider($app))->boot();

        $this->assertCount(0, $log->listeners);
    }

    public function testDisabledConfigKeyWinsOverEnv()
    {
        $this->setDisableEnv('true');

        [$app, $log] = $this->appWithFakeLog(['cloudwatch' => $this->cloudwatchConfig(['disabled' => false])]);

        (new CloudWatchServiceProvider($app))->boot();

        $this->assertCount(1, $log->listeners);
    }

    public function testNonMonologCloudwatchLoggerIsIgnored()
    {
        [$app, $log] = $this->appWithFakeLog();
        $app->instance('cloudwatch.logger', new \stdClass());

        (new CloudWatchServiceProvider($app))->boot();

        $log->listeners[0](new MessageLogged('info', 'Ignored', []));

        $this->addToAssertionCount(1);
    }

    private function appWithFakeLog(array $channels = [])
    {
        $app = $this->app($channels);
        $log = new class() {
            public $listeners = [];

            public function listen($callback)
            {
                $this->listeners[] = $callback;
            }
        };
        $app->instance('log', $log);

        return [$app, $log];
    }

    private function bindCloudwatchLogger($app)
    {
        $handler = new TestHandler();
        $app->instance('cloudwatch.logger', new Logger('cloudwatch', [$handler]));

        return $handler;
    }
}
