<?php

namespace Pagevamp\Providers;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Illuminate\Support\ServiceProvider;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Pagevamp\Exceptions\IncompleteCloudWatchConfig;

class CloudWatchServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (!$this->isDisabled()) {
            $app = $this->app;
            $app['log']->listen(function () use ($app) {
                $args = func_get_args();

	            // Laravel 5.4 returns a MessageLogged instance only
	            if (count($args) == 1) {
		            $level = $args[0]->level;
		            $message = $args[0]->message;
		            $context = $args[0]->context;
	            } else {
		            $level = $args[0];
		            $message = $args[1];
		            $context = $args[2];
	            }

                if ($app['cloudwatch.logger'] instanceof Logger) {
                    $app['cloudwatch.logger']->log($level, $message, $context);
                }
            });
        }
    }

    public function getLogger()
    {
        $cwClient = new CloudWatchLogsClient($this->getCredentials());
        $loggingConfig = $this->app->make('config')->get('logging.channels.cloudwatch');

        $streamName = $this->requiredString($loggingConfig, 'stream_name');
        $groupName = $this->requiredString($loggingConfig, 'group_name');
        // Missing key keeps the cwh default (14 days); null or '' means no
        // retention policy (logs never expire)
        $retentionDays = array_key_exists('retention', $loggingConfig)
            ? $this->optionalInt($loggingConfig, 'retention', null)
            : 14;
        $batchSize = $this->optionalInt($loggingConfig, 'batch_size', 10000);

        $logHandler = new CloudWatch($cwClient, $groupName, $streamName, $retentionDays, $batchSize);
        $logger = new Logger((string) ($loggingConfig['name'] ?? ''));

        $formatter = $this->resolveFormatter($loggingConfig);
        $logHandler->setFormatter($formatter);
        $logger->pushHandler($logHandler);

        return $logger;
    }

    /**
     * Resolve a Formatter instance from configurations or use LineFormatter
     * as default.
     *
     * @param array $configs
     *
     * @return \Monolog\Formatter\LineFormatter
     */
    private function resolveFormatter(array $configs)
    {
        $formatter = new LineFormatter(
            '%channel%: %level_name%: %message% %context% %extra%',
            null, false, true
        );

        if (($configs['formatter'] ?? null) && class_exists($configs['formatter'])) {
            $formatter = $this->app->make($configs['formatter']);
        }

        return $formatter;
    }

    /**
     * Code "inspired" from here
     * https://aws.amazon.com/blogs/developer/php-application-logging-with-amazon-cloudwatch-logs-and-monolog
     * Laravel installation mentioned here did not work but PHP with Monolog worked, hence this package.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/logging.php',
            'logging.channels'
        );

        if (!$this->isDisabled()) {
            $this->app->singleton('cloudwatch.logger', function () {
                return $this->getLogger();
            });
        }
    }

    /**
     * The "disabled" key of the cloudwatch channel wins; without it falls back
     * to env('DISABLE_CLOUDWATCH_LOG'), which returns null once the
     * configuration is cached (php artisan config:cache).
     *
     * @return bool
     */
    protected function isDisabled()
    {
        $disabled = $this->app->make('config')->get('logging.channels.cloudwatch.disabled');

        if (is_null($disabled)) {
            $disabled = env('DISABLE_CLOUDWATCH_LOG');
        }

        return (bool) $disabled;
    }

    /**
     * cwh 3 requires a non-empty string for group and stream names.
     *
     * @param array  $config
     * @param string $key
     *
     * @return string
     * @throws \Pagevamp\Exceptions\IncompleteCloudWatchConfig
     */
    private function requiredString(array $config, $key)
    {
        $value = $config[$key] ?? null;

        if (!is_scalar($value) || '' === trim((string) $value)) {
            throw new IncompleteCloudWatchConfig('Missing '.$key.' key-value');
        }

        return (string) $value;
    }

    /**
     * cwh 3 types retention and batch size as int: numeric strings (from
     * env()) are converted, null/'' use the default.
     *
     * @param array    $config
     * @param string   $key
     * @param int|null $default
     *
     * @return int|null
     * @throws \Pagevamp\Exceptions\IncompleteCloudWatchConfig
     */
    private function optionalInt(array $config, $key, $default)
    {
        $value = $config[$key] ?? null;

        if (is_null($value) || '' === $value) {
            return $default;
        }

        if (!is_numeric($value)) {
            throw new IncompleteCloudWatchConfig('Invalid '.$key.' value: must be a number');
        }

        return (int) $value;
    }

    /**
     * This is the way config should be defined in config/logging.php
     * in key cloudwatch.
     *
     * 'cloudwatch' => [
     *     'name' => env('CLOUDWATCH_LOG_NAME', ''),
     *     'region' => env('CLOUDWATCH_LOG_REGION', ''),
     *     'credentials' => [
     *         'key' => env('CLOUDWATCH_LOG_KEY', ''),
     *         'secret' => env('CLOUDWATCH_LOG_SECRET', '')
     *     ],
     *     'stream_name' => env('CLOUDWATCH_LOG_STREAM_NAME', 'laravel_app'),
     *     'retention' => env('CLOUDWATCH_LOG_RETENTION_DAYS', 14),
     *     'group_name' => env('CLOUDWATCH_LOG_GROUP_NAME', 'laravel_app'),
     *     'version' => env('CLOUDWATCH_LOG_VERSION', 'latest'),
     * ]
     *
     * @return array
     * @throws \Pagevamp\Exceptions\IncompleteCloudWatchConfig
     */
    protected function getCredentials()
    {
        $loggingConfig = $this->app->make('config')->get('logging.channels');

        if (!isset($loggingConfig['cloudwatch'])) {
            throw new IncompleteCloudWatchConfig('Configuration Missing for Cloudwatch Log');
        }

        $cloudWatchConfigs = $loggingConfig['cloudwatch'];

        if (!isset($cloudWatchConfigs['region'])) {
            throw new IncompleteCloudWatchConfig('Missing region key-value');
        }

        $awsCredentials = [
            'region' => $cloudWatchConfigs['region'],
            'version' => $cloudWatchConfigs['version'] ?? 'latest',
        ];

        // Without credentials the AWS SDK uses its default provider chain
        // (environment, IAM role, ...)
        if (isset($cloudWatchConfigs['credentials'])) {
            $awsCredentials['credentials'] = $cloudWatchConfigs['credentials'];
        }

        return $awsCredentials;
    }
}
