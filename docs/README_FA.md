# ارسال پیامک با سرویس دهنده های ایرانی با لاراول

یک روش ساده و راحت برای ارسال پیامک از طریق ارائه‌دهندگان پیامک ایرانی.

## پیش‌نیازها

- نسخه PHP `8.2.0` یا بالاتر
- نسخه Laravel `^11.32` یا `^12.0.1`

## فهرست ارائه‌دهندگان پیامک

| نسخه       | کلید ارائه‌دهنده | وب‌سایت ارائه‌دهنده | نام فارسی ارائه‌دهنده | نام انگلیسی ارائه‌دهنده |
| ---------- | ---------------- | ------------------- | --------------------- | ----------------------- |
| منتشر نشده | `sms_ir`         | [sms.ir]            | اس ام اس دات آی آر    | SMS.ir                  |
| منتشر نشده | `meli_payamak`   | [melipayamak.com]   | ملی پیامک             | Meli Payamak            |
| منتشر نشده | `payam_resan`    | [payam-resan.com]   | پیام رسان             | Payam Resan             |
| منتشر نشده | `kavenegar`      | [kavenegar.com]     | کاوه نگار             | Kavenegar               |
| منتشر نشده | `faraz_sms`      | [farazsms.com]      | فراز اس ام اس         | Faraz SMS               |
| منتشر نشده | `raygan_sms`     | [raygansms.com]     | رایگان اس ام اس       | Raygan SMS              |

> [!CAUTION]
> هر ارائه‌دهنده قوانین خاص خود را برای ارسال پیامک دارد. لطفاً فایل [providers_note_fa.md](./providers_note_fa.md) را بررسی کنید.

## نصب

برای نصب بسته از طریق Composer دستور زیر را اجرا کنید:

```bash
composer require amyavari/iran-sms-laravel
```

## انتشار فایل‌های Vendor

### انتشار همه فایل‌ها

برای انتشار تمام فایل‌های vendor (پیکربندی و دیتابیس):

```bash
php artisan iran-sms:install
```

**نکته:** برای ساخت جدول‌ها از طریق فایل های دیابیس:

```bash
php artisan migrate
```

### انتشار فایل‌های خاص

برای انتشار فقط فایل پیکربندی:

```bash
php artisan vendor:publish --tag=iran-sms-config
```

برای انتشار فقط فایل دیتابیس:

```bash
php artisan vendor:publish --tag=iran-sms-migrations
```

**نکته:** برای ساخت جدول‌ها از طریق فایل های دیتابیس:

```bash
php artisan migrate
```

## پیکربندی

### پیکربندی برای استفاده از یک ارائه دهنده

برای تنظیم یک ارائه‌دهنده پیامک، موارد زیر را به فایل `.env` اضافه کنید:

```env
# ارائه‌دهنده پیش‌فرض
SMS_PROVIDER=<default_provider>

# اگر ارائه‌دهنده از نام کاربری و رمز عبور استفاده می‌کند
SMS_USERNAME=<username>
SMS_PASSWORD=<password>

# اگر ارائه‌دهنده از توکن استفاده می‌کند
SMS_TOKEN=<api_token>

# شماره فرستنده پیش‌فرض
SMS_FROM=<default_sender_number>
```

**نکته:** برای `SMS_PROVIDER` از ستون `کلید ارائه‌دهنده` در [فهرست ارائه‌دهندگان پیامک](#فهرست-ارائه‌دهندگان-پیامک) استفاده کنید.

### پیکربندی برای استفاده از چند ارائه دهنده به صورت همزمان

پس از انتشار فایل پیکربندی (بخش [انتشار فایل‌های Vendor](#انتشار-فایلهای-vendor)) می‌توانید متغیرهای جداگانه برای هر ارائه‌دهنده تعریف کرده و در فایل `.env` تنظیم نمایید.

مثال برای ارائه‌دهنده‌ی `trez`:

```php
'providers' => [

    'trez' => [
        'username' => env('SMS_TREZ_USERNAME', ''), // قبلی: env('SMS_USERNAME', '')
        'password' => env('SMS_TREZ_PASSWORD', ''), // قبلی: env('SMS_PASSWORD', '')
        'token'    => env('SMS_TREZ_TOKEN', ''),    // قبلی: env('SMS_TOKEN', '')
        'from'     => env('SMS_TREZ_FROM', ''),     // قبلی: env('SMS_FROM', '')
    ],

    // ساختار بالا را برای دیگر ارائه‌دهندگان تکرار کنید
],
```

و سپس متغیرهای زیر را در فایل `.env` تعریف کنید:

```env
SMS_TREZ_USERNAME=<your_username>
SMS_TREZ_PASSWORD=<your_password>
SMS_TREZ_TOKEN=<your_token>
SMS_TREZ_FROM=<your_sender_number>
```

## استفاده

**نکته:** این بسته از chain کردن متدها به‌صورت fluent پشتیبانی می‌کند، مانند:
`Sms::provider()->otp()->log()->send();`
اما برای سادگی، در این راهنما از نمونه‌های مجزا استفاده شده است.

### ایجاد پیامک

برای ایجاد یک پیامک می توانید از کلاس Facade این پکیج استفاده کنید:

```php
use AliYavari\IranSms\Facades\Sms;

// با استفاده از ارائه‌دهنده پیش‌فرض
$sms = Sms::otp(string $phone, string $message);
$sms = Sms::text(string|array $phones, string $message);
$sms = Sms::pattern(string|array $phones, string $patternCode, array $variables);

// با استفاده از ارائه‌دهنده خاص
$sms = Sms::provider(string $provider)->otp(...);
$sms = Sms::provider(string $provider)->text(...);
$sms = Sms::provider(string $provider)->pattern(...);
```

**نکته:** برای `$provider` از ستون `کلید ارائه‌دهنده` در [فهرست ارائه‌دهندگان پیامک](#فهرست-ارائه‌دهندگان-پیامک) استفاده کنید.

### لاگ‌گیری خودکار

متدهای مختلفی برای لاگ‌گیری قبل از ارسال SMS وجود دارد که شما می توانید به آبجکت پیامک ایجاد شده اضافه کنید:

#### بر اساس نوع پیامک:

```php
$sms->log(bool $log = true);             // همه پیامک‌ها
$sms->logOtp(bool $log = true);          // فقط پیامک‌های OTP
$sms->logText(bool $log = true);         // فقط پیامک‌های متنی
$sms->logPattern(bool $log = true);      // فقط پیامک‌های الگو
```

#### بر اساس وضعیت ارسال:

**نکته:** این متدها حالت لاگ بر اساس نوع را فعال می کنند، یعنی اگر هیچکدام از متدهای `log*()` را قبل از آنها فراخوانی نکرده باشید، متد `log(true)` را فرا می خوانند.

```php
$sms->logSuccessful();   // فقط اگر ارسال موفق باشد
$sms->logFailed();       // فقط اگر ارسال ناموفق باشد
```

#### ترکیب Fluent:

شما می توانید از ترکیب متدهای قبلی برای ایجاد شرایط دلخواه استفاده کنید:

```php
// همه انواع پیامک بجز یکبار رمز را فقط در صورتی که ارسال موفق بود لاگ بگیر
$sms->log()->logOtp(false)->logSuccessful();
```

#### پاک‌سازی لاگ‌های قدیمی:

برای تمیز نگه داشتن جدول لاگ، این بسته یک دستور Artisan برای حذف لاگ‌های قدیمی فراهم کرده است. می‌توانید این دستور را با استفاده از [Laravel's task scheduler] زمان‌بندی کنید.

مثال: حذف لاگ‌هایی که بیش از ۳۰ روز از ایجادشان گذشته است.

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('iran-sms:prune-logs --days=30')->daily();
```

### ارسال پیامک

برای ارسال:

```php
$sms->send();
```

**نکته:** این متد در صورت بروز خطای کلاینت یا سرور، یک exception می دهد. برای اطلاع بیشتر [HTTP Client] را بررسی کنید.

### بررسی وضعیت ارسال

```php
$sms->successful(); // bool
$sms->failed();     // bool

// در صورت ارسال موفق null خروجی می دهد.
$sms->error();      // string|null
```

## استفاده با صف‌ها و نوتیفیکیشن‌ها

### صف‌ها

برای ارسال یک پیامک با استفاده از \[صف‌ها (queues)]، می‌توانید [یک نمونه پیامک ایجاد کنید](#ایجاد-پیامک) و آن را به یک job ارسال کنید که در آن متد `send()` را فراخوانی می‌کنید. می‌توانید از اینترفیس `AliYavari\IranSms\Contracts\Sms` به عنوان type-hint در استفاده کنید.

**نکته:** توصیه می‌شود تنظیمات مربوط به لاگ را در همین‌جا پیکربندی کنید تا کد شما تمیز و منسجم باقی بماند.

مثال:

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

### نوتیفیکیشن‌ها

برای ارسال پیامک با استفاده از \[Notificationها]، یک متد `toSms` در کلاس نوتیفیکیشن خود تعریف کرده و یک نمونه پیامک بازگردانید. همچنین، کانال `AliYavari\IranSms\Channels\SmsChannel` را در متد `via` قرار دهید.

**نکته:** متد `toSms` باید یک نمونه از کلاس پیامک (SMS) را همراه با تنظیمات لاگ (در صورت نیاز) بازگرداند. این کانال مسئول ارسال پیامک خواهد بود.

مثال:

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
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable)
    {
        return Sms::text($notifiable->phone, 'Hi')->logFailed();
    }
}
```

## تست

در این بخش از پکیج، می‌توانید ارسال پیامک‌ها را فیک (Fake) کنید تا بدون تماس واقعی با ارائه‌دهنده انجام شود. این قابلیت برای تست بسیار مفید است. در ادامه مستندات مربوط به متدهای `Sms::fake()` آمده است:

```php
/**
 * تمام ارسال های پیامک برای ارائه دهنده پیش فرض موفق خروجی دهند
 */
Sms::fake();

/**
 * تمام ارسال های پیامک برای ارائه دهنده های لیست شده موفق خروجی دهند
 *
 * نکته: برای ارائه دهنده پیش فرض از کلید default استفاده کنید
 */
Sms::fake([/* provider keys */]);

/**
 * مشابه مورد بالا
 */
Sms::fake([...], Sms::successfulRequest());

/**
 * فیک کردن ارسال پیامک با پاسخ ناموفق. می‌توانید پیام خطا و کد خطا نیز مشخص کنید.
 */
Sms::fake([...], Sms::failedRequest('خطا', 500));

/**
 * شبیه‌سازی قطع ارتباط با ارائه‌دهنده (Connection Exception).
 */
Sms::fake([...], Sms::failedConnection());

/**
 * تعریف رفتار متفاوت برای هر ارائه‌دهنده. (رفتار اختصاصی)
 */
Sms::fake([
    'provider_one' => Sms::failedConnection(),
    'provider_two' => Sms::failedRequest(),
    'provider_three' => Sms::failedConnection(),
]);
```

**نکته:** تعریف رفتار سراسری و رفتار اختصاصی به‌طور هم‌زمان مجاز نیست.

**نکته:** اگر برای یک ارائه دهنده جند رفتار تعریف کنید، آخرین رفتار اجرا می شود.

[sms.ir]: https://sms.ir/
[melipayamak.com]: https://www.melipayamak.com/
[payam-resan.com]: https://payam-resan.com/
[kavenegar.com]: https://kavenegar.com/
[farazsms.com]: https://farazsms.com/
[raygansms.com]: https://raygansms.com/
[HTTP Client]: https://laravel.com/docs/12.x/http-client#throwing-exceptions
[queues]: https://laravel.com/docs/12.x/queues
[notifications]: https://laravel.com/docs/12.x/notifications
[Laravel's task scheduler]: https://laravel.com/docs/12.x/scheduling#scheduling-artisan-commands
