# Parsisolution Gateway

Iranian Payment Gateways

This library is inspired by laravel [Socialite](https://github.com/laravel/socialite) and [PoolPort](https://github.com/PoolPort/PoolPort) and [larabook/gateway](https://github.com/larabook/gateway) and [ShirazSoft/Gateway](https://github.com/ShirazSoft/Gateway)

Available PSPs (Bank):
1. Beh Pardakht (MELLAT)
2. SADAD (MELLI)
3. SEP (SAMAN)
4. PEC (PARSIAN)
5. Mabna **V1** `old method`
6. Mabna **V2** `new method`
7. Asan Pardakht
8. IranKish

Available 3rd-parties:
1. Pay.ir
2. ZarinPal
3. JibIt
4. NextPay
5. SizPay
6. SabaPay (Saba Novin)
7. Pardano

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

Change `config/gateways.php` fields to your specifications.

## Usage

### Step 1:

Get instance of Gateway from Gateway Facade `Gateway::of('mellat')`
Or create one yourself: `new Mellat(app(), config('gateways.mellat'));` Or
```php
new Mellat(app(), [
    'username'     => '',
    'password'     => '',
    'terminalId'   => 0000000,
    'callback-url' => '/'
]);
```

### Step2:
Then to create new payment transaction you can do like this:

``` php
try {
    $gateway = Gateway::of('zarinpal'); // $gateway = new Zarinpal(app(), config('gateways.zarinpal')); 
    $gateway->callbackUrl(route('callback')); // You can change the callback
    
    // You can make it stateless.
    // in default mode it uses session to store and retrieve transaction id 
    // (and other gateway specific or user provided (using $gateway->with) required parameters)
    // but in stateless mode it get transaction id and other required parameters from callback url
    // Caution: you should use same stateless value in callback too
    $gateway->stateless();

    // Then you should create a transaction to be processed by the gateway
    // Amount is in `Toman` by default but you can set the currency in second argument as well. IRR (for `Riyal`)
    $transaction = new RequestTransaction(new Amount(12000)); // 12000 Toman
    $transaction->setExtra([
        'mobile' => '9122628796', // mobile of payer (for zarinpal)
        'email'  => 'ali@gmail.com', // email of payer (for zarinpal)
    ]);
    $transaction->setExtraField('description', 'توضیحات من');
    $authorizedTransaction = $gateway->authorize($transaction);

    $refId =  $authorizedTransaction->getReferenceId(); // شماره ارجاع بانک
    $transID = $authorizedTransaction->getId(); // شماره تراکنش

    // در اینجا
    //  شماره تراکنش  بانک را با توجه به نوع ساختار دیتابیس تان 
    //  در جداول مورد نیاز و بسته به نیاز سیستم تان
    // ذخیره کنید .

    return $gateway->redirect($authorizedTransaction);

} catch (\Exception $e) {

    echo $e->getMessage();
}
```

### Step3:
And in callback

```php
try {

    $settledTransaction = Gateway::settle(true); // true argument for stateless
    $trackingCode = $settledTransaction->getTrackingCode();
    $refId = $settledTransaction->getReferenceId();
    $cardNumber = $settledTransaction->getCardNumber();

    // تراکنش با موفقیت سمت بانک تایید گردید
    // در این مرحله عملیات خرید کاربر را تکمیل میکنیم

} catch (\Parsisolution\Gateway\Exceptions\RetryException $e) {

    // تراکنش قبلا سمت بانک تاییده شده است و
    // کاربر احتمالا صفحه را مجددا رفرش کرده است
    // لذا تنها فاکتور خرید قبل را مجدد به کاربر نمایش میدهیم

    echo $e->getMessage() . "<br>";

} catch (\Exception $e) {

    // نمایش خطای بانک
    echo $e->getMessage();
}
```
