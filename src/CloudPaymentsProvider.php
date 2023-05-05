<?php

namespace dyonis\yii2\payment\providers\cloudPayments;

use dyonis\yii2\payment\BasePaymentProvider;
use dyonis\yii2\payment\exceptions\PaymentException;
use dyonis\yii2\payment\PaymentLogger;
use dyonis\yii2\payment\response\BaseResponse;
use dyonis\yii2\payment\response\CheckResponse;
use dyonis\yii2\payment\response\FailResponse;
use dyonis\yii2\payment\response\SuccessResponse;
use yii\web\Request;
use yii\web\Response;

/**
 * https://developers.cloudpayments.ru
 */
final class CloudPaymentsProvider extends BasePaymentProvider
{
    const PROVIDER_NAME = 'CloudPayments';

    const STATUS_COMPLETED = 'Completed';
    const STATUS_AUTHORIZED = 'Authorized';
    const STATUS_CANCELED = 'Cancelled';
    const STATUS_DECLINED = 'Declined';

    public string $publicId = '';
    public string $apiKey = '';
    public string $name = self::PROVIDER_NAME;
    public array $allowedIPs = [];

    public bool $validateRequest = true;

    private PaymentLogger $logger;

    public function __construct($config = [])
    {
        parent::__construct($config);

        $this->logger = (new PaymentLogger())->setProvider($this);
    }

    public function init()
    {
        parent::init();

        if (!$this->publicId || !$this->apiKey) {
            throw new PaymentException('Parameters publicId and apiKey must be set');
        }
    }

    public function processCheckRequest(Request $request, Response $response): Response
    {
        try {
            $this->checkRequestValidity($request);
        } catch (\Exception $e) {
            $this->logger
                ->setType(PaymentLogger::TYPE_INFO)
                ->setMessage($e->getMessage())
                ->log();

            return $this->getUnsuccessfulResponse($response);
        }

        $this->logRequestData($request, 'check');
        $data = $request->post();

        $paymentResponse = new CheckResponse();
        $this->loadDataToResponseObject($paymentResponse, $data);

        try {
            $this->triggerPaymentCheck($paymentResponse);

            return $this->getSuccessResponse($response);
        }
        catch (\Exception $e) {
            $this->logger
                ->setType(PaymentLogger::TYPE_ERROR)
                ->setData($data)
                ->setMessage($e->getMessage())
                ->log();

            return $this->getUnsuccessfulResponse($response);
        }
    }

    public function processPaymentRequest(Request $request, Response $response): Response
    {
        try {
            $this->checkRequestValidity($request);
        } catch (\Exception $e) {
            return $this->getUnsuccessfulResponse($response);
        }

        $data = $request->post();
        $this->logRequestData($request, 'success');

        /**
         * 1. Обрабатываем ответ от платёжки https://developers.cloudpayments.ru/#pay
         */
        switch ($data['Status'] ?? null) {
            case self::STATUS_COMPLETED:
            case self::STATUS_AUTHORIZED:
                $paymentResponse = new SuccessResponse();
                $this->loadDataToResponseObject($paymentResponse, $data);

                // 2. Передаём данные транзакции в paymentSuccess
                try {
                    $this->triggerPaymentSuccess($paymentResponse);

                    return $this->getSuccessResponse($response);
                }
                catch (\Exception $e) {
                    return $this->getUnsuccessfulResponse($response);
                }
            case self::STATUS_CANCELED:
            case self::STATUS_DECLINED:
                $paymentResponse = new FailResponse();
                $this->loadDataToResponseObject($paymentResponse, $request->post());

                try {
                    $this->triggerPaymentFail($paymentResponse);

                    return $this->getSuccessResponse($response);
                }
                catch (\Exception $e) {
                    return $this->getUnsuccessfulResponse($response);
                }
            default:
                $this->logger
                    ->setType(PaymentLogger::TYPE_ERROR)
                    ->setMessage('Unknown payment status:'.$data['Status'] ?? null)
                    ->log();

                return $this->getUnsuccessfulResponse($response);
        }
    }

    public function processFailRequest(Request $request, Response $response): Response
    {
        try {
            $this->checkRequestValidity($request);
        } catch (\Exception $e) {
            return $this->getUnsuccessfulResponse($response);
        }

        $this->logRequestData($request, 'fail');

        $paymentResponse = new FailResponse();
        $this->loadDataToResponseObject($paymentResponse, $request->post());

        try {
            $this->triggerPaymentFail($paymentResponse);

            return $this->getSuccessResponse($response);
        }
        catch (\Exception $e) {
            return $this->getUnsuccessfulResponse($response);
        }
    }

    private function loadDataToResponseObject(BaseResponse $response, array $data)
    {
        $response->data = $data;
        $response->paySystemName = $this->name;
        $response->amount = $data['Amount'];
        $response->currency = $data['Currency'];
        $response->invoiceId = $data['InvoiceId'];
        $response->userId = (int)$data['AccountId'];
        $response->transactionId = $data['TransactionId'];
        $response->testMode = (bool)$data['TestMode'];

        if (isset($data['Data'])) {
            $response->payload = json_decode($data['Data'], true);
        }
    }

    /**
     * https://developers.cloudpayments.ru/#proverka-uvedomleniy
     * @url https://api.targemy.test/v1/payment/cloudPayments/check
     * @throws PaymentException
     */
    private function checkRequestValidity(Request $request)
    {
        if (!$request->getRawBody()) {
            throw new PaymentException('Empty request');
        }

        if ($this->validateRequest === false) {
            return;
        }

        if (!$request->post('TransactionId')) {
            throw new PaymentException("TransactionId not provided\n"
                .$request->getMethod()."\n"
                .$request->getRawBody()
            );
        }

        //X-Content-HMAC (url_decoded), Content-HMAC (url_encoded)
        $s1 = $request->headers['Content-HMAC'] ?? '';
        $s2 = base64_encode(hash_hmac('sha256', $request->getRawBody(), $this->apiKey, true));

        if ($s1 !== $s2) {
            $diff = "$s1 !== $s2\n";
            throw new PaymentException("Request verification error\n$diff");
        }
    }

    private function logRequestData(Request $request, string $type = null)
    {
        $data = [
            'type' => $type ?? 'null',
            'headers' => $request->headers->toOriginalArray(),
            'body' => $request->post(),
            //'rawBody' => $request->rawBody,// debug
        ];

        $this->logger
            ->setData($data)
            ->log();
    }

    /**
     * Send a response to the payment system
     * that the request has been successfully processed
     */
    protected function getSuccessResponse(Response $response): Response
    {
        $response->setStatusCode(200);
        $response->data = ['code' => 0];

        return $response;
    }

    /**
     * Send a response to the payment system
     * that the request was not processed correctly
     */
    protected function getUnsuccessfulResponse(Response $response): Response
    {
        $response->setStatusCode(503);
        $response->data = ['code' => 13];

        return $response;
    }
}
