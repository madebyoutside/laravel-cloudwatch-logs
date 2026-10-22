## Logger for Aws Cloud Watch

### Requirements

| | Version |
|---|---|
| PHP | 8.2 to 8.5 |
| Laravel | 10, 11 or 12 |
| maxbanton/cwh | 3.x (Monolog 3) |

Projects on Laravel 9 or older (or PHP below 8.2) must stay on version `1.x`.

### Example

You can use laravel's default `\Log` class to use this

`\Log::info('user logged in', ['id' => 123, 'name' => 'Naren']);`

### Add To Project

```
composer require betalabs/laravel-cloudwatch-logs
```

This package uses laravel's [Package discovery](https://laravel.com/docs/packages#package-discovery), no manual provider registration is needed.

### Configs

Configs for logging is defined at `config/logging.php`. Add `cloudwatch` to the `channels` array

```
'channels' =>  [
    'cloudwatch' => [
        'disabled' => env('DISABLE_CLOUDWATCH_LOG', false),
        'name' => env('CLOUDWATCH_LOG_NAME', ''),
        'region' => env('CLOUDWATCH_LOG_REGION', ''),
        'credentials' => [
            'key' => env('CLOUDWATCH_LOG_KEY', ''),
            'secret' => env('CLOUDWATCH_LOG_SECRET', '')
        ],
        'stream_name' => env('CLOUDWATCH_LOG_STREAM_NAME', 'laravel_app'),
        'retention' => env('CLOUDWATCH_LOG_RETENTION_DAYS', 14),
        'group_name' => env('CLOUDWATCH_LOG_GROUP_NAME', 'laravel_app'),
        'version' => env('CLOUDWATCH_LOG_VERSION', 'latest'),
        'batch_size' => env('CLOUDWATCH_LOG_BATCH_SIZE', 10000),
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],
]
```

Add correct values to keys in your .env file. And it should work.

If the application does not define a `cloudwatch` channel, the package default above is used.

| Key | Required | Accepted values | When empty or missing |
|---|---|---|---|
| `region` | yes | AWS region, e.g. `us-east-1` | throws `IncompleteCloudWatchConfig` |
| `group_name` | yes | non-empty string (numbers are converted) | throws `IncompleteCloudWatchConfig` |
| `stream_name` | yes | non-empty string (numbers are converted) | throws `IncompleteCloudWatchConfig` |
| `retention` | no | number of days, int or numeric string | missing key: 14 days; `null` or `''`: no retention policy (logs never expire); non-numeric: throws `IncompleteCloudWatchConfig` |
| `batch_size` | no | int or numeric string, max 10000 | `10000`; non-numeric: throws `IncompleteCloudWatchConfig` |
| `name` | no | string | empty logger name |
| `version` | no | AWS API version | `latest` |
| `credentials` | no | `['key' => ..., 'secret' => ...]` | AWS SDK default provider chain (environment variables, IAM role, ...) |
| `formatter` | no | Monolog formatter class | `LineFormatter` (also when the class does not exist) |
| `disabled` | no | bool | falls back to `DISABLE_CLOUDWATCH_LOG` (see below) |

Values coming from `env()` are strings: numeric strings such as `"120"` are converted to int, so `CLOUDWATCH_LOG_RETENTION_DAYS=120` works. Be careful with keys whose default comes from another config, e.g. `config('database.connections.mysql.database')`: if it resolves to `null`, the logger throws `IncompleteCloudWatchConfig` with the missing key in the message.

### Disabling

Add `DISABLE_CLOUDWATCH_LOG=true` to your `.env` file to disable the package (no listener, no `cloudwatch.logger` binding, no call to AWS).

**With `php artisan config:cache`** Laravel no longer loads `.env` at runtime, so `env('DISABLE_CLOUDWATCH_LOG')` returns `null` outside config files. To keep the flag working with cached config, the channel must have the `disabled` key:

```
'cloudwatch' => [
    'disabled' => env('DISABLE_CLOUDWATCH_LOG', false),
    // ...
],
```

Precedence: the `disabled` key of the channel wins; when the channel has no `disabled` key the package reads `env('DISABLE_CLOUDWATCH_LOG')` directly (previous behavior).

### Concept

This package relies on laravel's listener for log events. This package DOES NOT replace the default logging, instead adds additional log to AWS CLoud Watch. Hence you do not have to change the default log driver to make this work.

### Upgrading from 1.x

- Requires PHP 8.2+ and Laravel 10, 11 or 12; uses `maxbanton/cwh` 3 and Monolog 3.
- Configuration is validated: empty `group_name`/`stream_name` and non-numeric `retention`/`batch_size` now throw `IncompleteCloudWatchConfig` with a clear message (cwh 3 is strictly typed and would throw a `TypeError` instead).
- Missing `formatter`, `version` or `credentials` keys no longer trigger PHP "Undefined array key" warnings.
- New `disabled` channel key, needed for `DISABLE_CLOUDWATCH_LOG` to work with cached config. The package default config is now merged even when disabled.
- Applications that already run `php artisan config:cache` **without** a `disabled` key keep the previous behavior. Applications that use the package default config and have `DISABLE_CLOUDWATCH_LOG=true` in production will now really be disabled after the next `config:cache`; check the production `.env` before upgrading.
- The special handling of `ErrorException` messages was removed: in the supported Laravel versions (10 to 12) the log message reaches the listener already converted to string, so that branch was never executed. Every message now goes through the `cloudwatch.logger` singleton.

### Testing

```
composer update
vendor/bin/phpunit
```
