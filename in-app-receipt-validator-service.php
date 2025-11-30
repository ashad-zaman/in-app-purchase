<?php

/**
 * Install dependencies for PHP 7.4.3 with:
 *
 * composer require aporat/store-receipt-validator:^3.1 google/apiclient:^2.0
 *
 * Add the required settings:
 *    Apple IAP
 *      define('INAPP_APPLE_TEST_MODE',     false);
 *      define('INAPP_APPLE_SHARED_SECRET', 'apple_shared_secret');
 *
 *    Google IAP
 *      define('INAPP_GOOGLE_SERVICE_ACCOUNT_JSON', __DIR__ . '/google_api_service_account.json');
 *
 * Examples of usage:
 *
 *    $validator = new InAppReceiptValidatorService();

 *    try {
 *        $appleResult = $validator->validateAppleReceipt($base64Receipt);
 *        print_r($appleResult);
 *    } catch (\Throwable $e) {
 *        echo 'Apple Receipt Error: ' . $e->getMessage();
 *    }
 *
 *    try {
 *        $googleResult = $validator->validateGoogleReceipt(
 *            'com.example.app',
 *            'monthly_subscription',
 *            'purchase_token_here'
 *        );
 *        print_r($googleResult);
 *    } catch (\Throwable $e) {
 *        echo 'Google Receipt Error: ' . $e->getMessage();
 *    }
 */

// declare(strict_types=1);

require_once __DIR__ .'/vendor/autoload.php'; 

use ReceiptValidator\iTunes\Validator     as ITunesValidator;
use ReceiptValidator\GooglePlay\Validator as GooglePlayValidator;
use Google\Client                         as Google_Client;
use Google\Service\AndroidPublisher       as Google_Service_AndroidPublisher;

/**
 * Interface for in-app purchase receipt validation service.
 */
interface In_App_Receipt_Validator_Service_Interface
{
    /**
     * Validates an Apple App Store in-app purchase receipt.
     *
     * @param  string $base64Receipt The base64-encoded receipt string.
     * @return array
     * @throws \RuntimeException If the receipt is invalid or validation fails.
     */
    public function validateAppleReceipt(string $base64Receipt): array;

    /**
     * Validates a Google Play Store in-app purchase receipt.
     *
     * @param  string $packageName    The package name of the app (e.g., com.example.app).
     * @param  string $productId      The product ID of the in-app item (e.g., monthly_subscription).
     * @param  string $purchaseToken  The purchase token returned by Google Play.
     * @return array
     * @throws \RuntimeException If the receipt is invalid or validation fails.
     */
    public function validateGoogleReceipt(
        string $packageName,
        string $productId,
        string $purchaseToken
    ): array;
}


/**
 * Service for validating in-app purchase receipts from Apple and Google.
 */

final class In_App_Receipt_Validator_Service  implements In_App_Receipt_Validator_Service_Interface
{
    private ITunesValidator     $appleValidator;
    private GooglePlayValidator $googleValidator;

    public function __construct()
    {
        // Apple configuration
        $appleTestMode     = defined('INAPP_APPLE_TEST_MODE')     ? INAPP_APPLE_TEST_MODE     : false;
        $appleSharedSecret = defined('INAPP_APPLE_SHARED_SECRET') ? INAPP_APPLE_SHARED_SECRET : null;

        $this->appleValidator = new ITunesValidator();
        $this->appleValidator->setEndpoint(
            $appleTestMode ? ITunesValidator::ENDPOINT_SANDBOX : ITunesValidator::ENDPOINT_PRODUCTION
        );

        if ($appleSharedSecret !== null) {
            $this->appleValidator->setSharedSecret($appleSharedSecret);
        }

        // Google configuration
        
        if(defined('INAPP_GOOGLE_SERVICE_ACCOUNT_JSON'))
        {
            // $googleKeyPath = INAPP_GOOGLE_SERVICE_ACCOUNT_JSON;
            $compression_service = new CompressionService();
 
            $compression_service_de = $compression_service->decompress( INAPP_GOOGLE_SERVICE_ACCOUNT_JSON);

            $tmpFile = tmpfile();
            fwrite($tmpFile, $compression_service_de);

                // Get the file path
            $meta = stream_get_meta_data($tmpFile);
            $tmpFilePath = $meta['uri'];
            $googleKeyPath = $tmpFilePath ;
 
        }else{
            throw new \RuntimeException('Google service account path not defined');
        }

        $googleClient = new Google_Client();
        $googleClient->setAuthConfig($googleKeyPath);
        $googleClient->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
        // fclose($temp_pointer);


        $googleService = new Google_Service_AndroidPublisher($googleClient);
        $this->googleValidator = new GooglePlayValidator($googleService);

        fclose($tmpFile);
    }

    /**
     * Validate Apple in-app receipt.
     *
     * @param  string $base64Receipt
     * @return array
     * @throws RuntimeException
     */
    public function validateAppleReceipt(string $base64Receipt): array
    {
       
         $response = $this->appleValidator
            ->setReceiptData($base64Receipt)
            ->validate();  
 

        if (!$response->isValid()) {
            throw new \RuntimeException(
                'Invalid Apple receipt. Code: ' . $response->getResultCode()
            );
        }

        return [
            'bundle_id'           => $response->getBundleId(),
            'receipt'             => $response->getReceipt(),
            'latest_receipt_info' => $response->getLatestReceiptInfo(),
            'latest_receipt'      => $response->getLatestReceipt(),
        ];
    }


/**
     * Retrieves purchase information for a specific product from an Apple receipt.
     *
     * @param  string $base64Receipt The base64-encoded receipt string.
     * @param  string $productId     The product ID to look for.
     * @return array
     * @throws \RuntimeException If the receipt is invalid or the product is not found.
     */
    public function getApplePurchases($base64Receipt, $productId): array
    {
        $response = $this->appleValidator
            ->setReceiptData($base64Receipt)
            ->validate();  
 

        if (!$response->isValid()) {
            throw new \RuntimeException(
                'Invalid Apple receipt. Code: ' . $response->getResultCode()
            );
        }

        // $productId = '';
        $transactionId = '';
        $purchaseDate = null;

         
        foreach ($response->getPurchases() as $purchase) { 
            if( $purchase->getProductId() == $productId) {

                $productId = $purchase->getProductId();
                $transactionId = $purchase->getTransactionId();
                if ($purchase->getPurchaseDate() != null) {
                        $purchaseDate = $purchase->getPurchaseDate();
                    }
            }
        }
        
        return [
            'productId'  => $productId,
            'transactionId' => $transactionId,
            'purchaseDate' => $purchaseDate,
        ];
    }

    /**
     * Validates a Google Play Store in-app purchase or subscription receipt.
     *
     * @param  string $packageName    The package name of the app.
     * @param  string $productId      The product or subscription ID.
     * @param  string $purchaseToken  The purchase token returned by Google Play.
     * @param  bool   $isSubscription True if it's a subscription.
     * @return array
     *
     * @throws \RuntimeException
     */
    public function validateGoogleReceipt(
        string $packageName,
        string $productId,
        string $purchaseToken,
        bool   $isSubscription = false
    ): array {
        $this->googleValidator
            ->setPackageName($packageName)
            ->setProductId($productId)
            ->setPurchaseToken($purchaseToken);

        return $isSubscription
            ? $this->validateGoogleSubscription()
            : $this->validateGoogleProduct();
    }

    /**
     * Summary of validateGoogleSubscription
     * @throws RuntimeException
     * @return array{autoRenewing: bool, cancelReason: int|null, expiryTimeMillis: int, kind: string, paymentState: int, priceAmountMicros: int, priceCurrencyCode: string, raw_response: Google_Service_AndroidPublisher_InAppProduct|Google_Service_AndroidPublisher_InAppProductListing|Google_Service_AndroidPublisher_ProductPurchase|Google_Service_AndroidPublisher_SubscriptionPurchase, startTimeMillis: string}
     */
    private function validateGoogleSubscription(): array
    {
        $response = $this->googleValidator->validateSubscription();
    
        $expiryTimeMillis  = (int) $response->getExpiryTimeMillis();
        $currentTimeMillis = (int) (microtime(true) * 1000);
    
        if ($expiryTimeMillis <= $currentTimeMillis) {
            $expiryDate = (new \DateTime())->setTimestamp((int) ($expiryTimeMillis  / 1000))->format('Y-m-d H:i:s');
            $nowDate    = (new \DateTime())->setTimestamp((int) ($currentTimeMillis / 1000))->format('Y-m-d H:i:s');
    
            throw new \RuntimeException(sprintf(
                'Subscription has expired. Expiry: %s, Now: %s',
                $expiryDate,
                $nowDate
            ));
        }
    
        return [
            'kind'               => $response->getKind(),
            'startTimeMillis'    => $response->getStartTimeMillis(),
            'expiryTimeMillis'   => $expiryTimeMillis,
            'autoRenewing'       => $response->getAutoRenewing(),
            'priceAmountMicros'  => $response->getPriceAmountMicros(),
            'priceCurrencyCode'  => $response->getPriceCurrencyCode(),
            'paymentState'       => $response->getPaymentState(),
            'cancelReason'       => $response->getCancelReason(),
            'raw_response'       => $response->getRawResponse()
        ];
    }

/**
     * Cancels a Google Play subscription.
     *
     * @param  string $packageName    The package name of the app.
     * @param  string $productId      The product or subscription ID.
     * @param  string $purchaseToken  The purchase token returned by Google Play.
     * @return bool
     *
     * @throws \RuntimeException
     */

  public function cancelGooglePaySubscription(
        string $packageName,
        string $productId,
        string $purchaseToken
    ): bool {
        $this->googleValidator
            ->setPackageName($packageName)
            ->setProductId($productId)
            ->setPurchaseToken($purchaseToken);

        return  $this->cancelSubscription(); 
        
    }

    /**
     * Summary of cancelSubscription
     * @throws RuntimeException
     * @return bool
     */
    private function cancelSubscription() 
    {
        try{
            $response = $this->googleValidator->cancelSubscription();
            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Failed to cancel Google Play subscription. Error: ' . $e->getMessage()
            );
        } 
 
         
    }

    /**
     * Validates a Google Play one-time product purchase.
     *
     * @return array
     * @throws \RuntimeException
     */
    private function validateGoogleProduct(): array
    {
        $response = $this->googleValidator->validatePurchase();
        $rawResponse = $response->getRawResponse();
 


        error_log( "orderId : ".$rawResponse->orderId);
        error_log( "getPurchaseState : ".$response->getPurchaseState());
        error_log( "getPurchaseTimeMillis : ".$response->getPurchaseTimeMillis());

        if (!isset($rawResponse->orderId) || !$response->getPurchaseTimeMillis()) { //|| !$response->getPurchaseState()
            throw new \RuntimeException(sprintf(
                'Invalid Google Play purchase'
            )); 
        }

        return [
            'kind'               => $response->getKind(),
            'orderId'            => $rawResponse->orderId,
            'rawResponse'        => $response->getRawResponse(),
            'ackState'           => $response->getAcknowledgementState(),
            'purchaseTimeMillis' => $response->getPurchaseTimeMillis(),
            'purchaseState'      => $response->getPurchaseState(),
            'consumptionState'   => $response->getConsumptionState(),
            'developerPayload'   => $response->getDeveloperPayload(),
        ];
    }


/**
 * Summary of acknowledgeGooglePlaySubscription
 * @param string $packageName
 * @param string $subscriptionId
 * @param string $purchaseToken
 * @throws \RuntimeException
 * @return bool
 */
public function acknowledgeGooglePlaySubscription(string $packageName, string $productId, string $purchaseToken): bool
{
    try {

        // Google configuration
        
        if(defined('INAPP_GOOGLE_SERVICE_ACCOUNT_JSON'))
        {
            // $googleKeyPath = INAPP_GOOGLE_SERVICE_ACCOUNT_JSON;
            $compression_service = new CompressionService();
 
            $compression_service_de = $compression_service->decompress( INAPP_GOOGLE_SERVICE_ACCOUNT_JSON);

            $tmpFile = tmpfile();
            fwrite($tmpFile, $compression_service_de);

                // Get the file path
            $meta = stream_get_meta_data($tmpFile);
            $tmpFilePath = $meta['uri'];
            $serviceAccountPath = $tmpFilePath ;
 
        }else{
            throw new \RuntimeException('Google service account path not defined');
        }

          error_log('Google Play Acknowledgement checking start: ');

        $client = new Google_Client();
        $client->setAuthConfig($serviceAccountPath);
        $client->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
        $client->setApplicationName('Antanukas Reader Acknowledger');

        $service = new Google_Service_AndroidPublisher($client);

        $subscription = $service->purchases_subscriptions->get(
            $packageName,
            $productId,
            $purchaseToken
        );

        if ($subscription->getAcknowledgementState() === 0) {
            $ackRequest = new Google_Service_AndroidPublisher_SubscriptionPurchasesAcknowledgeRequest([
                'developerPayload' => 'ack_' . date('Ymd_His')
            ]);

            $service->purchases_subscriptions->acknowledge(
                $packageName,
                $productId,
                $purchaseToken,
                $ackRequest
            );
        }

        fclose($tmpFile);
        // If we reach here, the acknowledgement was successful     

        error_log('Google Play Acknowledgement Success: ' . $productId . ' [details: ...' . substr($purchaseToken, -12) . ']');

        return true;

    } catch (\Exception $e) {
        $msg = $e->getMessage();
        $tail = substr($purchaseToken, -12);
        error_log('Google Play Acknowledgement Error: ' . $msg . ' [details: ...' . $tail . ']');
        return false;
    }
}

/**
 * Summary of acknowledgeGooglePlayPurchase
 * @param string $packageName
 * @param string $productId
 * @param string $purchaseToken
 * @throws \RuntimeException
 * @return bool
 */
public function acknowledgeGooglePlayPurchase(string $packageName, string $productId, string $purchaseToken): bool
{
    try {

        // Google configuration
        
        if(defined('INAPP_GOOGLE_SERVICE_ACCOUNT_JSON'))
        {
            // $googleKeyPath = INAPP_GOOGLE_SERVICE_ACCOUNT_JSON;
            $compression_service = new CompressionService();
 
            $compression_service_de = $compression_service->decompress( INAPP_GOOGLE_SERVICE_ACCOUNT_JSON);

            $tmpFile = tmpfile();
            fwrite($tmpFile, $compression_service_de);

                // Get the file path
            $meta = stream_get_meta_data($tmpFile);
            $tmpFilePath = $meta['uri'];
            $serviceAccountPath = $tmpFilePath ;
 
        }else{
            throw new \RuntimeException('Google service account path not defined');
        }

        error_log('Google Play Acknowledgement checking start: ');

        $client = new Google_Client();
        $client->setAuthConfig($serviceAccountPath);
        $client->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
        $client->setApplicationName('Antanukas Reader Acknowledger');

        $service = new Google_Service_AndroidPublisher($client);

        $purchase = $service->purchases_products->get(
            $packageName,
            $productId,
            $purchaseToken
        );
 
        if ($purchase->getAcknowledgementState() === 0) {
            $ackRequest = new Google_Service_AndroidPublisher_ProductPurchasesAcknowledgeRequest([
                'developerPayload' => 'ack_' . date('Ymd_His')
            ]);
 

            $service->purchases_products->acknowledge(
                $packageName,
                $productId,
                $purchaseToken,
                $ackRequest
            );
        }

        fclose($tmpFile);
        // If we reach here, the acknowledgement was successful     

        error_log('Google Play Acknowledgement Success: ' . $productId . ' [details: ...' . substr($purchaseToken, -12) . ']');

        return true;

    } catch (\Exception $e) {
        $msg = $e->getMessage();
        $tail = substr($purchaseToken, -12);
        error_log('Google Play Acknowledgement Error: ' . $msg . ' [details: ...' . $tail . ']');
        return false;
    }
}

    
}
