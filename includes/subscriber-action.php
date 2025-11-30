<?php

require_once('constant/new-subscribers-constant.php');

class Subscriber_Action
{

    /**
     * Summary of get_active_subscribers_ids
     */
    private function get_active_subscribers_ids()
    {
        global $wpdb;

        // Return an array of user Ids active subscribers
        return $wpdb->get_results("SELECT DISTINCT pm.post_id as subscriber_id, pm2.meta_value as next_payment_date
            FROM {$wpdb->prefix}posts as p
            JOIN {$wpdb->prefix}postmeta as pm
                ON p.ID = pm.post_id
            JOIN {$wpdb->prefix}postmeta as pm2
                ON p.ID = pm2.post_id    
            WHERE p.post_type = 'shop_subscription'
            AND p.post_status = 'wc-active'
            AND pm.meta_key = '_customer_user'
            AND pm2.meta_key = '_schedule_next_payment' 
            AND pm2.meta_value !=0

            -- AND pm2.meta_value >=CONCAT(CURDATE(), ' 09:00:00')
            -- AND pm2.meta_value<=CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 08:59:59')

            AND pm2.meta_value >= NOW()
		    AND pm2.meta_value <= DATE_ADD(NOW(), INTERVAL 1 DAY)

            order by next_payment_date asc ");
    }

    /**
     * Summary of create_new_order
     * @param mixed $productId
     * @param mixed $paymentInfo
     * @param mixed $user_id
     * @param mixed $note
     * @return bool|WC_Order|WP_Error
     */
    public function create_new_order($productId, $user_id, $paymentInfo, $note = '')
    {


        // $productId=NewMonthlySub;


        if (GetDebugData) {
            echo 'New Purchase Pakcage ID(variation ID)=' . $productId . PHP_EOL;
        }

        $product = wc_get_product($productId);

        if (empty($product) || !isset($product)) {
            return false;
        }

        // First make sure all required functions and classes exist
        if (!function_exists('wc_create_order')) {
            return false;
        }

        if (GetDebugData) {
            echo 'Starting to create new order ' . PHP_EOL;
        }


        $order = wc_create_order(array('customer_id' => $user_id));

        if (is_wp_error($order)) {
            return false;
        }
        if (GetDebugData) {
            echo 'New Purchase orderID=' . $order->get_id() . PHP_EOL;
        }

        $user = get_user_by('ID', $user_id);

        $fname = $user->first_name;
        $lname = $user->last_name;
        $email = $user->user_email;

        if (GetDebugData) {
            echo 'User Email=' . $email . PHP_EOL;
        }

        $address_1 = get_user_meta($user_id, 'billing_address_1', true);
        $address_2 = get_user_meta($user_id, 'billing_address_2', true);
        $city = get_user_meta($user_id, 'billing_city', true);
        $postcode = get_user_meta($user_id, 'billing_postcode', true);
        $country = get_user_meta($user_id, 'billing_country', true);
        $state = get_user_meta($user_id, 'billing_state', true);
        $phone = get_user_meta($user_id, 'billing_phone', true);

        $address = array(
            'first_name' => $fname,
            'last_name' => $lname,
            'email' => $email,
            'address_1' => $address_1,
            'address_2' => $address_2,
            'city' => $city,
            'state' => $state,
            'postcode' => $postcode,
            'country' => $country,
            'phone' => $phone,
        );

        // echo 'address='.$email . PHP_EOL;
        //print_r( $address );


        // sleep(5);
        $order->set_address($address, 'billing');
        //  $order_id=$order->get_id();

        // $order->set_address( $address, 'shipping' );
        $order->add_product($product, 1);

        $order->calculate_totals();




        // Update order status with custom note
        $note = !empty($note) ? $note : __('Programmatically added Purchase order.');
        $order->update_status('completed', $note, true);
        $order->set_date_paid($paymentInfo['date_paid']);

        $order->set_date_created($paymentInfo['start_date']);

        $order->set_payment_method($paymentInfo['payment_method']);
        $order->set_payment_method_title($paymentInfo['payment_title']);

        $order->save();

        // Also update subscription status to active from pending (and add note)


        //  $stripe_cust_id = $curSub->get_meta('_stripe_customer_id');
        // $stripe_src_id  = $curSub->get_meta('_stripe_source_id');

        // echo "\n stripe_cust_id=>".$stripe_cust_id. PHP_EOL;
        // echo "\n stripe_src_id=>".$stripe_src_id. PHP_EOL;

        //$sub->update_meta_data('_stripe_customer_id',$stripe_cust_id);
        //$sub->update_meta_data('_stripe_source_id', $stripe_src_id);


        return $order;
    }


    /**
     * Summary of create_new_subscription
     * @param mixed $productId
     * @param mixed $curSub
     * @param mixed $paymentInfo
     * @param mixed $pa_duration
     * @param mixed $user_id
     * @param mixed $action_type
     * @param mixed $note
     */
    private function create_new_subscription($productId, $curSub, $paymentInfo, $pa_duration, $user_id, $action_type, $note = '')
    {


        $productId = NewMonthlySub;


        if (GetDebugData) {
            echo 'New Subscription Pakcage ID(variation ID)=' . $productId . PHP_EOL;
        }

        $product = wc_get_product($productId);

        if (empty($product) || !isset($product)) {
            return false;
        }

        // First make sure all required functions and classes exist
        if (!function_exists('wc_create_order') || !function_exists('wcs_create_subscription') || !class_exists('WC_Subscriptions_Product')) {
            return false;
        }

        if (GetDebugData) {
            echo 'Starting to create new order ' . PHP_EOL;
        }


        $order = wc_create_order(array('customer_id' => $user_id));

        if (is_wp_error($order)) {
            return false;
        }
        if (GetDebugData) {
            echo 'New Subscription orderID=' . $order->get_id() . PHP_EOL;
        }

        $user = get_user_by('ID', $user_id);

        $fname = $user->first_name;
        $lname = $user->last_name;
        $email = $user->user_email;

        if (GetDebugData) {

            echo 'User Email=' . $email . PHP_EOL;
        }

        if (empty($curSub)) {
            $fname = $fname;
            $lname = $lname;
            $address_1 = get_user_meta($user_id, 'billing_address_1', true);
            $address_2 = get_user_meta($user_id, 'billing_address_2', true);
            $city = get_user_meta($user_id, 'billing_city', true);
            $postcode = get_user_meta($user_id, 'billing_postcode', true);
            $country = get_user_meta($user_id, 'billing_country', true);
            $state = get_user_meta($user_id, 'billing_state', true);
            $phone = get_user_meta($user_id, 'billing_phone', true);
        } else {

            if (empty($fname))
                $fname = $curSub->get_billing_first_name();
            if (empty($lname))
                $lname = $curSub->get_billing_last_name();

            $address_1 = $curSub->get_billing_address_1();
            if (empty($address_1))
                $address_1 = get_user_meta($user_id, 'billing_address_1', true);

            $address_2 = $curSub->get_billing_address_2();
            if (empty($address_2))
                $address_2 = get_user_meta($user_id, 'billing_address_2', true);

            $city = $curSub->get_billing_city();
            if (empty($city))
                $city = get_user_meta($user_id, 'billing_city', true);

            $postcode = $curSub->get_billing_postcode();
            if (empty($postcode))
                $postcode = get_user_meta($user_id, 'billing_postcode', true);

            $country = $curSub->get_billing_country();
            if (empty($country))
                $country = get_user_meta($user_id, 'billing_country', true);

            $state = $curSub->get_billing_state();
            if (empty($state))
                $state = get_user_meta($user_id, 'billing_state', true);

            $phone = $curSub->get_billing_phone();
            if (empty($phone))
                $phone = get_user_meta($user_id, 'billing_phone', true);
        }




        $address = array(
            'first_name' => $fname,
            'last_name' => $lname,
            'email' => $email,
            'address_1' => $address_1,
            'address_2' => $address_2,
            'city' => $city,
            'state' => $state,
            'postcode' => $postcode,
            'country' => $country,
            'phone' => $phone,
        );

        // echo 'address='.$email . PHP_EOL;
        //print_r( $address );


        // sleep(5);
        $order->set_address($address, 'billing');
        //  $order_id=$order->get_id();

        // $order->set_address( $address, 'shipping' );
        $order->add_product($product, 1);

        $order->calculate_totals();

        // echo "\n Product Data". PHP_EOL;
        // print_r( $product);
        if (!empty($curSub)) {
            $_billing_period = get_post_meta($curSub->get_id(), "_billing_period", true);
            $_billing_interval = get_post_meta($curSub->get_id(), "_billing_interval", true);

            // echo "\n _billing_period=".$_billing_period .PHP_EOL;
            //  echo "\n _billing_interval=".$_billing_interval .PHP_EOL;

            // echo "\n _billing_period fun =".WC_Subscriptions_Product::get_period( $product ).PHP_EOL;
            // echo "\n _billing_interval fun=".WC_Subscriptions_Product::get_interval( $product ).PHP_EOL;
        }



        $newSub_data = array(
            'order_id' => $order->get_id(),
            'status' => 'pending', // Status should be initially set to pending to match how normal checkout process goes
            'billing_period' => WC_Subscriptions_Product::get_period($product),
            'billing_interval' => WC_Subscriptions_Product::get_interval($product)
        );

        // echo "\n New subscription Data". PHP_EOL;

        // print_r( $newSub_data);



        $sub = wcs_create_subscription($newSub_data);



        if (is_wp_error($sub)) {

            return false;
        }

        if (GetDebugData) {
            echo 'New subscription created=' . PHP_EOL;
            echo 'New subscription ID=' . $sub->get_id() . PHP_EOL;
        }

        $sub->set_billing_first_name($address['first_name']);
        $sub->set_billing_last_name($address['last_name']);
        $sub->set_billing_email($address['email']);
        $sub->set_billing_address_1($address['address_1']);
        $sub->set_billing_address_2($address['address_2']);
        $sub->set_billing_city($address['city']);
        $sub->set_billing_state($address['state']);
        $sub->set_billing_postcode($address['postcode']);
        $sub->set_billing_country($address['country']);
        $sub->set_billing_phone($address['phone']);



        // $new_sub_id=$sub->get_id();
        // foreach ( $address as $key => $value ) {
        //     echo "order_id=>".$new_sub_id." "."_billing_" . $key. PHP_EOL;

        //     update_post_meta($new_sub_id, "_billing_" . $key, $value );
        // }


        // Modeled after WC_Subscriptions_Cart::calculate_subscription_totals()
        // $start_date = gmdate( 'Y-m-d H:i:s' );
        // Add product to subscription
        $sub->add_product($product, 1);

        $expire_date = date('Y-m-d H:i:s', strtotime($paymentInfo['expires_date']));
        $tomorrow_date = date('Y-m-d H:i:s', strtotime('+1 day'));
        if ($expire_date < $tomorrow_date) {
            $expire_date = $tomorrow_date;
        }

        $next_payment = array(
            'next_payment' => $expire_date
        );


        // print_r($new_dates);
        // $sub->set_date_created($paymentInfo['start_date'] );

        try {

            $sub->update_dates($next_payment);

            $sub->save();

        } catch (\Exception $e) {
            //echo  'Error: ' . $e->getMessage(). PHP_EOL;
        }


        /* Updating dates for new subscription */

        $sub->calculate_totals();

        $orderIdwithLink = "<a href='/wp-admin/post.php?post='" . $order->get_id() . "'&action=edit'>#" . $order->get_id() . "</a>";

        $sub->add_order_note("Programatically Order  $orderIdwithLink created to record.");
        // $sub->add_order_note($note);

        // Update order status with custom note
        $note = !empty($note) ? $note : __('Programmatically added order  for subscription.');
        $order->update_status('completed', $note, true);
        $order->set_date_paid($paymentInfo['date_paid']);

        $order->set_date_created($paymentInfo['start_date']);

        $order->set_payment_method($paymentInfo['payment_method']);
        $order->set_payment_method_title($paymentInfo['payment_title']);

        $order->save();

        // Also update subscription status to active from pending (and add note)
        $sub->update_status('active', $note, true);
        //$payment_gateways = WC()->payment_gateways->payment_gateways(); 

        $sub->update_meta_data('_payment_system', 'iap');

        $sub->set_payment_method($paymentInfo['payment_method']);
        $sub->set_payment_method_title($paymentInfo['payment_title']);

        //  $stripe_cust_id = $curSub->get_meta('_stripe_customer_id');
        // $stripe_src_id  = $curSub->get_meta('_stripe_source_id');

        // echo "\n stripe_cust_id=>".$stripe_cust_id. PHP_EOL;
        // echo "\n stripe_src_id=>".$stripe_src_id. PHP_EOL;

        //$sub->update_meta_data('_stripe_customer_id',$stripe_cust_id);
        //$sub->update_meta_data('_stripe_source_id', $stripe_src_id);


        $sub->save();

        return $sub;
    }


    /**
     * Summary of create_subscription_for_user
     * @param mixed $p_user_id
     * @param mixed $p_productId
     * @param mixed $p_paymentInfo
     * @param mixed $data_response
     */
    public function create_subscription_for_user($p_user_id, $p_productId, $p_paymentInfo, $data_response)
    {


        $subscription_ids = [];
        $subId = '';
        $isActive = false;
        if (wcs_user_has_subscription($p_user_id)) {

            $users_subscriptions = wcs_get_users_subscriptions($p_user_id);

            // print_r( $users_subscriptions);

            foreach ($users_subscriptions as $subscription) {

                //if ($subscription->has_status(array('active'))) {
                $subscription_ids['subscriber_id'] = $subscription->get_id();
                $isActive = true;
                //}
                //$subId=$subscription->get_id(); 
                break;
            }
            //   if($isActive==false){
            //     $subscription_ids['subscriber_id']=$subId; 
            //   }
            $subscription_ids = (object) $subscription_ids;
        }


        try {

            if ($subscription_ids) {
                // print_r($subscription_ids->subscriber_id);exit;
                // foreach($subscription_ids as $key=>$subscription){

                $subscription_id = $subscription_ids->subscriber_id;

                // $subscription_next_payment_date=$subscription->next_payment_date; 
                $subscription = wcs_get_subscription($subscription_id);
                $status = $subscription->get_status();

                $pa_duration = '';

                /* process only for activde subscribers */
                if (in_array($status, ['active', 'on-hold', 'pending-cancel'])) {  //'active','on-hold','pending-cancel'

                    if (in_array($status, ['active', 'pending-cancel'])) {
                        return $subscription;
                    } else {
                        return $this->update_subscription_with_new_order($subscription_id, '', $p_paymentInfo, $data_response);
                    }
                    //  $this->create_new_subscription($p_productId, $subscription,$p_paymentInfo, $pa_duration, $p_user_id, 'update', 'Programatically Creating new subscription' );

                } else {

                    $next_payment_date = $subscription->get_date('next_payment');
                    $line_items = $subscription->get_items();

                    $newSub = $this->create_new_subscription($p_productId, $subscription, $p_paymentInfo, $pa_duration, $p_user_id, 'new', 'Programatically Creating new subscription');

                    /*Cancel previous Active subscription if new subscription created successfully*/
                    if ($newSub !== false) {

                        if ($newSub->get_id() !== null && $newSub->get_status() == 'active') {
                            if ($status == 'active') {
                                $subscription->set_status('cancelled', 'Migrating to new subscription', true);
                                $subscription->save();
                            }
                        }

                    } else {
                        if (GetDebugData) {
                            echo 'Failed to create subscription=' . PHP_EOL;
                        }
                    }
                    if (GetDebugData) {
                        echo 'Finish creating subscription=' . PHP_EOL;
                    }

                }

                // sleep(10);
                // }
            } else {
                $newSub = $this->create_new_subscription($p_productId, null, $p_paymentInfo, '', $p_user_id, 'new', '');
                if (GetDebugData) {
                    if ($newSub !== false) {
                        echo 'Finish creating subscription' . PHP_EOL;
                    } else {
                        echo 'Failed to create subscription=' . PHP_EOL;
                    }
                }
            }

            return $newSub;


        } catch (Exception $e) {
            $message = "";
            $subject = "Exception Caught for IAP WC subscription:\n";
            $message .= "Message: " . $e->getMessage() . "\n";
            $message .= "Code: " . $e->getCode() . "\n";
            $message .= "File: " . $e->getFile() . "\n";
            $message .= "Line: " . $e->getLine() . "\n";
            $message .= "Trace:\n" . $e->getTraceAsString();
            echo $subject;
            echo $message;
            $to = get_option('admin_email'); // not sure how get our emails properly, but better not hardcode them
            $headers = array('Content-Type: text/plain; charset=UTF-8');
            wp_mail($to, $subject, $message, $headers); // just googled, not sure if such sending will work
        }

        return false;
    }

    /**
     * Summary of update_subscription_with_new_order
     * @param mixed $subscription_id
     * @param mixed $order
     * @param mixed $paymentInfo
     * @param mixed $data_response
     */
    private function update_subscription_with_new_order($subscription_id, $order, $paymentInfo, $data_response)
    {

        // $subscription_id = $subscription->get_id();

        // Get the subscription object
        $subscription = wcs_get_subscription($subscription_id);

        if (!$subscription) {
            return new WP_Error('invalid_subscription', 'Invalid subscription ID.');
        }

        if (isset($data_response['notification_type_check']) && $data_response['notification_type_check'] == 'cancel') {

            $note = !empty($note) ? $note : __('Programmatically cancelled subscription due to failed to renew (web-hook).');

            if (in_array($subscription->get_status(), ['active', 'on-hold', 'pending-cancel'])) {
                $subscription->update_status('cancelled', $note, true);
                $subscription->save();
            }



        } else {

            if ($subscription->get_status() == 'active') {
                $subscription->update_status('on-hold', 'Subscription renewal payment due:');
            }

            $renewal_order = wcs_create_renewal_order($subscription);

            if (is_wp_error($renewal_order)) {
                return new WP_Error('invalid_order', 'Order is invalid or not paid.');
            }
            $note = __('Programmatically added renawal order  for subscription.');

            $renewal_order->update_status('completed', $note, true);

            $renewal_order->set_date_paid($paymentInfo['date_paid']);

            $renewal_order->set_date_created($paymentInfo['start_date']);

            $renewal_order->set_payment_method($paymentInfo['payment_method']);
            $renewal_order->set_payment_method_title($paymentInfo['payment_title']);

            $renewal_order->save();
            // Link the new order to the subscription
            $subscription->add_order_note("Renewal order #{$renewal_order->get_id()} created programmatically.");

            $subscription->update_meta_data('_payment_system', 'iap');

            // $subscription->update_status( 'active' );


            // $next_payment=date('Y-m-d H:i:s', strtotime($subscription->get_date('next_payment'). ' + 30 days'));
            $expire_date = date('Y-m-d H:i:s', strtotime($paymentInfo['expires_date']));

            $next_payment = array(
                'next_payment' => $expire_date
            );

            try {

                $subscription->update_dates($next_payment);

            } catch (\Exception $e) {
                echo 'Error: ' . $e->getMessage() . PHP_EOL;
            }


            $note = !empty($note) ? $note : __('Programmatically added order  for subscription.');

            $subscription->update_status('active', $note, true);


            $subscription->set_payment_method($paymentInfo['payment_method']);
            $subscription->set_payment_method_title($paymentInfo['payment_title']);

            // Save the changes
            $subscription->save();
        }
        // $order->save();

        return $subscription;
    }

    /**
     * Summary of get_subscription_details
     * @param mixed $userId
     * @return array{dates: array{cancelled: mixed, end_date: mixed, last_payment_date: mixed, next_payment_date: mixed, start_date: mixed, trial_end_date: mixed, last_payment_data: array{id: mixed, price: mixed, tax: mixed}, payment_method: mixed, payment_method_title: mixed, periode: mixed, price: mixed, status: mixed, subscription_product_name: mixed, subscription_type: string, title: mixed}|array{dates: array{cancelled: mixed, end_date: mixed, last_payment_date: mixed, next_payment_date: mixed, start_date: mixed, trial_end_date: mixed}, last_payment_data: array{id: mixed, price: mixed, tax: mixed}, payment_method: mixed, payment_method_title: mixed, periode: string, price: mixed, status: mixed, subscription_product_name: string, subscription_type: string, title: mixed}|bool}
     */
    public function get_subscription_details($userId)
    {

        // $userInfo = get_userdata($userId);

        $subscription = false;

        if (wcs_user_has_subscription($userId, '', array('active', 'pending-cancel'))) {
            $userSubscriptions = wcs_get_users_subscriptions($userId);
            $userSubscription = reset($userSubscriptions);
            foreach ($userSubscriptions as $subscriptionItem) {
                if ($subscriptionItem->has_status(array('active'))) {
                    $userSubscription = $subscriptionItem;
                }
            }

            //
            $userSubscriptionData = $userSubscription->get_data();
            $userSubscriptionItem = new WC_Order_Item_Product(key($userSubscription->get_items()));
            //  print_r($userSubscriptionItem);
            $variation = $variationExist = wc_get_product($userSubscriptionItem->get_variation_id());
            $subscription_product_name = '';
            $subscription_type = 'text';

            //  print_r($variation->get_parent_id());

            if (!$variation) {
                $variation = wc_get_product($userSubscriptionItem->get_product_id());
                $title = 'Prenumerata';
            }
            $duration = '';
            if ($variationExist) {
                $subscription_product_name = $variation->get_name();
                // $subscription_product_name1=$variation->get_slug();
                // $subscription_product_name2=$variation->get_attributes();
                //  print_r($subscription_product_name1);
                //  print_r($subscription_product_name2);
                $variable = $variation->get_variation_attributes();
                foreach ($variable as $pa_key => $item) {
                    $value = $item;
                }
                //                echo 'variable=>';
                //print_r($variable);
                $duration = isset($variable['attribute_pa_duration']) ? $variable['attribute_pa_duration'] : '';
                //                echo 'value=>';
//                print_r($value);
                if (strpos($value, '-advance') !== false) {
                    $subscription_type = 'advance';
                } else if (strpos($value, '-audio') !== false) {
                    $subscription_type = 'audio';
                } else {
                    $subscription_type = 'text';
                }
            }

            //            if($variation->get_parent_id()){
//                $subscription_type=get_post_meta( $variation->get_parent_id(), '_plan_category', true );
//            }

            $priceString = $variation->get_price() . ' Eur';
            if ($variation->get_data()['attributes']['pa_duration'] == '1-men') {
                $priceString = $variation->get_price() . ' Eur / mėn';
            }

            if ($variation->get_attribute('pa_duration')) {
                $title = $variation->get_attribute('pa_duration');
            }
            // print_r($userSubscriptionData);
            $subscription = [
                'title' => $title,
                'subscription_product_name' => $subscription_product_name,
                'periode' => $duration,
                'subscription_type' => $subscription_type,
                //                'price' => $priceString,
                'price' => $userSubscriptionData['total'],
                'status' => isset($userSubscriptionData['status']) ? $userSubscriptionData['status'] : '',
                'dates' => [
                    'start_date' => $userSubscription->get_date('start_date'),
                    'end_date' => $userSubscription->get_date('end_date'),
                    'trial_end_date' => $userSubscription->get_date('trial_end_date'),
                    'next_payment_date' => $userSubscription->get_date('next_payment_date'),
                    'cancelled' => $userSubscription->get_date('cancelled'),
                    'last_payment_date' => $userSubscription->get_date('last_payment_date'),
                ],
                'payment_method' => $userSubscriptionData['payment_method'],
                'payment_method_title' => $userSubscriptionData['payment_method_title'],
                'last_payment_data' => [
                    'id' => $userSubscriptionData['id'],
                    'price' => $userSubscriptionData['total'],
                    'tax' => $userSubscriptionData['total_tax']
                ]
            ];
        }

        return $subscription;
    }

}


?>