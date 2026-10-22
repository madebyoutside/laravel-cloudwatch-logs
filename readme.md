## Logger for Aws Cloud Watch

### Example

You can use laravel's default `\Log` class to use this

`\Log::info('user logged in', ['id' => 123, 'name' => 'Naren']);`

### Configs 

Configs for logging is defined at `config/logging.php`. Add `cloudwatch` to the `channels` array

```
'channels' =>  [
    'cloudwatch' => [
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
        ],
]
```

Add correct values to keys in your .env file. And it should work. 

### Add To Project
 
#### Laravel 5.5 or Higher

This package uses laravel's [Package discovery](https://laravel.com/docs/5.6/packages#package-discovery). To disable this package by default you can add `DISABLE_CLOUDWATCH_LOG=true` to you local `.env` file and this package will be disabled.

#### Laravel 5.4 or Lower

Add to the `providers` array in `config/app.php`:

```
Pagevamp\Providers\CloudWatchServiceProvider::class
```

### Concept

This package relies on laravel's listener for log events. This package DOES NOT replace the default logging, instead adds additional log to AWS CLoud Watch. Hence you do not have to change the default log driver to make this work.
