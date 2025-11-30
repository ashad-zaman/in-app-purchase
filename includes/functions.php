<?php
 


add_action('woocommerce_scheduled_subscription_payment', 'skip_external_subscription_renewals', 10, 2);

/**
 * Summary of skip_external_subscription_renewals
 * @param mixed $subscription_id
 * @return bool
 */
function skip_external_subscription_renewals( $subscription_id ) {

  // echo "subscription_id=>".$subscription_id;
    // Assuming you store platform as metadata
     $subscription = wcs_get_subscription( $subscription_id );

     if ( ! is_a( $subscription, 'WC_Subscription' ) ) return false; 
 
     if(in_array($subscription->get_payment_method(), ['ApplePay','GooglePay'])){
        $subscription_validator = new Subscription_Validator();
        $response = $subscription_validator->re_validate_receipt_token($subscription);

        if($response==false){

                $data_store                 = new Data_Store();
                $data_subs_iap              =   $data_store->fetch_data_by_subscription_Id($subscription_id);
                
                if(!$data_subs_iap){
                  
                  $id       = isset($data_subs_iap['id'])?$data_subs_iap['id']:'';
                  if($id){
                    $data_store->update_subscription_status( $id, 'failed');
                  }
                  
                }
        }else{
                $data_store                 = new Data_Store();
                $data_subs_iap              =   $data_store->fetch_data_by_subscription_Id($subscription_id);
                
                if(!$data_subs_iap){
                  $id       = isset($data_subs_iap['id'])?$data_subs_iap['id']:'';
                  if($id){
                    $data_store->update_subscription_status( $id, 'active');
                  }
                  
                }
        }

        return $response; 

     }
}     
 