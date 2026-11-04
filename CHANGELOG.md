# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [2.0.0] - 2026-11-04

### Added

- The `SmsLog` model is now public, so you can query your SMS logs directly.

### Removed

- Support for PHP `8.2`
- Support for Laravel `11` due to security advisories.

### Changed

- Increased minimum Laravel versions to `12.69` and `13.30` due to security advisories.
- **Breaking:** `UnsupportedMultiplePhonesException` has a new message: `Provider "%s" only supports sending pattern SMS to one phone number at a time.`

### Fixed

- A faked provider now returns a new SMS instance on each call, so it can send more than one SMS.
- The `iran-sms:prune-logs` command now fails on an invalid `--days` value, instead of deleting the logs.
- A phone array with gaps in its keys no longer causes an `Undefined array key` error or a wrong request body.
- **Mediana** text messages now use the documented `webservice` sending type.
- **Faraz SMS** `credit()` now throws on a client error, such as a wrong API key, instead of returning `0`.
- `Sms::failedConnection()` no longer records an extra HTTP request, and it now makes `credit()` throw too.
- **Ghasedak** pattern messages no longer throw a `TypeError` when a variable is `null`.
- **Payam Resan** and **Behin Payam** now report a provider error returned with HTTP 200 as a failed SMS, instead of a successful one.
- A missing field in a provider's response now gives a failed SMS, instead of a `TypeError`.
- Typos in the Persian error messages of **SMS.ir**, **Web One SMS**, **Asanak** and **Fara Payamak**.

## [1.5.0] - 2026-05-13

### Added

- **Mediana** provider.

### Changed

- **FarazaSMS** implementation to match its new APIs.

## [1.4.0] - 2026-04-10

### Added

- Support for Laravel `13`

## [1.3.0] - 2025-11-19

### Removed

- `composer.lock`.

### Changed

- Increased minimum Laravel version support due to security advisories.

## [1.2.0] - 2025-10-13

### Added

- **Fara Payamak** provider.
- **Ghasedak** provider.
- **Behin Payam** provider.
- **Asanak** provider.
- `credit()` method in all providers to retrieve the current credit balance.

## [1.1.1] - 2025-08-12

### Fixed

- Ensure a new immutable SMS instance is created for each message.

## [1.1.0] - 2025-07-16

### Added

- **Web One SMS** provider.
- **Amoot SMS** provider.

### Changed

- Increased minimum Laravel 11 version requirement from `11.32.0` to `11.35.1`

## [1.0.0] - 2025-07-08

First stable release of the package, features:

- **Multi-provider support**: Seamlessly switch between SMS provider
- **Logging system**: Monitor all SMS transactions for troubleshooting
- **Prune logs command**: Manage log storage efficiently
- **Notifications channel**: Native Laravel notifications support
- **Faking/Testing utilities**: Simplified testing for SMS functionality

Supported Providers:

- SMS.ir
- Meli Payamak
- Payam Resan
- Kavenegar
- Faraz SMS
- Raygan SMS

[Unreleased]: https://github.com/amyavari/iran-sms-laravel/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/amyavari/iran-sms-laravel/compare/v1.5.0...v2.0.0
[1.5.0]: https://github.com/amyavari/iran-sms-laravel/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/amyavari/iran-sms-laravel/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/amyavari/iran-sms-laravel/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/amyavari/iran-sms-laravel/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/amyavari/iran-sms-laravel/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/amyavari/iran-sms-laravel/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/amyavari/iran-sms-laravel/compare/v0.1.0...v1.0.0
