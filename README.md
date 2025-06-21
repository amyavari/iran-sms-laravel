# Iran SMS Laravel

<img src="https://banners.beyondco.de/Iran%20SMS%20Laravel.png?theme=dark&packageManager=composer+require&packageName=amyavari%2Firan-sms-laravel&pattern=architect&style=style_1&description=Send+SMS+through+Iranian+SMS+providers+with+ease&md=1&showWatermark=1&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg">

![PHP Version](https://img.shields.io/packagist/php-v/amyavari/iran-sms-laravel)
![Packagist Version](https://img.shields.io/packagist/v/amyavari/iran-sms-laravel?label=version)
![Packagist Downloads](https://img.shields.io/packagist/dt/amyavari/iran-sms-laravel)
![Packagist License](https://img.shields.io/packagist/l/amyavari/iran-sms-laravel)
![Tests](https://img.shields.io/github/actions/workflow/status/amyavari/iran-sms-laravel/tests.yml?label=tests)

A simple and convenient way to send SMS through Iranian SMS providers.

To view the Persian documentation, please refer to [README_FA.md](./README_FA.md).

برای مشاهده راهنمای فارسی، لطفاً به فایل [README_FA.md](./README_FA.md) مراجعه کنید.

**WARNING: This package is under development. DON'T use it yet.**

## Requirements

- PHP version `8.2.0` or higher
- Laravel `10.*`, `11.*`, or `12.*`

## List of Available SMS Providers

| Provider Name (EN) | Provider Name (FA) | Provider Website   | Provider Key | Version    |
| ------------------ | ------------------ | ------------------ | ------------ | ---------- |
| Trez               | رایگان اس‌ام‌اس    | [smspanel.trez.ir] | `trez`       | Unreleased |
| Kavenegar          | کاوه نگار          | [kavenegar.com]    | `kavenegar`  | Unreleased |
| SMS Online         | اس‌ام‌اس آنلاین    | [smsonline.ir]     | `sms_online` | Unreleased |
| Magfa              | مگفا               | [magfa.com]        | `magfa`      | Unreleased |
| Avanak             | آوانک              | [avanak.ir]        | `avanak`     | Unreleased |

## Installation

To install the package via Composer, run:

```bash
composer require amyavari/iran-sms-laravel
```

## Publish Vendor Files

### Publish All Files

To publish all vendor files (config and migrations):

```bash
php artisan iran-sms:install
```

**Note:** To create tables from migrations:

```bash
php artisan migrate
```

### Publish Specific Files

To publish only the config file:

```bash
php artisan vendor:publish --tag=iran-sms-config
```

To publish only the migration file:

```bash
php artisan vendor:publish --tag=iran-sms-migrations
```

**Note:** To create tables from migrations:

```bash
php artisan migrate
```

## Configuration

### Single Provider Setup

To configure a single SMS provider, add the following to your `.env` file:

```env
# Default provider
SMS_PROVIDER=<default_provider>

# If provider uses username and password
SMS_USERNAME=<username>
SMS_PASSWORD=<password>

# If provider uses an API token
SMS_TOKEN=<api_token>

# Default sender number
SMS_FROM=<default_sender_number>
```

**Note:** For the `SMS_PROVIDER`, refer to the `Provider Key` column in the [List of Available SMS Providers](#list-of-available-sms-providers).

### Multiple Provider Setup

After publishing the config file (see [Publish Vendor Files](#publish-vendor-files)), you can customize the environment variable names for each provider you want to use. Then, define those variables in your `.env` file.

For example, to configure the `trez` provider:

```php
'providers' => [

    'trez' => [
        'username' => env('SMS_TREZ_USERNAME', ''), // Previously: env('SMS_USERNAME', '')
        'password' => env('SMS_TREZ_PASSWORD', ''), // Previously: env('SMS_PASSWORD', '')
        'token'    => env('SMS_TREZ_TOKEN', ''),    // Previously: env('SMS_TOKEN', '')
        'from'     => env('SMS_TREZ_FROM', ''),     // Previously: env('SMS_FROM', '')
    ],

    // Repeat this structure for any other providers you want to configure
],
```

Define the corresponding variables in your `.env` file:

```env
SMS_TREZ_USERNAME=<your_username>
SMS_TREZ_PASSWORD=<your_password>
SMS_TREZ_TOKEN=<your_token>
SMS_TREZ_FROM=<your_sender_number>

# Repeat for other providers you defined
```

## Usage

**Note:** This package supports fluent method chaining like `Sms::provider()->otp()->log()->send();`, but for simplicity, this manual demonstrates usage with separate instances.

### Creating an SMS Instance

You can create an SMS instance using the facade provided by the package:

```php
use AliYavari\IranSms\Facades\Sms;

// Using the default provider
$sms = Sms::otp(string $phone, string $message);
$sms = Sms::text(string|array $phones, string $message);
$sms = Sms::pattern(string|array $phones, string $patternCode, array $variables);

// Using a specific provider
$sms = Sms::provider(string $provider)->otp(...);
$sms = Sms::provider(string $provider)->text(...);
$sms = Sms::provider(string $provider)->pattern(...);
```

**Note:** For the `$provider` name, refer to the `Provider Key` column in the [List of Available SMS Providers](#list-of-available-sms-providers).

### Automatic Logging

You can chain log configurations on your SMS instance before sending.

To help keep your code clean and logging consistent, especially when managing SMS sending from a central location, this package provides convenient methods to configure logging behavior based on the SMS type and sending status.

**Note:** Before using any logging features, make sure to create the necessary tables. (See [Publish Vendor Files](#publish-vendor-files).)

#### Log Based on SMS Type

```php
$sms->log(bool $log = true);           // Log any type of SMS
$sms->logOtp(bool $log = true);        // Log only OTP messages
$sms->logText(bool $log = true);       // Log only text messages
$sms->logPattern(bool $log = true);    // Log only pattern messages
```

#### Log Based on Sending Status

**Note:** These methods implicitly enable logging. If you use them without calling a `log*()` method first, `log(true)` will be applied automatically.

```php
$sms->logSuccessful(); // Log only if the message was sent successfully
$sms->logFailed();     // Log only if the message failed to send
```

#### Make Your Log Behavior Fluent

You can chain the logging methods to define custom logic fluently:

```php
// Example: Log all message types except OTPs, only if they are sent successfully
$sms->log()->logOtp(false)->logSuccessful();
```

### Sending SMS

To send the SMS:

```php
$sms->send();
```

**Note:** This method throws an exception if a client or server error occurs. See the `throw` method in [HTTP Client].

### Checking Sending Status

To check the status after sending:

```php
$sms->successful(); // bool
$sms->failed();     // bool

// Get the error message (returns null if successful)
$sms->error();      // string|null
```

## Working with Queues and Notifications

### Queues

To send an SMS instance using [queues], you can [create an SMS instance](#creating-an-sms-instance) and dispatch it to a job where you call the `send()` method. You can use the `AliYavari\IranSms\Contracts\Sms` interface as a constructor type-hint.

**Note:** It's recommended to configure log options here to keep your code clean and consistent.

Example:

```php
namespace App\Jobs;

use AliYavari\IranSms\Contracts\Sms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendSms implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private Sms $sms) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->sms->log(true)->send();
    }
}
```

### Notifications

To send SMS using [notifications], define a `toSms` method in your notification class and return an SMS instance. Also, include `AliYavari\IranSms\Channels\SmsChannel` in the `via` method.

**Note:** The `toSms` method must return an SMS instance with your log setup (if desired). The channel will handle sending it.

Example:

```php
namespace App\Notifications;

use AliYavari\IranSms\Channels\SmsChannel;
use AliYavari\IranSms\Facades\Sms;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class MyNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification channels.
     */
    public function via(object $notifiable): array
    {
        return [SmsChannel::class];
    }

    /**
     * Get the voice representation of the notification.
     */
    public function toSms(object $notifiable)
    {
        return Sms::text($notifiable->phone, 'Hi')->logFailed();
    }
}
```

## Testing

This package provides fluent methods to fake and test SMS sending:

```php
use AliYavari\IranSms\Facades\Sms;

// Fake the default provider to return successful responses
Sms::fake();

// Fake specific providers to return successful responses
// Note: Use `default` as the provider key to target the default provider
Sms::fake([/* provider keys */]);

// Equivalent to the above (explicit success)
Sms::fake([...], Sms::successfulRequest());

// Fake providers to return failed responses (optional custom error message)
Sms::fake([...], Sms::failedRequest(string $errorMessage = 'Error Message'));

// Fake providers to throw a ConnectionException
Sms::fake([...], Sms::failedConnection());

// Define different behaviors per provider
Sms::fake([
    'provider_one' => Sms::failedConnection(),
    'provider_two' => Sms::failedRequest(),
    'provider_three' => Sms::failedConnection(),
]);
```

**Note:** Defining both _global behavior_ and _per-provider behaviors_ together is not allowed in a single call. Use one strategy per `fake()` call.

**Note:** If you define multiple behaviors for the same provider, the last one will override the previous definitions.

## Contributing

Thank you for considering contributing to the Iran SMS Laravel! The contribution guide can be found in the [CONTRIBUTING.md](CONTRIBUTING.md)

## License

**Iran SMS Laravel** was created by **[Ali Mohammad Yavari](https://www.linkedin.com/in/ali-m-yavari/)** under the **[MIT license](https://opensource.org/licenses/MIT)**.

<!-- Links -->

[smspanel.trez.ir]: http://smspanel.trez.ir/
[kavenegar.com]: https://kavenegar.com/
[smsonline.ir]: https://smsonline.ir/
[magfa.com]: https://magfa.com/
[avanak.ir]: https://www.avanak.ir/
[HTTP Client]: https://laravel.com/docs/12.x/http-client#throwing-exceptions
[queues]: https://laravel.com/docs/12.x/queues
[notifications]: https://laravel.com/docs/12.x/notifications
