<?php

/**
 * Plugin Name:       Antanukas In App Purchase (IAP)
 * Description:       Personal use for Antanukas development IAP.
 * Version:           1.0.2
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Ashaduzzaman
 * Author URI:   ''
 * Text Domain:       antanukasiap
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

 if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}


require_once __DIR__ . "/vendor/autoload.php";
use Firebase\JWT\JWT;
use Firebase\JWT\JWTNew;

use Firebase\JWT\JWK;
use Firebase\JWT\KEY;


require_once( ABSPATH. 'wp-includes/pluggable.php');


require_once( __DIR__ . '/includes/database/db-tables.php' );
require_once( __DIR__ . '/includes/subscriber-action.php' );
require_once( __DIR__ . '/includes/admin/settings.php' );
require_once( __DIR__ . '/includes/admin/classes/wpant_iap_products_manager.php' );


// require_once( __DIR__ . '/includes/admin/iap_subscriptions_list_table.php' );
require_once( __DIR__ . '/includes/compression-service.php' );
require_once( __DIR__ . '/in-app-receipt-validator-service.php' );
require_once( __DIR__ . '/includes/data-store.php' );
require_once( __DIR__ . '/includes/functions.php' );
require_once( __DIR__ . '/includes/iap-products.php' );
require_once( __DIR__ . '/includes/helper/helper.php' );
require_once( __DIR__ . '/includes/helper/AppleAuthHelper.php' );
require_once( __DIR__ . '/includes/helper/ApplePriceTierHelper.php' );
require_once( __DIR__ . '/includes/validator/subscription_validator.php' );

require_once( __DIR__ . '/in-app-products-manager.php' );
require_once( __DIR__ . '/apple_iap_product_manager.php' );
// require_once( __DIR__ . '/cron-scripts/check-subscription-expiration-re-validate.php' );



register_activation_hook( __FILE__, 'antanukasiap_activate' );
register_uninstall_hook( __FILE__, 'antanukasiap_uninstall' );


add_action('rest_api_init', function () { 

    register_rest_route('wc/v1', 'iap-purchase', array(
        'methods' => 'POST',
        'callback' => 'antanukas_inAppPurchase',
    ));

    register_rest_route('iap/v1', 'webhook/ios', array(
        'methods' => 'POST',
        'callback' => 'antanukas_in_app_purchase_webhook_ios',
    ));

   register_rest_route('iap/v1', 'webhook/android', array(
        'methods' => 'POST',
        'callback' => 'antanukas_in_app_purchase_webhook_android',
    ));

    register_rest_route('iap/v1', 'webhook/testing', array(
        'methods' => 'POST',
        'callback' => 'antanukas_in_app_purchase_webhook_checking'
    ));


});


add_filter('rest_pre_dispatch', 'rest_pre_dispatch_fun', 99999, 3);
function rest_pre_dispatch_fun($result, $server, $request)
{

    $route = $request->get_route();

    // Define the endpoints where you want to skip JWT auth
    $whitelisted_routes = [
        '/iap/v1/webhook/testing',
        '/iap/v1/webhook/android',
    ];

    foreach ($whitelisted_routes as $whitelist) {
        if (stripos($route, $whitelist) !== false) {
            // Option 2: Allow it to proceed, skipping authentication
            remove_filter('determine_current_user', 'jwt_authenticate_user', 20);
            return null; // Let it proceed to controller without JWT
        }
    }

    return $result; // Default behavior
}

/**
 * Summary of antanukas_in_app_purchase_webhook_checking
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */

function antanukas_in_app_purchase_webhook_checking(WP_REST_Request $request)
{

    // echo "Debuging android web hook data=>".PHP_EOL;
    error_log("Debuging android web hook data 2=>");

    error_log("Headers=>" . json_encode($request->get_headers()));

    error_log("Body=>" . $request->get_body());

    $request_body = $request->get_body();
    $request_body_data = json_decode($request_body, true);


    $version = isset($request_body_data['version']) ? $request_body_data['version'] : '';
    $package_name = isset($request_body_data['packageName']) ? $request_body_data['packageName'] : '';
    $event_time_millis = isset($request_body_data['data']) ? Helper::convert_ms_to_date_time($request_body_data['eventTimeMillis']) : '';

    $subscription_notification = isset($request_body_data['subscriptionNotification']) ? $request_body_data['subscriptionNotification'] : '';
    $notificationType = isset($request_body_data['subscriptionNotification']) ? $request_body_data['subscriptionNotification']['notificationType'] : '';
    $purchaseToken = isset($request_body_data['subscriptionNotification']) ? $request_body_data['subscriptionNotification']['purchaseToken'] : '';
    $subscriptionId = isset($request_body_data['originalTransactionId']) ? $request_body_data['originalTransactionId']['subscriptionId'] : '';

    error_log("version=>");
    error_log($version);
    error_log("package_name=>");
    error_log($package_name);
    error_log("event_time_millis=>");
    error_log($event_time_millis);
    error_log("subscription_notification=>");
    error_log($subscription_notification);
    error_log("notificationType=>");
    error_log($notificationType);
    error_log("purchaseToken=>");
    error_log($purchaseToken);
    error_log("purchaseToken=>");
    error_log($purchaseToken);
    error_log("subscriptionId=>");
    error_log($subscriptionId);


    print_r($request);

}

/**
 * Summary of IAP gooogle pay and apple IAP subscription validation function
 * @param  WP_REST_Request $request
 * @return WP_Error|WP_REST_Response
 */

if (!function_exists('antanukas_inAppPurchase')) {
    function antanukas_inAppPurchase(WP_REST_Request $request): WP_Error|WP_REST_Response
    {

        $validation = Jwt_Auth_Public::validate_token(false);
        $userId = $validation->data->user->id;



        // echo "Debuging antanukas IAP API for app=>".PHP_EOL;
        error_log("Debuging antanukas IAP API for app=>");

        error_log("User Id=>" . $userId);

        error_log("Headers=>" . json_encode($request->get_headers()));

        error_log("Body=>" . $request->get_body());



        if (!$userId) {
            error_log("Missing/invalid auth token=>");
            return new WP_Error('Authentication required', 'Missing/invalid auth token', array('status' => 401));
        }



        $check_json_data = $request->get_json_params();
        if (!isset($check_json_data) || isset($check_json_data[''])) {
            error_log("Missing / malformed JSON body=>");
            return new WP_Error('Syntax / structure issues', 'Missing / malformed JSON body', array('status' => 400));
        }

        $response = null;
        if ($userId) {

            $platform = isset($request['platform']) ? sanitize_text_field($request['platform']) : '';
            $book_id = isset($request['book_id']) ? sanitize_text_field($request['book_id']) : '';
            $product_id = isset($request['product_id']) ? sanitize_text_field($request['product_id']) : '';
            $base64Receipt = isset($request['receipt']) ? $request['receipt'] : '';
            $package_name = isset($request['package_name']) ? sanitize_text_field($request['package_name']) : '';
            $purchase_token = isset($request['purchase_token']) ? sanitize_text_field($request['purchase_token']) : '';
            $purchase_type = isset($request['purchase_type']) ? sanitize_text_field($request['purchase_type']) : '';

            $in_valid_fields = false;

            if (empty($platform) || !in_array($platform, ['ios', 'android'])) {
                $in_valid_fields = true;
            }

            if (empty($purchase_type) || ($purchase_type != 'subscription' && $purchase_type != 'product')) {
                $in_valid_fields = true;
            }

            if ($platform == 'android') {

                if ($purchase_type == 'subscription') {
                    if (empty($product_id) || $product_id != 'lt.antanukas.subscription.monthly') {
                        $in_valid_fields = true;
                    }
                }

                // if( $purchase_type == 'product'){
                //     if (empty($product_id) || $product_id != 'lt.antanukas.subscription.monthly') {
                //                             $in_valid_fields = true;
                //     }
                // }


                if (empty($package_name) || $package_name != 'lt.antanukas.reader') {
                    $in_valid_fields = true;
                }
                if (empty($purchase_token)) {
                    $in_valid_fields = true;
                }
            }

            if ($platform == 'ios' && (empty($base64Receipt) || empty($product_id))) {
                $in_valid_fields = true;
            }


            if ($in_valid_fields == true) {
                return new WP_Error('Semantic problems', 'Validation errors (invalid field data) ', array('status' => 422));
            }

            $data_response['user_id'] = $userId;
            $data_response['receipt'] = $base64Receipt;
            $data_response['platform'] = $platform;
            $data_response['product_id'] = $product_id;
            $data_response['book_id'] = $book_id;
            $data_response['purchase_type'] = $purchase_type;
            $data_response['package_name'] = $package_name;
            $data_response['purchase_token'] = $purchase_token;

            $lock_key = 'subscription_lock_' . $userId;

            if (get_transient($lock_key)) {
                return new WP_Error('duplicate_request', 'A subscription request is already being processed.');
            }

            set_transient($lock_key, true, 10); // lock for 5 seconds

            $subscription_validator = new Subscription_Validator();

            if ($platform == 'ios' && in_array($purchase_type, ['subscription', 'product'])) {


                $response = $subscription_validator->validate_ios_subscription_by_receipt($data_response);
                // Remove lock
                delete_transient($lock_key);
            }

            if ($platform == 'android') {
                $response = $subscription_validator->validate_android_subscription_by_purchase_token($data_response);
                // Remove lock
                delete_transient($lock_key);

                if ($response['status'] == 0 && $response['message'] == 'Payment details validation failed') {
                    $response = [
                        'success' => 0,
                        'message' => 'Payment details validation failed',
                    ];

                    return new WP_Error('Validation Error', 'Validation errors (Payment details validation failed) ', array('status' => 400));

                }

                if ($response['status'] == 0 && $response['message'] == 'Acknowledgement failed. Check error log for details') {

                    error_log("Acknowledgement failed. Check error log for details");

                    return new WP_Error('Acknowledgement failed.', 'Acknowledgement failed. Check error log for details', array('status' => 400));

                }


                if ($response['status'] == 0 && $response['message'] == 'Subscription creating failed') {

                    error_log("Subscription creation failed");

                    return new WP_Error('Semantic problems', 'Validation errors (Subscription creating failed) ', array('status' => 400));

                }


            }

        } else {
            $response = [
                'success' => 0,
                'message' => 'ok',
            ];
        }


        return new WP_REST_Response($response);
    }
}

/**
 * Summary   apple IAP subscription validation webhook method
 * @param  WP_REST_Request $request
 * @return WP_Error|WP_REST_Response
 */

if (!function_exists('antanukas_in_app_purchase_webhook_ios')) {
    function antanukas_in_app_purchase_webhook_ios(WP_REST_Request $request_param)
    {

        $request_body = $request_param->get_body();
        $request = json_decode($request_body, true);

        error_log("IOS webhook Headers=>" . json_encode($request_param->get_headers()));

        error_log("IOS webhook Body=>" . $request_param->get_body());

        error_log("IOS webhook Body request decoded=>" . $request);

        $notification_type = isset($request['notificationType']) ? $request['notificationType'] : '';
        $subtype = isset($request['subtype']) ? $request['subtype'] : '';
        $data = isset($request['data']) ? $request['data'] : '';
        $app_apple_id = isset($request['data']) ? $request['data']['appAppleId'] : '';
        $bundle_id = isset($request['data']) ? $request['data']['bundleId'] : '';
        $original_transaction_id = isset($request['data']) ? $request['data']['originalTransactionId'] : '';
        $product_id = isset($request['data']) ? $request['data']['productId'] : '';
        $purchase_date = isset($request['data']) ? Helper::convert_ms_to_date_time($request['data']['purchaseDate']) : '';

        $environment = isset($request['environment']) ? $request['environment'] : '';

        if ($data) {

            if (empty($original_transaction_id) || $original_transaction_id == '') {

                $response = [
                    'success' => 0,
                    'message' => 'Original transaction id is missing',
                ];

                error_log("Original transaction id is missing");

                return new WP_REST_Response($response);
            }


            if ($notification_type == 'ONE_TIME_CHARGE') {
                $response = [
                    'success' => 1,
                    'message' => 'ok',
                ];
            } else {


                $need_to_cancel_subscription = false;

                if (in_array($notification_type, ['GRACE_PERIOD_EXPIRED'])) {
                    $need_to_cancel_subscription = true;
                }

                if ($notification_type == 'EXPIRED' && in_array($subtype, ['VOLUNTARY', 'BILLING_RETRY'])) {
                    $need_to_cancel_subscription = true;
                }

                if ($notification_type == 'DID_CHANGE_RENEWAL_STATUS' && $subtype == 'AUTO_RENEW_DISABLED') {
                    $need_to_cancel_subscription = true;
                }

                if ($notification_type == 'DID_FAIL_TO_RENEW' && $subtype == '') {
                    $need_to_cancel_subscription = true;
                }

                if ($need_to_cancel_subscription == true) {

                    $data_store = new Data_Store();
                    $sub_data = $data_store->fetch_data_by_original_transaction_id_or_purchase_token($original_transaction_id, 'ios');

                    if (!$sub_data) {

                        $error_msg = 'IAP subscription not found in antanukas database by this original transaction id' . $original_transaction_id;
                        $response = [
                            'success' => 0,
                            'message' => $error_msg,
                        ];

                        error_log($error_msg);

                        return new WP_REST_Response($response);
                    }
                    $data_response['user_id'] = $sub_data['user_id'];
                    $data_response['receipt'] = $sub_data['receipt'];
                    $data_response['package_name'] = $sub_data['package_name'];
                    $data_response['purchase_token'] = $sub_data['purchase_token'];
                    $data_response['purchase_type'] = 'subscription';
                    $data_response['platform'] = $sub_data['platform'];
                    $data_response['product_id'] = $product_id;
                    $data_response['notification_type'] = 'cancell';
                    $data_response['subtype'] = $subtype;
                    $data_response['environment'] = $environment;
                    $data_response['purchase_date'] = $purchase_date;
                    $data_response['action_type'] = 'webhook';


                    $subscription_validator = new Subscription_Validator();

                    try {

                        $response = $subscription_validator->validate_ios_subscription_by_receipt($data_response);

                        $response = [
                            'success' => 1,
                            'message' => 'ok',
                        ];

                        if ($response['status'] == 0 && $response['message'] == 'Payment details validation failed') {

                            error_log("Validation failed");

                            $response = [
                                'success' => 0,
                                'message' => 'Payment details validation failed',
                            ];

                            $data_store->update_subscription_status($sub_data['id'], 'failed');

                        }
                        if ($response['status'] == 0 && $response['message'] == 'Subscription creating failed') {

                            error_log("Subscription creation failed");

                            $response = [
                                'success' => 0,
                                'message' => 'Subscription creating failed',
                            ];

                        }

                    } catch (\Throwable $e) {

                        error_log("Validation failed or subscription creation failed");

                        $response = [
                            'success' => 0,
                            'message' => 'Subscription creating failed',
                        ];
                    }

                }
            }


        } else {
            $response = [
                'success' => 0,
                'message' => 'ok',
            ];
        }
        return new WP_REST_Response($response);
    }
}

/**
 * Summary of IAP gooogle pay  subscription validation function
 * @param  WP_REST_Request $request
 * @return WP_Error|WP_REST_Response
 */


if (!function_exists('antanukas_in_app_purchase_webhook_android')) {
    function antanukas_in_app_purchase_webhook_android(WP_REST_Request $request_param)
    {

        $request_body = $request_param->get_body();
        $request = json_decode($request_body, true);

        $headers_data = $request_param->get_headers();

        if (isset($headers_data['authorization']) && !empty($headers_data['authorization'])) {

            $auth_header = explode(" ", $headers_data['authorization'][0]);
            $authorization_data = $auth_header[1];

            $jwks = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v3/certs'), true);
            $keys = JWK::parseKeySet($jwks);


            // Step 3: Decode & validate JWT
            try {
                $decoded = JWT::decode($authorization_data, $keys, ['RS256']);
                // Step 3: Decode & validate JWT);
                // Optional: validate issuer, audience, expiry
                if ($decoded->iss !== 'https://accounts.google.com' && $decoded->iss !== 'accounts.google.com') {
                    throw new Exception('Invalid issuer');
                }

                // Success: you can now trust $decoded->sub, email, etc.
                error_log("Valid JWT from Google: " . $decoded->sub);

            } catch (Exception $e) {

                echo "Invalid token: " . $e->getMessage();

                error_log("Invalid token: " . $e->getMessage());
                return new WP_Error('Authentication required', 'Missing/invalid auth token', array('status' => 401));

            }
        } else {
            return new WP_Error('Authentication required', 'Missing/invalid auth token', array('status' => 401));
        }

        error_log("android webhook Headers=>" . json_encode($request_param->get_headers()));

        error_log("android webhook Body=>" . $request_param->get_body());


        $version = isset($request['version']) ? $request['version'] : '';
        $package_name = isset($request['packageName']) ? $request['packageName'] : '';
        $event_time_millis = isset($request['eventTimeMillis']) ? Helper::convert_ms_to_date_time($request['eventTimeMillis']) : '';

        $subscription_notification = isset($request['subscriptionNotification']) ? $request['subscriptionNotification'] : '';
        if (isset($subscription_notification) && !empty($subscription_notification)) {

            $notificationType = isset($request['subscriptionNotification']) ? $request['subscriptionNotification']['notificationType'] : '';
            $purchaseToken = isset($request['subscriptionNotification']) ? $request['subscriptionNotification']['purchaseToken'] : '';
            $subscriptionId = isset($request['originalTransactionId']) ? $request['originalTransactionId']['subscriptionId'] : '';
        }
        /**voidedpurchasenotification */
        $voided_purchase_notification = isset($request['voidedPurchaseNotification']) ? $request['voidedPurchaseNotification'] : '';
        if (isset($voided_purchase_notification) && !empty($voided_purchase_notification)) {

            $refundType = isset($request['voidedPurchaseNotification']) ? $request['voidedPurchaseNotification']['refundType'] : '';
            $purchaseToken = isset($request['voidedPurchaseNotification']) ? $request['voidedPurchaseNotification']['purchaseToken'] : '';
        }


        // echo "refundType=".$refundType.PHP_EOL; exit;

        if (isset($voided_purchase_notification) && !empty($voided_purchase_notification)) {

            if (empty($purchaseToken) || $purchaseToken == '') {

                $response = [
                    'success' => 0,
                    'message' => 'Purchase token is missing',
                ];

                error_log("Purchase token is missing");

                return new WP_REST_Response($response);
            }


            if (isset($refundType)) {

                $data_store = new Data_Store();
                $sub_data = $data_store->fetch_data_by_original_transaction_id_or_purchase_token($purchaseToken, 'android');

                if (!$sub_data) {

                    $error_msg = 'IAP subscription not found in antanukas database by this purchaseToken' . $purchaseToken;

                    $response = [
                        'success' => 0,
                        'message' => $error_msg,
                    ];

                    error_log($error_msg);

                    return new WP_REST_Response($response);
                }

                $subscriptionId = isset($subscriptionId) ? $subscriptionId : 'lt.antanukas.subscription.monthly';
                $data_response['user_id'] = $sub_data['user_id'];
                $data_response['package_name'] = $package_name;
                $data_response['purchase_token'] = $purchaseToken;
                $data_response['purchase_type'] = 'subscription';
                $data_response['platform'] = $sub_data['platform'];
                $data_response['notification_type'] = '';
                $data_response['notification_type_check'] = (in_array($notificationType, [1, 2])) ? 'renew' : ((in_array($notificationType, [3, 13, 20])) ? 'cancel' : '');
                $data_response['purchase_date'] = $event_time_millis;
                $data_response['product_id'] = $subscriptionId;
                $data_response['action_type'] = 'webhook';

                $subscription_validator = new Subscription_Validator();


                $response = $subscription_validator->validate_only_android_subscription_by_purchase_token($data_response);

                if ($response['status'] == 0 && $response['message'] == 'Payment refund validation failed') {

                    error_log("voidedpurchaseNotification Refund Validation failed");

                    $response = [
                        'success' => 0,
                        'message' => 'Payment details validation failed',
                    ];


                }

                if ($response['status'] == 1) {

                    $subscription_id = isset($sub_data['wc_subscription_id']) ? $sub_data['wc_subscription_id'] : '';

                    if (!empty($subscription_id) && $subscription_id > 0) {

                        $subscription = wcs_get_subscription($subscription_id);

                        // Get related orders: parent + renewals
                        // $related_orders = $subscription->get_related_orders();

                        $latest_order_id = $subscription->get_last_order();

                        $latest_order = wc_get_order($latest_order_id);


                        if ($latest_order && $latest_order->get_status() !== 'refunded') {
                            $latest_order->update_status('refunded', 'Subscription order  refunded by android webhook due to refunded action.');
                        }


                        if ($subscription->can_be_updated_to('cancelled') && in_array($subscription->get_status(), ['active', 'on-hold', 'pending-cancel'])) {

                            $subscription->update_status('cancelled', 'Subscription pending-cancel via android webhook for refunded order  voidedpurchasenotification ');
                        }
                    }

                    $status = 'refunded';
                    $data_store->update_subscription_status($sub_data['id'], $status);

                    error_log("voidedpurchaseNotification Refunded successfully");
                }

                return new WP_REST_Response($response);

            }

        }

        if (isset($subscription_notification) && !empty($subscription_notification)) {

            if (empty($purchaseToken) || $purchaseToken == '') {

                $response = [
                    'success' => 0,
                    'message' => 'Purchase token is missing',
                ];

                error_log("Purchase token is missing");

                return new WP_REST_Response($response);
            }

            if (isset($notificationType) && !empty($notificationType) && in_array($notificationType, [1, 2, 3, 6, 12, 13, 20])) {

                $data_store = new Data_Store();
                $sub_data = $data_store->fetch_data_by_original_transaction_id_or_purchase_token($purchaseToken, 'android');

                if (!$sub_data) {

                    $error_msg = 'IAP subscription not found in antanukas database by this purchaseToken' . $purchaseToken;

                    $response = [
                        'success' => 0,
                        'message' => $error_msg,
                    ];

                    error_log($error_msg);

                    return new WP_REST_Response($response);
                }

                $data_response['user_id'] = $sub_data['user_id'];
                $data_response['package_name'] = $package_name;
                $data_response['purchase_token'] = $purchaseToken;
                $data_response['purchase_type'] = 'subscription';
                $data_response['platform'] = $sub_data['platform'];
                $data_response['notification_type'] = '';
                $data_response['notification_type_check'] = (in_array($notificationType, [1, 2])) ? 'renew' : ((in_array($notificationType, [3, 13, 20])) ? 'cancel' : '');
                $data_response['purchase_date'] = $event_time_millis;
                $data_response['product_id'] = 'lt.antanukas.subscription.monthly';
                $data_response['action_type'] = 'webhook';

                $subscription_validator = new Subscription_Validator();

                try {

                    if (in_array($notificationType, [6, 12])) {

                        $response = $subscription_validator->validate_only_android_subscription_by_purchase_token($data_response);
                        if ($response['status'] == 1) {

                            $data_store->update_android_remove_grace_period($sub_data['id'], $notificationType);

                        }

                        if ($response['status'] == 0 && $response['message'] == 'Payment details validation failed') {

                            error_log(" android Web hook Validation failed for notificationType 6,12");

                            $response = [
                                'success' => 0,
                                'message' => 'Payment details validation failed',
                            ];

                            $data_store->update_subscription_status($sub_data['id'], 'failed');

                        }

                        if ($response['status'] == 1) {

                            $subscription_id = isset($sub_data['wc_subscription_id']) ? $sub_data['wc_subscription_id'] : '';

                            if (!empty($subscription_id) && $subscription_id > 0) {

                                $subscription = wcs_get_subscription($subscription_id);

                                if (in_array($subscription->get_status(), ['active', 'pending-cancel'])) {
                                    //if($subscription->get_status() != 'on-hold') {
                                    $subscription->update_status('on-hold', 'Subscriptionon-hold via android webhook for notificationType' . $notificationType);
                                    // }
                                }

                                // else if($subscription->get_status()== 'active' ){
                                //      $subscription->update_status( 'on-hold', 'Subscription on-hold via android webhook');
                                // }
                            }

                            $data_store->update_subscription_status($sub_data['id'], 'active');
                        }


                    } else if (in_array($notificationType, [1, 2])) {

                        $response = $subscription_validator->validate_android_subscription_by_purchase_token($data_response);

                        if ($response['status'] == 1) {
                            $data_store->update_subscription_status($sub_data['id'], 'active');
                        }


                    } else {
                        $response = $subscription_validator->validate_only_android_subscription_by_purchase_token($data_response);

                        if ($response['status'] == 0 && $response['message'] == 'Payment details validation failed') {

                            error_log("Validation failed");

                            $response = [
                                'success' => 0,
                                'message' => 'Payment details validation failed',
                            ];

                            $data_store->update_subscription_status($sub_data['id'], 'failed');

                        }

                        if ($response['status'] == 0 && $response['message'] == 'Subscription creating failed') {

                            error_log("Subscription creation failed");

                            $response = [
                                'success' => 0,
                                'message' => 'Subscription creating failed',
                            ];

                        }

                        if ($response['status'] == 1 && !in_array($notificationType, [1, 2])) {

                            $subscription_id = isset($sub_data['wc_subscription_id']) ? $sub_data['wc_subscription_id'] : '';

                            if (!empty($subscription_id) && $subscription_id > 0) {

                                $subscription = wcs_get_subscription($subscription_id);

                                if (in_array($subscription->get_status(), ['active', 'on-hold'])) {
                                    //if($subscription->get_status() != 'on-hold') {
                                    $subscription->update_status('cancelled', 'Subscription pending-cancel via android webhook for notificationType ' . $notificationType);
                                    // }
                                }

                                // else if($subscription->get_status()== 'active' ){
                                //      $subscription->update_status( 'on-hold', 'Subscription on-hold via android webhook');
                                // }
                            }

                            $status = 'cancelled';
                            if (in_array($notificationType, [3, 13, 20])) {
                                if (isset($sub_data['revoked_or_grace']) && (in_array($sub_data['revoked_or_grace'], [6, 12]))) {
                                    $status = 'refunded';
                                } else if ($notificationType == 13) {
                                    $status = 'expired';
                                }
                            }

                            $data_store->update_subscription_status($sub_data['id'], $status);
                        }

                    }

                } catch (\Throwable $e) {

                    error_log("Validation failed or subscription creation failed");

                    $response = [
                        'success' => 0,
                        'message' => 'ok',
                    ];

                }

            }

        } else {
            $response = [
                'success' => 0,
                'message' => 'ok',
            ];
        }


        return new WP_REST_Response($response);
    }
}

/**
 * Summary of antanukas_validate_auth_token
 * @param string $authorization_data
 * @return WP_Error|null
 */
function antanukas_validate_auth_token($authorization_data)
{
    if (isset($authorization_data) && !empty($authorization_data)) {


        $jwks = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v3/certs'), true);
        $keys = JWK::parseKeySet($jwks);


        // Step 3: Decode & validate JWT
        try {
            $decoded = JWT::decode($authorization_data, $keys, ['RS256']);
            // Step 3: Decode & validate JWT);
            // Optional: validate issuer, audience, expiry
            if ($decoded->iss !== 'https://accounts.google.com' && $decoded->iss !== 'accounts.google.com') {
                throw new Exception('Invalid issuer');
            }

            // Success: you can now trust $decoded->sub, email, etc.
            error_log("Valid JWT from Google: " . $decoded->sub);

        } catch (Exception $e) {

            echo "Invalid token: " . $e->getMessage();

            error_log("Invalid token: " . $e->getMessage());
            return new WP_Error('Authentication required', 'Missing/invalid auth token', array('status' => 401));

        }
    } else {
        return new WP_Error('Authentication required', 'Missing/invalid auth token', array('status' => 401));
    }
}





