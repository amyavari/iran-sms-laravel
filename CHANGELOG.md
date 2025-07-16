# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

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

[1.0.0]: https://github.com/amyavari/iran-sms-laravel/compare/v0.1.0...v1.0.0
[1.1.0]: https://github.com/amyavari/iran-sms-laravel/compare/v1.0.0...v1.1.0
