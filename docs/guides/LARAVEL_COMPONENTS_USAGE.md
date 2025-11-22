# Laravel Components Usage Guide

## Quick Reference for Laravel Components in FOS-Streaming v70

This guide provides practical examples of how to use Laravel components in the FOS-Streaming application.

## Table of Contents

- [Validation](#validation)
- [Cache](#cache)
- [Queue](#queue)
- [Mail](#mail)
- [Events](#events)
- [Logging](#logging)
- [HTTP Client](#http-client)
- [Redis](#redis)
- [Encryption](#encryption)
- [Collections](#collections)
- [Filesystem](#filesystem)

---

## Validation

### Basic Validation

```php
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

// Setup (do this once in your bootstrap)
$container = Container::getInstance();
$loader = new FileLoader(new Filesystem, __DIR__ . '/lang');
$translator = new Translator($loader, 'en');
$validator = new ValidatorFactory($translator, $container);

// Validate user input
$data = [
    'username' => $_POST['username'] ?? '',
    'email' => $_POST['email'] ?? '',
    'password' => $_POST['password'] ?? '',
];

$rules = [
    'username' => 'required|string|min:3|max:50|alpha_dash',
    'email' => 'required|email|max:255',
    'password' => 'required|string|min:8|confirmed',
];

$validation = $validator->make($data, $rules);

if ($validation->fails()) {
    $errors = $validation->errors()->all();
    // Handle errors
    foreach ($errors as $error) {
        echo $error . "<br>";
    }
} else {
    // Data is valid, proceed
    $validated = $validation->validated();
}
```

### Stream Validation

```php
$streamRules = [
    'stream_url' => 'required|url|regex:/^(rtmp|http|https):\/\//',
    'stream_name' => 'required|string|max:100',
    'transcode_profile' => 'required|integer|between:1,10',
    'user_id' => 'required|integer|exists:users,id',
];

$validation = $validator->make($_POST, $streamRules);
```

### Custom Validation Rules

```php
use Illuminate\Validation\Rule;

$rules = [
    'ip_address' => [
        'required',
        'ip',
        Rule::unique('ip_blocks')->where('status', 'active')
    ],
    'port' => 'required|integer|between:1024,65535',
];
```

---

## Cache

### Setup Cache

```php
use Illuminate\Cache\CacheManager;
use Illuminate\Container\Container;
use Illuminate\Redis\RedisManager;

$app = Container::getInstance();

// Configure Redis
$app['config']['database.redis'] = [
    'client' => 'predis',
    'default' => [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'password' => getenv('REDIS_PASSWORD') ?: null,
        'port' => getenv('REDIS_PORT') ?: 6379,
        'database' => 0,
    ],
];

$app['redis'] = new RedisManager($app, 'predis', $app['config']['database.redis']);
$cache = new CacheManager($app);
```

### Basic Cache Operations

```php
// Store
$cache->put('stream:1234:status', 'active', 3600); // 1 hour

// Retrieve
$status = $cache->get('stream:1234:status');

// With default
$status = $cache->get('stream:1234:status', 'inactive');

// Check existence
if ($cache->has('stream:1234:status')) {
    // ...
}

// Delete
$cache->forget('stream:1234:status');

// Forever (no expiration)
$cache->forever('setting:web_ip', '192.168.1.1');
```

### Cache Remember Pattern

```php
// Cache active streams for 10 minutes
$activeStreams = $cache->remember('streams:active', 600, function() {
    return Stream::where('status', 'active')->get();
});

// Get or store forever
$settings = $cache->rememberForever('app:settings', function() {
    return Setting::all()->pluck('value', 'key')->toArray();
});
```

### Cache Tags (Redis only)

```php
// Tag cache entries
$cache->tags(['streams', 'user:123'])->put('stream:456', $data, 3600);

// Retrieve tagged cache
$data = $cache->tags(['streams', 'user:123'])->get('stream:456');

// Flush all cache with tag
$cache->tags(['user:123'])->flush();
```

---

## Queue

### Setup Queue

```php
use Illuminate\Queue\Capsule\Manager as Queue;

$queue = new Queue;
$queue->addConnection([
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'default',
    'retry_after' => 90,
]);

$queue->setAsGlobal();
```

### Create Job Class

```php
class ProcessStreamJob
{
    protected $stream;

    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    public function handle()
    {
        // Process stream
        $ffmpeg = new FFmpegProcessor();
        $ffmpeg->start($this->stream);

        // Update status
        $this->stream->update(['status' => 'processing']);
    }
}
```

### Dispatch Jobs

```php
// Immediate dispatch
Queue::push(new ProcessStreamJob($stream));

// Delayed dispatch (60 seconds)
Queue::later(60, new ProcessStreamJob($stream));

// Specific queue
Queue::pushOn('high-priority', new ProcessStreamJob($stream));
```

### Queue Worker

```bash
# Run queue worker
php artisan queue:work

# Or with Sail
sail artisan queue:work --queue=streams,default
```

---

## Mail

### Setup Mailer

```php
use Illuminate\Mail\Mailer;
use Illuminate\Mail\TransportManager;
use Swift_Mailer;
use Swift_SmtpTransport;

$app = Container::getInstance();

// Configure mail
$app['config']['mail'] = [
    'driver' => 'smtp',
    'host' => getenv('MAIL_HOST'),
    'port' => getenv('MAIL_PORT'),
    'username' => getenv('MAIL_USERNAME'),
    'password' => getenv('MAIL_PASSWORD'),
    'encryption' => getenv('MAIL_ENCRYPTION'),
    'from' => [
        'address' => getenv('MAIL_FROM_ADDRESS'),
        'name' => getenv('MAIL_FROM_NAME'),
    ],
];

$mailer = new Mailer(
    $app['view'],
    new Swift_Mailer(
        new Swift_SmtpTransport(
            $app['config']['mail.host'],
            $app['config']['mail.port']
        )
    )
);
```

### Send Email

```php
// Simple email
$mailer->raw('Stream has gone offline!', function($message) {
    $message->to('admin@example.com')
            ->subject('Stream Alert');
});

// With Blade template
$data = ['stream' => $stream, 'user' => $user];
$mailer->send('emails.stream-alert', $data, function($message) use ($user) {
    $message->to($user->email)
            ->subject('Your stream is offline');
});

// With attachments
$mailer->send('emails.report', $data, function($message) {
    $message->to('admin@example.com')
            ->subject('Daily Stream Report')
            ->attach('/path/to/report.pdf');
});
```

### Email Template (Blade)

```blade
<!-- views/emails/stream-alert.blade.php -->
<h1>Stream Alert</h1>

<p>Hello {{ $user->username }},</p>

<p>Your stream <strong>{{ $stream->name }}</strong> has gone offline.</p>

<ul>
    <li>Stream ID: {{ $stream->id }}</li>
    <li>Time: {{ now()->toDateTimeString() }}</li>
    <li>Status: {{ $stream->status }}</li>
</ul>

<p>Please check your stream configuration.</p>
```

---

## Events

### Define Event

```php
class StreamStarted
{
    public $stream;

    public function __construct($stream)
    {
        $this->stream = $stream;
    }
}
```

### Define Listener

```php
class NotifyAdminOfStreamStart
{
    public function handle(StreamStarted $event)
    {
        // Send notification
        Mail::to('admin@example.com')->send(
            new StreamStartedMail($event->stream)
        );

        // Log event
        Log::info('Stream started', [
            'stream_id' => $event->stream->id,
            'user_id' => $event->stream->user_id,
        ]);
    }
}
```

### Setup Events

```php
use Illuminate\Events\Dispatcher;

$events = new Dispatcher($app);

// Register listener
$events->listen(StreamStarted::class, NotifyAdminOfStreamStart::class);

// Dispatch event
$events->dispatch(new StreamStarted($stream));
```

---

## Logging

### Setup Logger

```php
use Illuminate\Log\LogManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$app['config']['logging.channels'] = [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'security'],
    ],
    'daily' => [
        'driver' => 'daily',
        'path' => __DIR__ . '/logs/app.log',
        'level' => 'debug',
        'days' => 14,
    ],
    'security' => [
        'driver' => 'daily',
        'path' => __DIR__ . '/logs/security.log',
        'level' => 'info',
        'days' => 30,
    ],
];

$log = new LogManager($app);
```

### Logging Examples

```php
// Different log levels
$log->emergency('System is down');
$log->alert('Database connection lost');
$log->critical('Disk space critically low');
$log->error('Stream failed to start', ['stream_id' => 123]);
$log->warning('High CPU usage detected', ['cpu' => 95]);
$log->notice('Scheduled maintenance in 1 hour');
$log->info('User logged in', ['user_id' => 456]);
$log->debug('Stream URL parsed', ['url' => $url]);

// With context
$log->channel('security')->info('Login attempt', [
    'username' => $username,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'success' => true,
]);

// Multiple channels
$log->stack(['daily', 'security'])->warning('Suspicious activity');
```

---

## HTTP Client

### Basic Requests

```php
use Illuminate\Http\Client\Factory as Http;

$http = new Http;

// GET request
$response = $http->get('https://api.example.com/streams');
$data = $response->json();

// POST request
$response = $http->post('https://api.example.com/streams', [
    'name' => 'My Stream',
    'url' => 'rtmp://example.com/live',
]);

// PUT/PATCH
$response = $http->put('https://api.example.com/streams/123', $data);
$response = $http->patch('https://api.example.com/streams/123', $data);

// DELETE
$response = $http->delete('https://api.example.com/streams/123');
```

### Advanced Usage

```php
// With headers
$response = $http->withHeaders([
    'Authorization' => 'Bearer ' . $token,
    'Accept' => 'application/json',
])->get('https://api.example.com/streams');

// With timeout
$response = $http->timeout(30)->get('https://api.example.com/check');

// With retry
$response = $http->retry(3, 100)->get('https://api.example.com/streams');

// Check response
if ($response->successful()) {
    $data = $response->json();
} elseif ($response->failed()) {
    $error = $response->body();
}

// Status codes
$status = $response->status();
$isOk = $response->ok(); // 200
$isCreated = $response->created(); // 201
$isServerError = $response->serverError(); // 5xx
```

---

## Redis

### Basic Operations

```php
use Illuminate\Redis\RedisManager;

$redis = $app['redis'];

// Strings
$redis->set('stream:1234:status', 'active');
$status = $redis->get('stream:1234:status');

// With expiration (seconds)
$redis->setex('stream:1234:temp', 3600, 'processing');

// Increment/Decrement
$redis->incr('stats:total_streams');
$redis->decr('stats:active_streams');
$redis->incrby('stats:bandwidth', 1024);

// Lists
$redis->lpush('queue:streams', json_encode($streamData));
$item = $redis->rpop('queue:streams');
$length = $redis->llen('queue:streams');

// Sets
$redis->sadd('users:online', $userId);
$redis->srem('users:online', $userId);
$onlineUsers = $redis->smembers('users:online');
$isOnline = $redis->sismember('users:online', $userId);

// Sorted Sets (leaderboard)
$redis->zadd('streams:popular', $viewCount, $streamId);
$topStreams = $redis->zrevrange('streams:popular', 0, 9); // Top 10

// Hashes
$redis->hset('stream:1234', 'name', 'My Stream');
$redis->hset('stream:1234', 'viewers', 100);
$streamData = $redis->hgetall('stream:1234');
$viewers = $redis->hget('stream:1234', 'viewers');

// Keys
$redis->del('stream:1234');
$redis->exists('stream:1234');
$allStreams = $redis->keys('stream:*');
$redis->expire('temp:data', 300); // 5 minutes
```

### Pub/Sub

```php
// Publish
$redis->publish('stream:events', json_encode([
    'event' => 'started',
    'stream_id' => 1234,
]));

// Subscribe (blocking)
$redis->subscribe(['stream:events'], function($message) {
    $data = json_decode($message, true);
    // Handle event
});
```

---

## Encryption

### Basic Encryption

```php
use Illuminate\Encryption\Encrypter;

$key = getenv('APP_KEY') ?: 'base64:' . base64_encode(random_bytes(32));
$encrypter = new Encrypter($key, 'AES-256-CBC');

// Encrypt
$encrypted = $encrypter->encrypt('sensitive data');

// Decrypt
$decrypted = $encrypter->decrypt($encrypted);

// Encrypt string
$encryptedString = $encrypter->encryptString('my secret');
$decryptedString = $encrypter->decryptString($encryptedString);
```

---

## Collections

### Working with Collections

```php
use Illuminate\Support\Collection;

// Create collection
$streams = collect([
    ['id' => 1, 'name' => 'Stream A', 'viewers' => 100],
    ['id' => 2, 'name' => 'Stream B', 'viewers' => 250],
    ['id' => 3, 'name' => 'Stream C', 'viewers' => 75],
]);

// Filter
$popular = $streams->filter(function($stream) {
    return $stream['viewers'] > 100;
});

// Map
$names = $streams->map(function($stream) {
    return $stream['name'];
});

// Sort
$sorted = $streams->sortByDesc('viewers');

// Pluck
$ids = $streams->pluck('id');
$nameById = $streams->pluck('name', 'id');

// Sum
$totalViewers = $streams->sum('viewers');

// Average
$avgViewers = $streams->avg('viewers');

// Group by
$byCategory = $streams->groupBy('category_id');

// Chunk
$streams->chunk(50)->each(function($chunk) {
    // Process 50 at a time
});
```

---

## Filesystem

### Local Storage

```php
use Illuminate\Filesystem\Filesystem;

$files = new Filesystem;

// Read
$content = $files->get('/path/to/file.txt');

// Write
$files->put('/path/to/file.txt', 'content');
$files->append('/path/to/file.txt', 'more content');

// Check
$exists = $files->exists('/path/to/file.txt');
$isFile = $files->isFile('/path/to/file.txt');
$isDir = $files->isDirectory('/path/to/dir');

// Delete
$files->delete('/path/to/file.txt');
$files->deleteDirectory('/path/to/dir');

// Copy/Move
$files->copy('/source/file.txt', '/dest/file.txt');
$files->move('/source/file.txt', '/dest/file.txt');

// List files
$fileList = $files->files('/path/to/dir');
$allFiles = $files->allFiles('/path/to/dir');
$directories = $files->directories('/path/to/dir');
```

---

## Best Practices

1. **Container Usage**: Use dependency injection where possible
2. **Configuration**: Store all config in `.env` file
3. **Caching**: Cache expensive operations (DB queries, API calls)
4. **Logging**: Log all important events with context
5. **Validation**: Always validate user input
6. **Queues**: Use queues for long-running tasks
7. **Collections**: Use collections for array manipulation
8. **Error Handling**: Wrap operations in try-catch blocks

---

## Further Reading

- [Laravel 10 Documentation](https://laravel.com/docs/10.x)
- [Illuminate Components](https://github.com/illuminate)
- [Laravel Sail Guide](LARAVEL_SAIL_GUIDE.md)

---

**FOS-Streaming v70** - Powered by Laravel Components
