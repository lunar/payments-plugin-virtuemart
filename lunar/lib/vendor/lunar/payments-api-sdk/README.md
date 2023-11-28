# Lunar payments client (PHP)

php wrapper for the lunar payments API

## Requirements

PHP 7.1 and later.

## Install

You can install the package via [Composer](http://getcomposer.org/). Run the following command:

```bash
composer require lunar/payments-api-sdk
```

## Api documentation
To find the api arguments you can go to URL HERE 

## Dependencies

The bindings require the following extension in order to work properly:

- [`curl`](https://secure.php.net/manual/en/book.curl.php)

If you use Composer, these dependencies should be handled automatically. If you install manually, you'll want to make sure that these extensions are available.  
If you don't want to use curl, you can create your own client to extend from `HttpClientInterface` and send that as a parameter when instantiating the `Lunar` class.

## Examples

```php
$lunar = new \Lunar\Lunar($private_secret_key);
 
$payment_intent_id = $lunar->payments()->create( $args ); 
 
// fetch a payment intent
$result = $lunar->payments()->fetch($payment_intent_id);
 
// capture a transaction
$payments = $lunar->payments();
$transaction  = $payments->capture($transaction_id, [
    'amount'   => [
        'decimal'=> '10',
        'currency' => 'EUR'
    ]
]);


// void a transaction
$payments = $lunar->payments();
$transaction  = $payments->cancel($transaction_id, [
    'amount'   => [
        'decimal'=> '10',
        'currency' => 'EUR'
    ]
]);


// refund a transaction
$payments = $lunar->payments();
$transaction  = $payments->refund($transaction_id, [
    'amount'   => [
        'decimal'=> '10',
        'currency' => 'EUR'
    ]
]);

``` 

## Error handling

The api wrapper will throw errors when things do not fly. All errors inherit from
`ApiException`. A very verbose example of catching all types of errors:

```php
$lunar = new \Lunar\Lunar($private_secret_key);
try {
    $payments = $lunar->transactions();
    $payments->capture($transaction_id, [
          'amount'   => [
            'decimal'=> '10',
            'currency' => 'EUR'
        ]
    ]);
} catch (\Lunar\Exception\NotFound $e) {
    // The transaction was not found
} catch (\Lunar\Exception\InvalidRequest $e) {
    // Bad (invalid) request - see $e->getJsonBody() for the error
} catch (\Lunar\Exception\Forbidden $e) {
    // You are correctly authenticated but do not have access.
} catch (\Lunar\Exception\Unauthorized $e) {
    // You need to provide credentials (an app's API key)
} catch (\Lunar\Exception\Conflict $e) {
    // Everything you submitted was fine at the time of validation, but something changed in the meantime and came into conflict with this (e.g. double-capture).
} catch (\Lunar\Exception\ApiConnection $e) {
    // Network error on connecting via cURL
} catch (\Lunar\Exception\ApiException $e) {
    // Unknown api error
}
``` 

In most cases catching `NotFound` and `InvalidRequest` as client errors
and logging `ApiException` would suffice.

## Development

Install dependencies:

``` bash
composer install
```

## Tests

Install dependencies as mentioned above (which will resolve [PHPUnit](http://packagist.org/packages/phpunit/phpunit)), then you can run the test suite:

```bash
./vendor/bin/phpunit
```
