<?php

namespace Tests\Integration;

use Illuminate\Events\Dispatcher;
use Illuminate\Log\Logger as LaravelLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Pagevamp\Providers\CloudWatchServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Real Laravel log stack (container, config, events dispatcher and
 * Illuminate\Log\Logger): only the AWS handler is replaced by a TestHandler.
 */
class LaravelLogTest extends TestCase
{
    /** @var \Monolog\Handler\TestHandler */
    private $appHandler;

    /** @var \Monolog\Handler\TestHandler */
    private $cloudwatchHandler;

    /** @var \Illuminate\Log\Logger */
    private $log;

    protected function setUp(): void
    {
        parent::setUp();

        $app = $this->app(['cloudwatch' => $this->cloudwatchConfig()]);

        $this->appHandler = new TestHandler();
        $this->log = new LaravelLogger(
            new Logger('laravel', [$this->appHandler]),
            new Dispatcher($app)
        );
        $app->instance('log', $this->log);

        $provider = new CloudWatchServiceProvider($app);
        $provider->register();

        $this->cloudwatchHandler = new TestHandler();
        $app->instance('cloudwatch.logger', new Logger('cloudwatch', [$this->cloudwatchHandler]));

        $provider->boot();
    }

    public static function levels()
    {
        return [
            'debug' => ['debug'],
            'info' => ['info'],
            'notice' => ['notice'],
            'warning' => ['warning'],
            'error' => ['error'],
            'critical' => ['critical'],
            'alert' => ['alert'],
            'emergency' => ['emergency'],
        ];
    }

    #[DataProvider('levels')]
    public function testLaravelLogIsSentToCloudwatchWithSameLevel($level)
    {
        $this->log->{$level}('Order failed', ['order' => 634]);

        $records = $this->cloudwatchHandler->getRecords();

        $this->assertCount(1, $records);
        $this->assertEquals(strtoupper($level), $records[0]->level->getName());
        $this->assertEquals('Order failed', $records[0]->message);
        $this->assertEquals(['order' => 634], $records[0]->context);
    }

    public function testDefaultLaravelLogStillReceivesTheMessage()
    {
        $this->log->error('Order failed');

        $this->assertTrue($this->appHandler->hasErrorThatContains('Order failed'));
        $this->assertTrue($this->cloudwatchHandler->hasErrorThatContains('Order failed'));
    }

    public function testErrorExceptionArrivesAsStringThroughLaravel()
    {
        // Laravel formats the message to string before firing MessageLogged,
        // so the provider's ErrorException branch is not reached through Log
        $this->log->error(new \ErrorException('Division by zero'));

        $this->assertTrue($this->cloudwatchHandler->hasErrorThatContains('Division by zero'));
    }
}
