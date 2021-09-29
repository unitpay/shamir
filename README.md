# Shamir

[![Latest Stable Version](https://poser.pugx.org/unitpay/shamir/v/stable.png)](https://packagist.org/packages/unitpay/shamir)
[![Total Downloads](https://poser.pugx.org/unitpay/shamir/downloads.png)](https://packagist.org/packages/unitpay/shamir)
[![Build status](https://github.com/unitpay/shamir/workflows/build/badge.svg)](https://github.com/unitpay/shamir/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/unitpay/shamir/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/unitpay/shamir/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/unitpay/shamir/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/unitpay/shamir/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Funitpay%2Fshamir%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/unitpay/shamir/master)
[![static analysis](https://github.com/unitpay/shamir/workflows/static%20analysis/badge.svg)](https://github.com/unitpay/shamir/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/unitpay/shamir/coverage.svg)](https://shepherd.dev/github/unitpay/shamir)

PHP [Shamir's Secret Sharing](https://en.wikipedia.org/wiki/Shamir%27s_Secret_Sharing) implementation.
Inspired by hashicorp vault shamir. Compatible with [Simple Shamir's Secret Sharing (s4)](https://github.com/simonfrey/s4).

## Requirements

- PHP 7.4 or higher.

## Installation

The package could be installed with composer:

```shell
composer require unitpay/shamir --prefer-dist
```

## Usage
Split secret to parts with threshold 
```php
$secret = 'Some super secret';
$parts = 5;
$threshold = 3;
$shares = Shamir::split($secret, $parts, $threshold);
```

Reconstruct shares
```php
$recoveredSecret = Shamir::reconstruct([$parts[1], $parts[0], $parts[3]]);
```

## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework with
[Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

## License

It is released under the terms of the MIT License.
Please see [`LICENSE`](./LICENSE.md) for more information.

## Follow updates

[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/unitpay)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/unitpay_talk)
