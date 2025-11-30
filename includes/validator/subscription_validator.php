<?php
/**
 * Subscription Validator
 *
 * @package   InAppPurchaseValidator
 * */
class Subscription_Validator
{

    /**
     * Summary of validate_ios_subscription_by_receipt
     * @param mixed $data_response
     * @return array|array{message: string, status: int}
     */
    public function validate_ios_subscription_by_receipt($data_response)
    {

        $validator = new In_App_Receipt_Validator_Service();

        try {
            $response = array(
                'status' => 1,
                'message' => 'ok',
            );
            $appleResult = $validator->validateAppleReceipt($data_response['receipt']);
            $response['data'] = $appleResult;

            $p_paymentInfo = [];
            $p_paymentInfo['date_paid'] = Helper::convert_ms_to_date_time($appleResult['receipt']['in_app'][0]['purchase_date_ms']);
            $p_paymentInfo['start_date'] = Helper::convert_ms_to_date_time($appleResult['receipt']['in_app'][0]['purchase_date_ms']);
            $p_paymentInfo['expires_date'] = Helper::convert_ms_to_date_time($appleResult['receipt']['in_app'][0]['expires_date_ms']);
            $p_paymentInfo['payment_method'] = 'ApplePay';
            $p_paymentInfo['payment_title'] = "Apple pay in app purchase";
            $p_paymentInfo['amount'] = '';
            $p_paymentInfo['currency'] = '';


            $data_response['raw_response'] = $response['data'];
            $data_response['status'] = 'active';


        } catch (\Throwable $e) {

            $response['status'] = 0;
            $response['message'] = 'Payment details validation failed';
            $data_response['status'] = 'failed';

            // echo 'Apple Receipt Error: ' . $e->getMessage();
        }

        if ($data_response['purchase_type'] == 'product') {

            if ($response['status'] == 1) {

                $applePurchaseResult = $validator->getApplePurchases($data_response['receipt'], $data_response['product_id']);

                $response['items'] = $applePurchaseResult;
                $data_response['items'] = $response['items'];
                $data_store = new Data_Store();
                $data_response = Helper::formate_data($data_response);
                $have_ios_sub_data = $data_store->get_ios_purchase_data($data_response);
                $data_response = Helper::formate_data($data_response);
                if ($data_response['purchase_type'] == 'product') {
                    $p_paymentInfo['date_paid'] = $data_response['purchase_time'];
                    $p_paymentInfo['start_date'] = $data_response['used_time'];
                }

                try {

                    if ($response['status'] == 1) {
                        $order = antanukas_inAppPurchase_create_new_order($data_response['user_id'], $data_response['book_id'], $p_paymentInfo);
                        $response['wc_order_id'] = $data_response['wc_order_id'] = $order->get_id();
                        //$response['subscription'] = antanukas_get_subscription_details( $data_response['user_id'] ); 
                        $data_response['status'] = 'active';
                    }

                } catch (\Throwable $e) {

                    $response['status'] = 0;
                    $response['message'] = 'Subscription creating failed';
                    $data_response['status'] = 'failed';

                    // echo 'Apple Receipt Error: ' . $e->getMessage();
                }

                $have_android_product_data = $data_store->fetch_product_data_by_store_product_id($data_response['product_id']);

                if ($have_android_product_data) {

                    $data_response['iap_product_id'] = $have_android_product_data['id'];
                    $data_response['amount'] = $have_android_product_data['price'];
                    $data_response['currency'] = $have_android_product_data['currency'];

                    if ($data_response['book_id']) {

                        $_product = wc_get_product($data_response['book_id']);

                        if (!$_product) {
                            $response['status'] = 0;
                            $response['message'] = 'Product not found in WooCommerce';
                            $data_response['status'] = 'failed';
                            return $response;
                        }

                        $data_response['wc_product_id'] = $_product->get_id();
                        $data_response['wc_product_name'] = $_product->get_name();


                    }

                }

                $data_response['status'] = $data_response['status'] == 'active' ? 'available' : 'failed';


                if ($have_ios_sub_data == false && $response['status'] == 0) {
                    $need_to_insert = true;
                }

                if ($response['status'] == 1) {
                    $need_to_insert = true;
                }

                if ($need_to_insert == true) {
                    $purchase_id = $data_store->store_purchase_data($data_response);
                    $data_response['purchase_id'] = $purchase_id;
                    $data_store->store_ios_purchase_data($data_response);
                }
            }
        }


        if ($data_response['purchase_type'] == 'subscription') {

            $data_store = new Data_Store();
            $data_response = Helper::formate_data($data_response);
            $have_ios_sub_data = $data_store->get_ios_subscription_data($data_response);
            $need_to_insert = false;

            if ($have_ios_sub_data == false && $response['status'] == 0) {
                $need_to_insert = true;
            }

            if ($response['status'] == 1) {
                $need_to_insert = true;
            }

            if ($need_to_insert == true) {
                $data_response = Helper::formate_data($data_response);
                $subscription_id = $data_store->store_subscription_data($data_response);
                $data_response['subscription_id'] = $subscription_id;
                $data_store->store_ios_subscription_data($data_response);
            }


            try {

                if ($response['status'] == 1) {
                    $subscription = antanukas_inAppPurchase_new_subscription($data_response['user_id'], $data_response['product_id'], $p_paymentInfo, $data_response);
                    $response['wc_subscription_id'] = $data_response['wc_subscription_id'] = $subscription->get_id();
                    //$response['subscription'] = antanukas_get_subscription_details( $data_response['user_id'] ); 
                    $data_response['status'] = 'active';
                }

            } catch (\Throwable $e) {

                $response['status'] = 0;
                $response['message'] = 'Subscription creating failed';
                $data_response['status'] = 'failed';

                // echo 'Apple Receipt Error: ' . $e->getMessage();
            }

        }

        return $response;
    }

    /**
     * Summary of validate_only_android_subscription_by_purchase_token
     * @param mixed $data_response
     * @return array[]|array{message: string, status: int}
     */
    public function validate_only_android_subscription_by_purchase_token($data_response)
    {

        $validator = new In_App_Receipt_Validator_Service();

        try {
            $response = array(
                'status' => 1,
                'message' => 'ok',
            );
            $googleResult = $validator->validateGoogleReceipt(
                $data_response['package_name'],
                $data_response['product_id'],
                $data_response['purchase_token'],
                ($data_response['purchase_type'] == 'subscription') ? true : false
            );

            $response['data'] = $googleResult;

            $p_paymentInfo = [];
            $p_paymentInfo['date_paid'] = Helper::convert_ms_to_date_time($googleResult['startTimeMillis']);
            $p_paymentInfo['start_date'] = Helper::convert_ms_to_date_time($googleResult['startTimeMillis']);
            $p_paymentInfo['expires_date'] = Helper::convert_ms_to_date_time($googleResult['expiryTimeMillis']);
            $p_paymentInfo['payment_method'] = 'GooglePay';
            $p_paymentInfo['payment_title'] = "Google pay in app purchase";
            $p_paymentInfo['amount'] = $googleResult['priceAmountMicros'];
            $p_paymentInfo['currency'] = $googleResult['priceCurrencyCode'];



            //antanukas_inAppPurchase_new_subscription($userId, $product_id,$p_paymentInfo);
            // $response['subscription'] = antanukas_get_subscription_details( $userId );

            $data_response['raw_response'] = $response['data'];
            $data_response['status'] = 'active';

        } catch (\Throwable $e) {

            $response['status'] = 0;
            $response['message'] = 'Payment details validation failed';
            $data_response['status'] = 'failed';
            // echo 'Google Receipt Error: ' . $e->getMessage();
        }

        return $response;

    }
    /**
     * Summary of validate_android_subscription_by_purchase_token
     * @param mixed $data_response
     * @return array|array{message: string, status: int|array{message_ack: mixed}}
     */
    public function validate_android_subscription_by_purchase_token($data_response)
    {

        $validator = new In_App_Receipt_Validator_Service();

        try {
            $response = array(
                'status' => 1,
                'message' => 'ok',
            );
            $googleResult = $validator->validateGoogleReceipt(
                $data_response['package_name'],
                $data_response['product_id'],
                $data_response['purchase_token'],
                ($data_response['purchase_type'] == 'subscription') ? true : false
            );

            $response['data'] = $googleResult;

            $p_paymentInfo = [];
            $p_paymentInfo['date_paid'] = Helper::convert_ms_to_date_time($googleResult['startTimeMillis']);
            $p_paymentInfo['start_date'] = Helper::convert_ms_to_date_time($googleResult['startTimeMillis']);
            $p_paymentInfo['expires_date'] = Helper::convert_ms_to_date_time($googleResult['expiryTimeMillis']);
            $p_paymentInfo['payment_method'] = 'GooglePay';
            $p_paymentInfo['payment_title'] = "Google pay in app purchase";
            $p_paymentInfo['amount'] = $googleResult['priceAmountMicros'];
            $p_paymentInfo['currency'] = $googleResult['priceCurrencyCode'];

            //antanukas_inAppPurchase_new_subscription($userId, $product_id,$p_paymentInfo);
            // $response['subscription'] = antanukas_get_subscription_details( $userId );

            $data_response['raw_response'] = $response['data'];
            $data_response['status'] = 'active';

        } catch (\Throwable $e) {

            $response['status'] = 0;
            $response['message'] = 'Payment details validation failed';
            $data_response['status'] = 'failed';
            error_log("Payment details validation failed : " . $e->getMessage());
            // echo 'Google Receipt Error: ' . $e->getMessage();
        }



        // Acknowledge the subscription if it is a subscription type
        try {
            if ($response['status'] == 1) {

                // $result = $validator->acknowledgeGooglePlayPurchase(
                // $result = $validator->acknowledgeGooglePlaySubscription(

                if ($data_response['purchase_type'] == 'subscription') {
                    $result = $validator->acknowledgeGooglePlaySubscription(
                        $data_response['package_name'],
                        $data_response['product_id'],
                        $data_response['purchase_token']
                    );
                }
                if ($data_response['purchase_type'] == 'product') {

                    $result = $validator->acknowledgeGooglePlayPurchase(
                        $data_response['package_name'],
                        $data_response['product_id'],
                        $data_response['purchase_token']
                    );
                }

                if ($result) {
                    $response['message'] = 'Acknowledgement successful';
                    $response['message_ack'] = 'Acknowledgement successful';
                    //echo "Acknowledgement successful.\n";

                } else {
                    $response['status'] = 0;
                    $response['message'] = 'Acknowledgement failed. Check error log for details';
                    $response['message_ack'] = 'Acknowledgement failed. Check error log for details';
                    $data_response['status'] = 'failed';
                    error_log("Acknowledgement failed. Check error log for details");
                    //echo "Acknowledgement failed. Check error log for details.\n";
                }
            }

        } catch (\Throwable $e) {

            $response['status'] = 0;
            $response['message'] = 'Acknowledgement failed. Check error log for details';
            $response['message_ack'] = 'Acknowledgement failed. Check error log for details';
            $data_response['status'] = 'failed';
        }



        if ($data_response['purchase_type'] == 'product' && $response['status'] == 1) {
            $data_store = new Data_Store();
            $data_response = Helper::formate_data($data_response);
            if ($data_response['purchase_type'] == 'product') {
                $p_paymentInfo['date_paid'] = $data_response['purchase_time'];
                $p_paymentInfo['start_date'] = $data_response['used_time'];
            }




            $have_android_product_data = $data_store->fetch_product_data_by_store_product_id($data_response['product_id']);

            if ($have_android_product_data) {

                $data_response['iap_product_id'] = $have_android_product_data['id'];
                $data_response['amount'] = $have_android_product_data['price'];
                $data_response['currency'] = $have_android_product_data['currency'];

                if ($data_response['book_id']) {

                    $_product = wc_get_product($data_response['book_id']);

                    if (!$_product) {
                        $response['status'] = 0;
                        $response['message'] = 'Product not found in WooCommerce';
                        $data_response['status'] = 'failed';
                        return $response;
                    }

                    $data_response['wc_product_id'] = $_product->get_id();
                    $data_response['wc_product_name'] = $_product->get_name();


                }

            }

            try {

                if ($response['status'] == 1) {
                    $order = antanukas_inAppPurchase_create_new_order($data_response['user_id'], $data_response['book_id'], $p_paymentInfo);
                    $response['wc_order_id'] = $data_response['wc_order_id'] = $order->get_id();
                    //$response['subscription'] = antanukas_get_subscription_details( $data_response['user_id'] ); 
                    $data_response['status'] = 'active';
                }

            } catch (\Throwable $e) {

                $response['status'] = 0;
                $response['message'] = 'Subscription creating failed';
                $data_response['status'] = 'failed';

                // echo ' Order creating Error: ' . $e->getMessage();
            }

            $data_response['status'] = $data_response['status'] == 'active' ? 'available' : 'failed';
            // print_r($data_response);
            // exit;
            // $need_to_insert=false;
            //if($need_to_insert==true) {

            $purchase_id = $data_store->store_purchase_data($data_response);
            $data_response['purchase_id'] = $purchase_id;


            $data_store->store_android_purchase_data($data_response);

            // }

        }


        if ($data_response['purchase_type'] == 'subscription') {

            $data_store = new Data_Store();
            $data_response = Helper::formate_data($data_response);

            try {
                if ($response['status'] == 1) {


                    $p_paymentInfo['date_paid'] = $data_response['last_payment'];
                    $p_paymentInfo['start_date'] = $data_response['last_payment'];

                    $subscription = antanukas_inAppPurchase_new_subscription($data_response['user_id'], $data_response[''], $p_paymentInfo, $data_response);
                    $response['wc_subscription_id'] = $data_response['wc_subscription_id'] = $subscription->get_id();
                    // $response['subscription'] = antanukas_get_subscription_details( $data_response['user_id'] ); 
                    $data_response['status'] = 'active';

                }

            } catch (\Throwable $e) {

                $response['status'] = 0;
                $response['message'] = 'Subscription creating failed';
                $data_response['status'] = 'failed';
                // echo 'Google Receipt Error: ' . $e->getMessage();
            }

            if (isset($response['message_ack']) && $response['message_ack'] == 'Acknowledgement successful') {
                $data_response['acknowledge_status'] = 'success';



                $data_response = Helper::formate_data($data_response);
                $have_android_sub_data = $data_store->get_android_subscription_data($data_response);
                $need_to_insert = false;

                if ($have_android_sub_data == false && $response['status'] == 0) {
                    $need_to_insert = true;
                }

                if ($response['status'] == 1) {

                    $need_to_insert = true;
                }

                if ($need_to_insert == true) {
                    $subscription_id = $data_store->store_subscription_data($data_response);
                    $data_response['subscription_id'] = $subscription_id;
                    $data_store->store_android_subscription_data($data_response);

                }
            }
        }

        return $response;
    }


    /**
     * Summary of re_validate_receipt_token_by_id
     * @param mixed $id
     * @return bool
     */
    public function re_validate_receipt_token_by_id($id)
    {


        $data_store = new Data_Store();
        $data_subs_iap = $data_store->fetch_data_by_Id($id);
        $userId = isset($data_subs_iap['user_id']) ? $data_subs_iap['user_id'] : '';

        if ($userId) {

            if (!$data_subs_iap) {
                return false;
            }

            $data_subs_iap_raw_response = isset($data_subs_iap['raw_response']) ? json_decode($data_subs_iap['raw_response']) : '';


            $platform = isset($data_subs_iap['platform']) ? $data_subs_iap['platform'] : '';
            $product_id = isset($data_subs_iap['product_id']) ? $data_subs_iap['product_id'] : '';
            $base64Receipt = isset($data_subs_iap_raw_response->latest_receipt) ? $data_subs_iap_raw_response->latest_receipt : '';
            $package_name = isset($data_subs_iap['package_name']) ? $data_subs_iap['package_name'] : '';
            $purchase_token = isset($data_subs_iap['purchase_token']) ? $data_subs_iap['purchase_token'] : '';
            $purchase_type = 'subscription';


            $data_response['user_id'] = $userId;
            $data_response['receipt'] = $base64Receipt;
            $data_response['package_name'] = $package_name;
            $data_response['purchase_token'] = $purchase_token;
            $data_response['purchase_type'] = $purchase_type;
            $data_response['platform'] = $platform;
            $data_response['product_id'] = $product_id;


            if ($platform == 'ios') {

                $subscription_id = isset($data_subs_iap['wc_subscription_id']) ? $data_subs_iap['wc_subscription_id'] : '';

                if (!empty($subscription_id) && $subscription_id > 0) {

                    $subscription = wcs_get_subscription($subscription_id);

                    if (in_array($subscription->get_status(), ['active', 'on-hold'])) {
                        if ($subscription->get_status() != 'on-hold') {
                            $subscription->update_status('on-hold', 'Subscription renewal payment due re-validate by id ios:');
                        }
                    }
                }


                $response = $this->validate_ios_subscription_by_receipt($data_response);

            }

            if ($platform == 'android') {

                $subscription_id = isset($data_subs_iap['wc_subscription_id']) ? $data_subs_iap['wc_subscription_id'] : '';
                if (!empty($subscription_id) && $subscription_id > 0) {

                    $subscription = wcs_get_subscription($subscription_id);

                    if (in_array($subscription->get_status(), ['active', 'on-hold'])) {
                        if ($subscription->get_status() != 'on-hold') {
                            $subscription->update_status('on-hold', 'Subscription renewal payment due re-validate by id android:');
                        }
                    }
                }

                $response = $this->validate_android_subscription_by_purchase_token($data_response);
            }

            if ($response['status'] == 1)
                return true;

        } else {
            return false;
        }

        return false;
    }

    /**
     * Summary of re_validate_purchase_receipt_token_by_id
     * @param mixed $id
     * @return bool
     */
    public function re_validate_purchase_receipt_token_by_id($id)
    {


        $data_store = new Data_Store();
        $data_purchase_iap = $data_store->fetch_purchase_data_by_Id($id);
        $userId = isset($data_purchase_iap['wc_user_id']) ? $data_purchase_iap['wc_user_id'] : '';

        if ($userId) {

            if (!$data_purchase_iap) {
                return false;
            }

            $data_purchase_iap_raw_response = isset($data_purchase_iap['raw_response']) ? json_decode($data_purchase_iap['raw_response']) : '';


            $platform = isset($data_purchase_iap['platform']) ? $data_purchase_iap['platform'] : '';
            $product_id = isset($data_purchase_iap['product_id']) ? $data_purchase_iap['product_id'] : '';
            $book_id = isset($data_purchase_iap['wc_product_id']) ? $data_purchase_iap['wc_product_id'] : '';
            $base64Receipt = isset($data_purchase_iap->latest_receipt) ? $data_purchase_iap_raw_response->latest_receipt : '';
            $package_name = isset($data_purchase_iap['package_name']) ? $data_purchase_iap['package_name'] : '';
            $purchase_token = isset($data_purchase_iap['purchase_token']) ? $data_purchase_iap['purchase_token'] : '';
            $purchase_type = 'product';


            $data_response['user_id'] = $userId;
            $data_response['book_id'] = $book_id;
            $data_response['receipt'] = $base64Receipt;
            $data_response['package_name'] = $package_name;
            $data_response['purchase_token'] = $purchase_token;
            $data_response['purchase_type'] = $purchase_type;
            $data_response['platform'] = $platform;
            $data_response['product_id'] = $product_id;


            if ($platform == 'ios') {

                $wc_order_id = isset($data_purchase_iap['wc_order_id']) ? $data_purchase_iap['wc_order_id'] : '';

                if (!empty($wc_order_id) && $wc_order_id > 0) {

                    $order = wc_get_order($wc_order_id);

                }


                $response = $this->validate_ios_subscription_by_receipt($data_response);

            }

            if ($platform == 'android') {

                $wc_order_id = isset($data_purchase_iap['wc_order_id']) ? $data_purchase_iap['wc_order_id'] : '';
                if (!empty($wc_order_id) && $wc_order_id > 0) {

                    $order = wc_get_order($wc_order_id);

                    $order = wc_get_order($wc_order_id);
                }

                $response = $this->validate_android_subscription_by_purchase_token($data_response);
            }

            if ($response['status'] == 1)
                return true;

        } else {
            return false;
        }

        return false;
    }

    /**
     * Summary of re_validate_receipt_token
     * @param mixed $subscription
     * @return bool
     */
    public function re_validate_receipt_token($subscription)
    {


        $userId = $subscription->get_user_id();

        if ($userId) {

            $subscription_id = $subscription->get_id();

            $data_store = new Data_Store();
            $data_subs_iap = $data_store->fetch_data_by_subscription_Id($subscription_id);

            if (!$data_subs_iap) {
                return false;
            }

            $data_subs_iap_raw_response = isset($data_subs_iap['raw_response']) ? json_decode($data_subs_iap['raw_response']) : '';


            $platform = isset($data_subs_iap['platform']) ? $data_subs_iap['platform'] : '';
            $product_id = isset($data_subs_iap['product_id']) ? $data_subs_iap['product_id'] : '';
            $base64Receipt = isset($data_subs_iap_raw_response->latest_receipt) ? $data_subs_iap_raw_response->latest_receipt : '';
            $package_name = isset($data_subs_iap['package_name']) ? $data_subs_iap['package_name'] : '';
            $purchase_token = isset($data_subs_iap['purchase_token']) ? $data_subs_iap['purchase_token'] : '';
            $purchase_type = 'subscription';


            $data_response['user_id'] = $userId;
            $data_response['receipt'] = $base64Receipt;
            $data_response['package_name'] = $package_name;
            $data_response['purchase_token'] = $purchase_token;
            $data_response['purchase_type'] = $purchase_type;
            $data_response['platform'] = $platform;
            $data_response['product_id'] = $product_id;

            if ($subscription->get_payment_method() === 'ApplePay') {

                // if ( in_array( $subscription->get_status() , [ 'active', 'on-hold' ] ) ) {
                //     if($subscription->get_status() != 'on-hold') {
                //         $subscription->update_status( 'on-hold', 'Subscription renewal payment due:');
                //     }
                // }

                $response = $this->validate_ios_subscription_by_receipt($data_response);

            }

            if ($subscription->get_payment_method() === 'GooglePay') {

                //  if ( in_array($subscription->get_status() , [ 'active', 'on-hold' ] ) ) {
                //     if($subscription->get_status() != 'on-hold') {
                //         $subscription->update_status( 'on-hold', 'Subscription renewal payment due:');
                //     }
                // }

                $response = $this->validate_android_subscription_by_purchase_token($data_response);
            }

            if ($response['status'] == 1)
                return true;

        } else {
            return false;
        }

        return false;
    }
}

 
if (!function_exists('antanukas_inAppPurchase_new_subscription')) {
    /**
     * Summary of antanukas_inAppPurchase_new_subscription
     * @param mixed $p_user_id
     * @param mixed $p_productId
     * @param mixed $p_paymentInfo
     * @param mixed $data_response
     * @return \WC_Subscription
     */
    function antanukas_inAppPurchase_new_subscription($p_user_id, $p_productId, $p_paymentInfo, $data_response)
    {
        $subscriber_action = new Subscriber_Action();
        return $subscriber_action->create_subscription_for_user($p_user_id, $p_productId, $p_paymentInfo, $data_response);
    }
}

if (!function_exists('antanukas_inAppPurchase_create_new_order')) {
/**
     * Summary of antanukas_inAppPurchase_create_new_order
     * @param mixed $p_user_id
     * @param mixed $p_productId
     * @param mixed $p_paymentInfo
     * @return \WC_Order
     */
    function antanukas_inAppPurchase_create_new_order($p_user_id, $p_productId, $p_paymentInfo)
    {
        $subscriber_action = new Subscriber_Action();
        return $subscriber_action->create_new_order($p_productId, $p_user_id, $p_paymentInfo, 'Programatically Creating new purchase order');
    }
}

if (!function_exists('antanukas_get_subscription_details')) {
    /**
     * Summary of antanukas_get_subscription_details
     * @param mixed $p_user_id
     * @return array
     */
    function antanukas_get_subscription_details($p_user_id)
    {
        $subscriber_action = new Subscriber_Action();
        return $subscriber_action->get_subscription_details($p_user_id);
    }
}

