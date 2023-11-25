# Shaparak :: Laravel Online Payment Component
Online Payment Component for Laravel 5+ known as Shaparak component completely compatible with [BankTest](http://banktest.ir) simulator.
Shaparak integrated all Iranian/Shetab payment gateways to one component.

## What is Banktest?
- [BankTest](http://banktest.ir) is a sandbox service for all Iranian online payment gateways
- [بانک تست](http://banktest.ir) یک سرویس شبیه ساز درگاه های پرداخت آنلاین ایرانی برای اهداف توسعه و تست نرم افزار می باشد

## Support This Project

Please support the package by giving it :star: and contributing to its development.

## Currently supported PSPs:

- Mellat Bank Gateway - درگاه بانک ملت (به پرداخت ملت) لاراول
- Saman Bank Gateway - درگاه بانک سامان (پرداخت الکترونیک سامان) لاراول
- Saderat Bank Gateway - درگاه بانک صادرات (پرداخت الکترونیک سپهر) لاراول
- Pasargad Bank Gateway - درگاه بانک پاسارگاد (پرداخت الکترونیک پاسارگاد) لاراول
- Parsian Bank Gateway - درگاه بانک پارسیان (تجارت الکترونیک پارسیان) لاراول
- Melli Bank Gateway - درگاه بانک ملی (سداد) لاراول
- ...

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
### Add required fields to the model migration
```php
$table->string('token', 40)->nullable(); // It keeps token that we get from the IPG
$table->jsonb('gateway_callback_params')->nullable(); // It keeps the IPG callback parameters (just for tracking and debugging)

$table->boolean('verified')->default(false); // Transaction verified or not
$table->boolean('after_verified')->default(false); // Transaction settled or not
$table->boolean('reversed')->default(false); // Transaction revered/refunded or not
$table->boolean('accomplished')->default(false); // Transaction accomplished or not
```
### Prepare required model(s)
Your Transaction, Invoice or Order model MUST implement Shaparak Transaction Interface. 
```php
<?php

namespace App\Models;

use App\Traits\JsonbField;
use App\Traits\ShaparakIntegration;
use Illuminate\Database\Eloquent\Model;
use PhpMonsters\Shaparak\Contracts\Transaction as ShaparakTransaction;

class Transaction extends Model implements TransactionTransaction
{
```

### Shaparak Integration Trait
You can use a trait as below in order integrate your model with Shaparak and implement Shaparak's Transaction contract.

**Sample integration trait (THIS IS JUST AN EXAMPLE)**: 

```php
trait ShaparakIntegration
{
    /**
     * return callback url for payment process
     */
    public function getCallbackUrl(): string
    {
        return makeHttpsUrl(route(
            'ipg.transaction_callback',
            [
                'token' => $this->token,
            ]
        ));
    }

    /**
     * set gateway token that fetched from ipg provider gateway
     */
    public function setGatewayToken(string $token, bool $save = true): bool
    {
        $this->token = $token;
        $this->status = (TransactionStatusEnum::GoneToGate)->value;

        if ($save) {
            return $this->save();
        }

        return true;
    }

    public function getGatewayToken(): string
    {
        return $this->token;
    }

    /**
     * check if you transaction is ready for requesting payment token
     */
    public function isReadyForTokenRequest(): bool
    {
        return intval($this->status) <= (TransactionStatusEnum::Callback)->value;
    }

    /**
     * check if transaction is ready for requesting verify transaction
     */
    public function isReadyForVerify(): bool
    {
        return intval($this->status) <= (TransactionStatusEnum::Verified)->value;
    }

    /**
     * check if transaction is ready for requesting inquiry transaction (if supported by gateway)
     */
    public function isReadyForInquiry(): bool
    {
        return intval($this->status) >= (TransactionStatusEnum::GoneToGate)->value;
    }

    /**
     * check if transaction is ready for requesting settle/... transaction (if needed by gateway)
     */
    public function isReadyForSettle(): bool
    {
        return intval($this->status) == (TransactionStatusEnum::Verified)->value;
    }

    /**
     * check if transaction is ready to mark as  accomplished
     */
    public function isReadyForAccomplish(): bool
    {
        return (intval($this->status) >= (TransactionStatusEnum::Verified)->value) &&
            (intval($this->status) < (TransactionStatusEnum::Accomplished)->value);
    }

    public function ipgProviderSupportsRefund(): bool
    {
        return ! empty($this->providable) &&
            $this->providable->refund_support === true;
    }

    public function isReadyForReverse(): bool
    {
        return $this->status === TransactionStatusEnum::Callback->value;
    }

    public function isReadyForCancel(): bool
    {
        return $this->status === TransactionStatusEnum::Verified->value;
    }

    /**
     * check if transaction is ready for accomplishment (merchant verify)
     */
    public function isReadyForRefund(): bool
    {
        return
            $this->ipgProviderSupportsRefund() &&
            (int) $this->status !== (TransactionStatusEnum::Accomplished)->value;
    }

    /**
     * update transaction by paid card number (if provided by gateway)
     */
    public function setCardNumber(string $cardNumber, bool $save = true): bool
    {
        $this->cardNumber = $cardNumber;

        if ($save) {
            return $this->save();
        }

        return true;
    }

    /**
     * mark transaction as verified
     */
    public function setVerified(bool $save = true): bool
    {
        $this->status = (TransactionStatusEnum::Verified)->value;

        if ($save) {
            return $this->save();
        }

        return true;
    }

    /**
     * mark transaction as settled/...
     */
    public function setSettled(bool $save = true): bool
    {
        $this->status = (TransactionStatusEnum::Settled)->value;

        if ($save) {
            return $this->save();
        }

        return true;
    }

    /**
     * mark transaction as reversed
     */
    public function setRefunded(bool $save = true): bool
    {
        $this->status = (TransactionStatusEnum::Refund)->value;

        if ($save) {
            return $this->save();
        }

        return true;
    }

    /**
     * get transaction amount
     */
    public function getPayableAmount(): int
    {
        return $this->payable_amount;
    }

    /**
     * save ipg provider's gateway callback parameters into transaction
     */
    public function setCallBackParameters(array $parameters, bool $save = true): bool
    {
        $this->gateway_callback_params = $parameters;

        if ($save) {
            return $this->save();
        }

        return true;
    }

    /**
     * @return false|mixed|string
     */
    public function getCallbackParams(): mixed
    {
        return $this->gateway_callback_params;
    }
}
```

### Initialize a Shaparak instance
```php
// method I: init Shaparak by passing custom gateway options
$gatewayProperties = [
    'terminal_id' => 'X1A3B5CY-X1DT4Z',
    'terminal_pass' => '12345',
];
$shaparak = Shaparak::with($psp, $transaction, $gatewayProperties)
    ->setParameters($request->all());
    
// method II: init shaparak by config based gateway options
// if you don't pass the third item it will use gateway's options from `config/shaparak.php` config file
$shaparak = Shaparak::with($psp, $transaction)
    ->setParameters($request->all());
```

### Create goto IPG form (Payment form)
Create a form in order to go to payment gateway. This form is auto-submit by default
```php
// create your transaction as you desired
$transaction = new Transaction();
$transaction->user_id = $user->id;
// ...
$transaction->ip_address = $request->getClientIp();
$transaction->description = $request->input('description');
$transaction->save();

try {
    $form = Shaparak::with('saman', $transaction)->getForm();
} catch (\Exception $e) {
    XLog::exception($e);
    flash(trans('gate.could_not_create_goto_bank_form'))->error();
    return redirect()->back();
}
```
Show the form in your blade like:
```php
{!! $form !!}
```

### Callback URL/route on your application
In your callback action create a Shaparak instance and handle the transaction 
```php
$shaparak = Shaparak::with('saman', $order)
    ->setParameters($request->all());
    
if ($shaparak->canContinueWithCallbackParameters($request->all()) !== true) {
    flash(trans('gate.could_not_continue_because_of_callback_params'))->error();
    // do what you need
}

$shaparak->setCallBackParameters($request->all());

$verifyResult = $shaparak->verifyTransaction($request->all());

```

### Next steps (optional steps)
Use the following methods based on your needs

```php
$inquiryResult = $shaparak->inquiryTransaction($request->all());
$settleResult = $shaparak->settleTransaction($request->all());
$refundResult = $shaparak->refundTransaction($request->all());
```

## Security

If you discover any security related issues, please email aboozar.ghf@gmail.com instead of using the issue tracker.

## Team

This component is developed by the following person(s) and a bunch of [awesome contributors](https://github.com/iamtartan/laravel-online-payment/graphs/contributors).

[![Aboozar Ghaffari](https://avatars2.githubusercontent.com/u/502961?v=3&s=130)](https://github.com/iamtartan) |  [![Maryam Nabiyan](https://avatars.githubusercontent.com/u/47553919?s=120&v=4)](https://github.com/maryamnbyn)
--- | --- |
[Aboozar Ghaffari](https://github.com/iamtartan) | [Maryam Nabiyan](https://github.com/maryamnbyn)


## License

The Laravel Online Payment Module is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)