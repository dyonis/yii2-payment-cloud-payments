# CloudPayments provider for `dyonis/yii2-payment` package

## Configuration
Add to `config/main.php`
```php
'components' => [
...
    'payment' => [
        'class' => dyonis\yii2\payment\PaymentComponent::class,
        'providers' => [
            'cloudPayments' => [
                'class' => dyonis\yii2\payment\providers\cloudPayments\CloudPaymentsProvider::class,
                'responseUrl' => 'https://your-site.com/payment/cloudPayments/process',
                'publicId' => 'Your public ID key',
                'apiKey' => 'Your secret apiKey',
                'on payment-success' => [my\response\Processor::class, 'processCloudPaymentsEvent'],
                'on payment-fail' => [my\response\Processor::class, 'processCloudPaymentsEvent'],
            ],
            // ... Other payment systems
        ],
    ],
],
```

JavaScript Events:
* onPaymentComplete
* onPaymentFail
* onPaymentSuccess

```javascript
document.addEventListener('onPaymentSuccess', e => {
        // Success payment
    },
    false
)
```
