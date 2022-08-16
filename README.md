# Shaparak :: Laravel Online Payment Component
Online Payment Component for Laravel 5+ known as Shaparak component completely compatible with [BankTest](http://banktest.ir) simulator.
Shaparak integrated all Iranian/Shetab payment gateways to one component.

## What is Banktest?
- [BankTest](http://banktest.ir) is a sandbox service for all Iranian online payment gateways
- [بانک تست](http://banktest.ir) یک سرویس شبیه ساز درگاه های پرداخت آنلاین ایرانی برای اهداف توسعه و تست نرم افزار می باشد


## Currently supported PSPs:

- Mellat Bank Gateway - درگاه بانک ملت (به پرداخت ملت) لاراول
- Saman Bank Gateway - درگاه بانک سامان (پرداخت الکترونیک سامان) لاراول
- Saderat Bank Gateway - درگاه بانک صادرات (مبناکارت آریا) لاراول
- Pasargad Bank Gateway - درگاه بانک پاسارگاد (پرداخت الکترونیک پاسارگاد) لاراول
- Parsian Bank Gateway - درگاه بانک پارسیان (تجارت الکترونیک پارسیان) لاراول
- Melli Bank Gateway - درگاه بانک ملی (سداد) لاراول
- ...
- Other gateways, coming soon... لطفا شما هم در تکمیل پکیج مشارکت کنید

## Requirements
Shaparak require PHP 7.1+

## Installation
1. Installation via php composer

```bash
composer require php-monsters/shaparak
```
2. Add package service provider to your app service providers:

```php
PhpMonsters\Shaparak\ShaparakServiceProvider::class,
```
3. Add package alias to your app aliases:

```php
'Shaparak' => PhpMonsters\Shaparak\Facades\Shaparak::class,
```
4. Publish package assets and configs

```bash
php artisan vendor:publish --provider="PhpMonsters\Shaparak\ShaparakServiceProvider"
```

## Configuration
If you complete installation step correctly, you can find Shaparak config file as `shaparak.php` in you project config file.

For using sandbox environment you should set ```SHAPARAK_MODE=development``` in your .env file otherwise set ```SHAPARAK_MODE=production```

if you choose development mode, Shaparak uses [banktest.ir](https://banktest.ir) as its payment gateway.


## Usage

### Prepare required model(s)

## Security

If you discover any security related issues, please email aboozar.ghf@gmail.com instead of using the issue tracker.

## Team

This component is developed by the following person(s) and a bunch of [awesome contributors](https://github.com/iamtartan/laravel-online-payment/graphs/contributors).

[![Aboozar Ghaffari](https://avatars2.githubusercontent.com/u/502961?v=3&s=130)](https://github.com/iamtartan) |  [![Maryam Nabiyan](https://avatars.githubusercontent.com/u/47553919?s=120&v=4)](https://github.com/maryamnbyn)
--- | --- |
[Aboozar Ghaffari](https://github.com/iamtartan) | [Maryam Nabiyan](https://github.com/maryamnbyn)

## Support This Project

Please give :star: star to the package and contribute in package development.

## License

The Laravel Online Payment Module is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)