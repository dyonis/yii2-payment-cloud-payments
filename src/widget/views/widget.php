<?php
/** @var array $widgetOptions */

use dyonis\yii2\payment\providers\cloudPayments\CloudPaymentsProvider;

?>
<script>
    function CloudPaymentsPay() {
        const widget = new cp.CloudPayments();
        const options = <?= json_encode($widgetOptions, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) ?>;

        widget.pay('charge', options, { // auth|charge
                onSuccess: function (options) {
                    triggerCpEvent('onPaymentSuccess', {
                        provider: '<?= CloudPaymentsProvider::PROVIDER_NAME ?>',
                        options
                    });
                },
                onFail: function (reason, options) {
                    triggerCpEvent('onPaymentFail', {
                        provider: '<?= CloudPaymentsProvider::PROVIDER_NAME ?>',
                        reason,
                        options
                    });
                },
                onComplete: function (paymentResult, options) {
                    triggerCpEvent('onPaymentComplete', {
                        provider: '<?= CloudPaymentsProvider::PROVIDER_NAME ?>',
                        paymentResult,
                        options
                    });
                }
            }
        )
    }

    function triggerCpEvent(name, data)
    {
        //console.log('CloudPayments Event', name, data);

        const event = new CustomEvent(name, {detail: data});
        document.dispatchEvent(event);
    }
</script>
