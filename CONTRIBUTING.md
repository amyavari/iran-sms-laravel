# CONTRIBUTING

Contributions are welcome, and are accepted via pull requests.
Please review these guidelines before submitting any pull requests.

## Process

1. Fork the project
1. Create a new branch
1. Code, test, commit and push
1. Open a pull request detailing your changes. Make sure to follow the [template](.github/PULL_REQUEST_TEMPLATE.md)

## Guidelines

- Please ensure the coding style running `composer lint`.
- Send a coherent commit history, making sure each individual commit in your pull request is meaningful.
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- Please remember that we follow [SemVer](http://semver.org/).
- To easily add a new driver (SMS provider), follow the step-by-step guide in the [Add a New SMS Driver](#add-a-new-sms-driver) section.

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Lint

Lint your code:

```bash
composer lint
```

## Tests

Run all tests:

```bash
composer test
```

Check types:

```bash
composer test:types
```

Unit tests:

```bash
composer test:unit
```

## Add a New SMS Driver

Adding a new SMS driver (provider) is super easy. Follow these step-by-step instructions to integrate your driver without needing to understand the internal structure of this package.

### Step 1: Add Driver Configuration

Add your driver configuration to [`./config/iran-sms.php`](./config/iran-sms.php)

**Note:** Use **snake_case** for the driver (provider) name throughout all files.

```php
'providers' => [

    // Other drivers...

    'your_name' => [
        'username' => env('SMS_USERNAME', ''),
        'password' => env('SMS_PASSWORD', ''),
        'token' => env('SMS_TOKEN', ''),
        'from' => env('SMS_FROM', ''),
    ],
],
```

### Step 2: Create the Driver Class

Create a new class for your provider inside [`./src/Drivers/`](./src/Drivers/). The class must extend `AliYavari\IranSms\Abstracts\Driver` and implement the required methods using the simplest and most efficient approach supported by your provider.

**Note:** The constructor receives the configuration values by name, so its parameter names must match the config keys.

**Note:** If a message type is not supported by your provider, throw `AliYavari\IranSms\Exceptions\UnsupportedMethodException` using its static make() method. To get the driver name dynamically within your class, use `$this->getDriverName()`.

```php
use AliYavari\IranSms\Abstracts\Driver;
use AliYavari\IranSms\Exceptions\UnsupportedMethodException;

final class YourNameDriver extends Driver
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $token,
        private readonly string $from,
    ) {}

    public function credit(): int
    {
        // Return the provider credit balance in Rials
    }

    protected function getDefaultSender(): string
    {
        return $this->from;
    }

    protected function sendOtp(string $phone, string $message, string $from): void
    {
        // Implement OTP sending logic here
    }

    protected function sendPattern(array $phones, string $patternCode, array $variables, string $from): void
    {
        // Implement pattern SMS logic here
    }

    protected function sendText(array $phones, string $message, string $from): void
    {
        // Implement plain text SMS logic here
    }

    protected function isSuccessful(): bool
    {
        // Return true or false based on the provider response
    }

    protected function getErrorMessage(): string
    {
        // Return a human-readable error message from the provider
    }

    protected function getErrorCode(): string|int
    {
        // Return raw error code from the provider
    }
}

```

### Step 3: Register the Driver in the Service Provider

Add your driver to the `DRIVERS` map in [`./src/IranSmsServiceProvider.php`](./src/IranSmsServiceProvider.php). The key must be the same as the config key from Step 1.

```php
use AliYavari\IranSms\Drivers\YourNameDriver;

private const array DRIVERS = [
    // Other drivers...

    'your_name' => YourNameDriver::class,
];
```
