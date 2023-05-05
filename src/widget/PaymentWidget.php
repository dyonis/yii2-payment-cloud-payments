<?php

namespace dyonis\yii2\payment\providers\cloudPayments\widget;

use dyonis\yii2\payment\PaymentProviderInterface;
use dyonis\yii2\payment\providers\cloudPayments\CloudPaymentsProvider;
use Yii;
use yii\base\Widget;
use yii\helpers\ArrayHelper;

class PaymentWidget extends Widget
{
    /**
     * @var array JS widget options
     */
    public array $options = [];

    /**
     * @var CloudPaymentsProvider
     */
    private PaymentProviderInterface $provider;

    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->provider = Yii::$app->payment->getProvider(CloudPaymentsProvider::PROVIDER_NAME);
    }

    public function run(): string
    {
        parent::run();

        $this->registerAssets();
        $this->initJsOptions();
        $this->initJS();

        return $this->render('widget', [
            'widgetOptions' => $this->options,
        ]);

    }

    private function registerAssets()
    {
        $this->view->registerAssetBundle(CloudPaymentsAsset::class);
    }

    private function initJsOptions()
    {
        $default = [
            'publicId' => $this->provider->publicId,
            'invoiceId' => 'CP_invoice_'.microtime(true),
        ];

        $this->options = ArrayHelper::merge($default, $this->options);
    }

    private function initJS()
    {
        $this->view->registerJs('CloudPaymentsPay();');
    }
}
