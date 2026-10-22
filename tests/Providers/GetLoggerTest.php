<?php

namespace Tests\Providers;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Pagevamp\Exceptions\IncompleteCloudWatchConfig;
use Pagevamp\Providers\CloudWatchServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * getLogger() with a real container and config: how the channel
 * configuration reaches Monolog and the cwh handler.
 */
class GetLoggerTest extends TestCase
{
    public function testLoggerUsesChannelName()
    {
        $logger = $this->logger($this->cloudwatchConfig(['name' => 'my-app']));

        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertEquals('my-app', $logger->getName());
        $this->assertCount(1, $logger->getHandlers());
        $this->assertInstanceOf(CloudWatch::class, $logger->getHandlers()[0]);
    }

    public function testHandlerReceivesGroupStreamAndRetention()
    {
        $handler = $this->logger($this->cloudwatchConfig([
            'group_name' => 'my_group',
            'stream_name' => 'my_stream',
            'retention' => 30,
        ]))->getHandlers()[0];

        $this->assertEquals('my_group', $this->handlerProperty($handler, 'group'));
        $this->assertEquals('my_stream', $this->handlerProperty($handler, 'stream'));
        $this->assertEquals(30, $this->handlerProperty($handler, 'retention'));
    }

    public function testBatchSizeDefaultsTo10000()
    {
        $handler = $this->logger($this->cloudwatchConfig())->getHandlers()[0];

        $this->assertEquals(10000, $this->handlerProperty($handler, 'batchSize'));
    }

    public function testCustomBatchSize()
    {
        $handler = $this->logger($this->cloudwatchConfig(['batch_size' => 50]))->getHandlers()[0];

        $this->assertEquals(50, $this->handlerProperty($handler, 'batchSize'));
    }

    public function testConfiguredFormatterIsUsed()
    {
        $handler = $this->logger($this->cloudwatchConfig(['formatter' => JsonFormatter::class]))->getHandlers()[0];

        $this->assertInstanceOf(JsonFormatter::class, $handler->getFormatter());
    }

    public function testFormatterClassThatDoesNotExistFallsBackToLineFormatter()
    {
        $handler = $this->logger($this->cloudwatchConfig(['formatter' => 'Does\Not\Exist']))->getHandlers()[0];

        $this->assertInstanceOf(LineFormatter::class, $handler->getFormatter());
    }

    public function testConfigWithoutFormatterKeyFallsBackToLineFormatter()
    {
        $config = $this->cloudwatchConfig();
        unset($config['formatter']);

        $handler = $this->logger($config)->getHandlers()[0];

        $this->assertInstanceOf(LineFormatter::class, $handler->getFormatter());
    }

    public function testMissingCloudwatchChannelThrowsException()
    {
        $provider = new CloudWatchServiceProvider($this->app(['single' => []]));

        $this->expectException(IncompleteCloudWatchConfig::class);
        $this->expectExceptionMessage('Configuration Missing for Cloudwatch Log');

        $provider->getLogger();
    }

    public function testMissingRegionThrowsException()
    {
        $config = $this->cloudwatchConfig();
        unset($config['region']);

        $provider = new CloudWatchServiceProvider($this->app(['cloudwatch' => $config]));

        $this->expectException(IncompleteCloudWatchConfig::class);
        $this->expectExceptionMessage('Missing region key-value');

        $provider->getLogger();
    }

    public static function emptyNames()
    {
        return [
            'stream null' => ['stream_name', null],
            'stream empty' => ['stream_name', ''],
            'stream blank' => ['stream_name', '  '],
            'stream missing' => ['stream_name', 'unset'],
            'group null' => ['group_name', null],
            'group empty' => ['group_name', ''],
            'group missing' => ['group_name', 'unset'],
        ];
    }

    #[DataProvider('emptyNames')]
    public function testEmptyGroupOrStreamThrowsException($key, $value)
    {
        $config = $this->cloudwatchConfig();
        if ('unset' === $value) {
            unset($config[$key]);
        } else {
            $config[$key] = $value;
        }

        $this->expectException(IncompleteCloudWatchConfig::class);
        $this->expectExceptionMessage('Missing '.$key.' key-value');

        $this->logger($config);
    }

    public function testNumericStreamNameIsConvertedToString()
    {
        $handler = $this->logger($this->cloudwatchConfig(['stream_name' => 123]))->getHandlers()[0];

        $this->assertSame('123', $this->handlerProperty($handler, 'stream'));
    }

    public static function retentionValues()
    {
        return [
            'numeric string from env' => ['120', 120],
            'int' => [30, 30],
            'empty string means no expiration' => ['', null],
            'null means no expiration' => [null, null],
        ];
    }

    #[DataProvider('retentionValues')]
    public function testRetentionIsConvertedForCwh($value, $expected)
    {
        $handler = $this->logger($this->cloudwatchConfig(['retention' => $value]))->getHandlers()[0];

        $this->assertSame($expected, $this->handlerProperty($handler, 'retention'));
    }

    public function testMissingRetentionKeepsCwhDefault()
    {
        $config = $this->cloudwatchConfig();
        unset($config['retention']);

        $handler = $this->logger($config)->getHandlers()[0];

        $this->assertSame(14, $this->handlerProperty($handler, 'retention'));
    }

    public function testNonNumericRetentionThrowsException()
    {
        $this->expectException(IncompleteCloudWatchConfig::class);
        $this->expectExceptionMessage('Invalid retention value: must be a number');

        $this->logger($this->cloudwatchConfig(['retention' => 'forever']));
    }

    public static function batchSizeValues()
    {
        return [
            'numeric string from env' => ['500', 500],
            'empty string uses default' => ['', 10000],
            'null uses default' => [null, 10000],
        ];
    }

    #[DataProvider('batchSizeValues')]
    public function testBatchSizeIsConvertedForCwh($value, $expected)
    {
        $handler = $this->logger($this->cloudwatchConfig(['batch_size' => $value]))->getHandlers()[0];

        $this->assertSame($expected, $this->handlerProperty($handler, 'batchSize'));
    }

    public function testNonNumericBatchSizeThrowsException()
    {
        $this->expectException(IncompleteCloudWatchConfig::class);
        $this->expectExceptionMessage('Invalid batch_size value: must be a number');

        $this->logger($this->cloudwatchConfig(['batch_size' => 'big']));
    }

    public function testNullOrMissingNameUsesEmptyLoggerName()
    {
        $config = $this->cloudwatchConfig(['name' => null]);
        $this->assertSame('', $this->logger($config)->getName());

        unset($config['name']);
        $this->assertSame('', $this->logger($config)->getName());
    }

    public function testMissingVersionDefaultsToLatest()
    {
        $config = $this->cloudwatchConfig();
        unset($config['version']);

        $logger = $this->logger($config);

        $this->assertEquals('2014-03-28', $this->awsClient($logger)->getApi()->getApiVersion());
    }

    public function testMissingCredentialsUsesAwsDefaultProviderChain()
    {
        $config = $this->cloudwatchConfig();
        unset($config['credentials']);

        $logger = $this->logger($config);

        $this->assertInstanceOf(CloudWatchLogsClient::class, $this->awsClient($logger));
    }

    private function awsClient(Logger $logger)
    {
        return $this->handlerProperty($logger->getHandlers()[0], 'client');
    }

    private function logger(array $config)
    {
        $provider = new CloudWatchServiceProvider($this->app(['cloudwatch' => $config]));

        return $provider->getLogger();
    }

    private function handlerProperty(CloudWatch $handler, $name)
    {
        return (new \ReflectionProperty(CloudWatch::class, $name))->getValue($handler);
    }
}
