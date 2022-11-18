# Parsisolution Gateway

Iranian Payment Gateways

This library is inspired by laravel [Socialite](https://github.com/laravel/socialite) and [PoolPort](https://github.com/PoolPort/PoolPort) and [larabook/gateway](https://github.com/larabook/gateway)

Available PSPs (Bank):
1. [Beh Pardakht](https://behpardakht.com) (`MELLAT`)
2. [SEP](https://sep.ir) (`SAMAN`)
3. [SADAD](https://sadadpsp.ir) (`MELLI`)
4. [PEC](https://pec.ir) (`PARSIAN`)
5. [PEP](https://pep.co.ir) (`PASARGAD`)
6. [Novin Pardakht](https://pna.co.ir) (`EN Bank`, also known as `Eghtesad Novin Bank`)
7. [IranKish](https://irankish.com)
8. [Sepehr](https://sepehrpay.com)
9. [Asan Pardakht](https://asanpardakht.ir)
10. [Fanava Card](https://fanavacard.ir)

Available 3rd-parties:
1. [Vandar](https://vandar.io)
2. [Pay.ir](https://pay.ir)
3. [ZarinPal](https://zarinpal.com)
4. [Zibal](https://zibal.ir)
5. [JibIt](https://jibit.ir)
6. [PayPing](https://payping.ir)
7. [IDPay](https://idpay.ir)
8. [Jibimo](https://jibimo.com)
9. [NextPay](https://nextpay.org)
10. [DigiPay](https://mydigipay.com)
11. [SizPay](https://sizpay.ir)
12. [Shepa](https://shepa.com)
13. [AqayePardakht](https://aqayepardakht.ir)
14. [IranDargah](https://irandargah.com)
15. [Bahamta](https://bahamta.com)
16. [ParsPal](https://parspal.com)
17. [BitPay](https://bitpay.ir)
18. [Milyoona](https://milyoona.com)
19. [Sepal](https://sepal.ir)
20. [TiPoul](https://tipoul.com)
21. [SabaPay](https://sabanovin.com)
22. [YekPay](https://yekpay.com)

## Install

### Step 1:

``` bash
composer require parsisolution/gateway
```

### Step 2:

``` bash
php artisan vendor:publish --provider="Parsisolution\Gateway\GatewayServiceProvider"
```

### Step 3:

``` bash
php artisan migrate
```

### Step 4:

Change `.env` values or `config/gateways.php` fields to your specifications.

## Usage

### Step 1:

Get instance of Gateway from Gateway Facade `Gateway::of('mellat')`
Or create one yourself: `new Mellat(app(), config('gateways.mellat'));` Or
```php
$gateway = Gateway::of('mellat');

$gateway = new Mellat(app(), config('gateways.mellat'));

$gateway = new Mellat(app(), [
    'username'    => '',
    'password'    => '',
    'terminal-id' => '',
]);
```

### Step2:
Then to create new payment transaction you can do like this:

```php
try {
    $gateway = Gateway::of('PayIR'); // $gateway = new Payir(app(), config('gateways.payir')); 
    $gateway->callbackUrl(route('callback')); // You can change the callback
    
    // You can make it stateless.
    // in default mode it uses session to store and retrieve transaction id 
    // (and other gateway specific or user provided (using $gateway->with) required parameters)
    // but in stateless mode it gets transaction id and other required parameters from callback url
    // Caution: you should use same stateless value in callback too
    $gateway->stateless();
    
    // You can get supported extra fields sample for each gateway and then set these fields with your desired values
    // (most gateways support `mobile` field)
    $supportedExtraFieldsSample = $gateway->getSupportedExtraFieldsSample();
    
    return compact('supportedExtraFieldsSample');

    // Then you should create a transaction to be processed by the gateway
    // Amount is in `Toman` by default, but you can set the currency in second argument as well. IRR (for `Riyal`)
    $transaction = new RequestTransaction(new Amount(12000)); // 12000 Toman
    $transaction->setExtra([
        'mobile' => '09124441122',
        'email'  => 'ali@gmail.com',
        'person' => 12345,
    ]);
    $transaction->setExtraField('description', 'توضیحات من');
    
    // if you added additional fields in your migration you can assign a value to it in the beginning like this
    $transaction['person_id'] = auth()->user()->id;
    
    $authorizedTransaction = $gateway->authorize($transaction);

    $transactionId = $authorizedTransaction->getId(); // شماره‌ی تراکنش در جدول پایگاه‌داده
    $orderId = $authorizedTransaction->getOrderId(); // شماره‌ی تراکنش اعلامی به درگاه
    $referenceId = $authorizedTransaction->getReferenceId(); // شناسه‌ی تراکنش در درگاه (در صورت وجود)
    $token = $authorizedTransaction->getToken(); // توکن درگاه (در صورت وجود)

    // در اینجا
    // شماره تراکنش(ها) را با توجه به نوع ساختار پایگاه‌داده‌ی خود 
    // در جداول مورد نیاز و بسته به نیاز سیستم‌تان ذخیره کنید.

    // this object tells us how to redirect to gateway
    $redirectResponse = $authorizedTransaction->getRedirect();

    // if you're developing an Api just return it and handle redirect in your frontend
    // (this gives you redirect method [get or post], url and fields)
    // (you can use a code like `redirector.blade.php`)
    return $redirectResponse;

    // otherwise use this solution to redirect user to gateway with proper response
    return $redirectResponse->redirect(app());

} catch (\Exception $e) {

    echo $e->getMessage();
}
```

### Step3:
And in callback

```php

$all = $request->all();

try {

    // first argument defines stateless/stateful state (true for stateless / default is false (stateful))
    // if you want to update fields that you added in migration on successful transaction
    // you can pass them in second argument as associative array
    $settledTransaction = Gateway::settle(true, ['person_id' => 333, 'invoice_id' => 5233]);

    $id = $settledTransaction->getId();
    $orderId = $settledTransaction->getOrderId();
    $amount = strval($settledTransaction->getAmount());
    $extra = $settledTransaction->getExtra();
    $person = $settledTransaction->getExtraField('person');
    $referenceId = $settledTransaction->getReferenceId();
    $traceNumber = $settledTransaction->getTraceNumber();
    $cardNumber = $settledTransaction->getCardNumber();
    $RRN = $settledTransaction->getRRN();
    $person_id = $settledTransaction['person_id'];
    $attributes = $settledTransaction->getAttributes();
    
    // تراکنش با موفقیت سمت درگاه تایید گردید
    // در این مرحله عملیات خرید کاربر را تکمیل میکنیم

    return compact('all', 'id', 'orderId', 'amount', 'extra', 'person', 'referenceId',
        'traceNumber', 'cardNumber', 'RRN', 'person_id', 'attributes');
        
} catch (\Parsisolution\Gateway\Exceptions\GeneralTransactionException $e) {
    $code = $e->getCode();
    $message = $e->getMessage();
    /** @var AuthorizedTransaction $transaction */
    $transaction = $e->getTransaction();
    $attributes = $transaction->getAttributes();
    $throwable = $e->getPrevious();
    $previous_class = get_class($throwable);
    $previous_trace = $throwable->getTrace();

    // تراکنش با خطا مواجه شده است

    return compact('previous_class', 'code', 'message', 'attributes', 'previous_trace');
    
} catch (\Parsisolution\Gateway\Exceptions\RetryException $e) {
    $class = get_class($e);
    $code = $e->getCode();
    $message = $e->getMessage();
    /** @var AuthorizedTransaction $transaction */
    $transaction = $e->getTransaction();
    $attributes = $transaction->getAttributes();
    $trace = $e->getTrace();
    
    // تراکنش قبلا سمت درگاه تاییده شده است و
    // کاربر احتمالا صفحه را مجددا رفرش کرده است
    // لذا تنها فاکتور خرید قبل را مجدد به کاربر نمایش میدهیم

    return compact('class', 'code', 'message', 'attributes', 'trace');
    
} catch (\Parsisolution\Gateway\Exceptions\TransactionException|\Parsisolution\Gateway\Exceptions\InvalidRequestException $e) {
    $class = get_class($e);
    $code = $e->getCode();
    $message = $e->getMessage();
    /** @var AuthorizedTransaction $transaction */
    $transaction = $e->getTransaction();
    $attributes = $transaction->getAttributes();
    $trace = $e->getTrace();

    // تراکنش با خطا مواجه شده است

    return compact('class', 'code', 'message', 'attributes', 'trace');
    
} catch (\Exception $e) {
    $class = get_class($e);
    $code = $e->getCode();
    $message = $e->getMessage();
    $trace = $e->getTrace();

    // تراکنش با خطا مواجه شده است

    return compact('class', 'code', 'message', 'trace');
}
```

### Appendix 1:

You can easily add your own gateway with no effort in your own code base by extending
`\Parsisolution\Gateway\AbstractProvider` class
and then add snippet code below to your controller's constructor 
```php
public function __construct()
{
    $createIDPay = function () {
        return new IDPay(app(), config('gateways.idpay2'));
    };
    Gateway::extend('idpay2', $createIDPay);
    // below number (60) should match the return value of gateway's `getProviderId` method
    Gateway::extend(60, $createIDPay);
}
```
after that you can use added gateway in controller's methods like other gateways:
```php
$gateway = Gateway::of('idpay2');
```
