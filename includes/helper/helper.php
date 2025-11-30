<?php
class Helper {
    public  static function convert_ms_to_date_time( $ms ){

        return DateTime::createFromFormat('U.v', number_format( $ms/1000, 3, '.', ''))->format("Y-m-d H:i:s");
    }

    public static function wpant_sortable_header($label, $col) {
        $current_orderby = $_GET['orderby'] ?? 'id';
        $current_order   = $_GET['order'] ?? 'desc';
        $new_order       = ($current_orderby === $col && $current_order === 'asc') ? 'desc' : 'asc';
        $url = add_query_arg(['orderby'=>$col,'order'=>$new_order]);
        $arrow = '';
        if ($current_orderby === $col) {
            $arrow = $current_order === 'asc' ? ' ▲' : ' ▼';
        }
        return '<a href="'.esc_url($url).'">'.esc_html($label).$arrow.'</a>';
    }

    public static function show_notices($page, $message, array $page_restricted)
    {
        if (!isset($page) || !in_array($page, $page_restricted)) {
            return;
        }
        if (!isset($message)) {
            return;
        }

        $msg = sanitize_text_field($message);
        $class = 'updated'; // green
        $text = '';

        switch ($msg) {
            case 'added':
                $text = 'Product has been <strong>added</strong> successfully.';
                break;
            case 'updated':
                $text = 'Product has been <strong>updated</strong> successfully.';
                break;
            case 'deleted':
                $text = 'Product has been <strong>deleted</strong> successfully.';
                $class = 'notice-warning'; // orange
                break;
        }

        if ($text) {
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . $text . '</p></div>';
        }
    }

    public static function convertMicrosToDecimal(int $micros): float {
        return round($micros / 1000000, 2);
    }


    public static function formate_data( $data ) {
    
        $data_response=[];
        $data_response=$data;

        $data_response['raw_response']=isset($data['raw_response'])?$data['raw_response']:[];

        $data_response['amount']='';
        $data_response['auto_renewing']='';
        $data_response['currency']='';
        $data_response['updated_at']=date('Y-m-d H:i:s');
        

        if($data['platform'] == 'ios' && $data['purchase_type'] == 'subscription') { 
    
            $data_response['product_id']                = isset($data['raw_response'])?$data['raw_response']['receipt']['in_app'][0]['product_id']:'';
            $data_response['transaction_id']            = isset($data['raw_response'])? $data['raw_response']['receipt']['in_app'][0]['transaction_id']:'';
            $data_response['original_transaction_id']   = isset($data['raw_response'])? $data['raw_response']['receipt']['in_app'][0]['original_transaction_id']:'';
            $data_response['purchase_id']               = isset($data['raw_response'])? $data['raw_response']['receipt']['in_app'][0]['original_transaction_id']:'';
            $data_response['start_time']                = isset($data['raw_response'])? Helper::convert_ms_to_date_time( $data['raw_response']['receipt']['in_app'][0]['purchase_date_ms'] ):'';
            $data_response['expiry_time']               = isset($data['raw_response'])? Helper::convert_ms_to_date_time( $data['raw_response']['receipt']['in_app'][0]['expires_date_ms'] ):'';
            $data_response['expires_date']              = isset($data['raw_response'])?Helper::convert_ms_to_date_time( $data['raw_response']['receipt']['in_app'][0]['expires_date_ms'] ):'';
            $data_response['bundle_id']                 = isset($data['raw_response'])?$data['raw_response']['receipt']['bundle_id']:'';  
            $data_response['last_payment']              = $data_response['start_time']; 
        }
        if($data['platform'] == 'android' && $data['purchase_type'] == 'subscription') {  
            $data_response['start_time']                = isset($data['raw_response'])? Helper::convert_ms_to_date_time(   $data['raw_response']['startTimeMillis'] ):'';
            $data_response['expiry_time']               = isset($data['raw_response'])? Helper::convert_ms_to_date_time(  $data['raw_response']['expiryTimeMillis'] ):'';
            $data_response['amount']                    = isset($data['raw_response'])?Helper::convertMicrosToDecimal($data['raw_response']['priceAmountMicros']):'';
            $data_response['currency']                  = isset($data['raw_response'])?$data['raw_response']['priceCurrencyCode']:'';
            $data_response['auto_renewing']             = isset($data['raw_response'])? $data['raw_response']['autoRenewing']:'';
            $data_response['purchase_id']               = $data_response['order_id'] = isset($data['raw_response'])?$data['raw_response']['raw_response']['orderId']:'';
            $today = date('Y-m-d');
            $startDate =  date('Y-m-d', strtotime($data_response['start_time']));
            $data_response['last_payment'] = $data_response['start_time'];
            if($startDate < $today ){
                $data_response['last_payment']            =   date('Y-m-d H:i:s', strtotime('-1 month', strtotime($data_response['expiry_time'])));
            }
            
        }

       if($data['platform'] == 'android' && $data['purchase_type'] == 'product') {  
            $data_response['purchase_time']             = isset($data['raw_response'])? Helper::convert_ms_to_date_time(   $data['raw_response']['rawResponse']['purchaseTimeMillis'] ):'';
            $data_response['used_time']                 = isset($data['raw_response'])? Helper::convert_ms_to_date_time(  $data['raw_response']['rawResponse']['purchaseTimeMillis'] ):null;
            $data_response['currency']                  = isset($data['raw_response'])?$data['raw_response']['priceCurrencyCode']:'';
            $data_response['amount']                    = isset($data['amount'])?$data['amount']:'';
            $data_response['currency']                  = isset($data['currency'])?$data['currency']:'';
            $data_response['wc_product_id']             = isset($data['wc_product_id'])?$data['wc_product_id']:''; 
            $data_response['wc_product_name']           = isset($data['wc_product_name'])?$data['wc_product_name']:'';  
            $data_response['wc_order_id']               = isset($data['wc_order_id'])? $data['wc_order_id']:'';
            $data_response['purchase_id']               = isset($data['raw_response']['orderId'])? $data['raw_response']['orderId']:'';
            $data_response['order_id']                  = $data_response['purchase_id'];
            
        }

        if($data['platform'] == 'ios' && $data['purchase_type'] == 'product') {  
            $data_response['purchase_time']             = isset($data['items'])? Helper::convert_ms_to_date_time(   $data['items']['purchaseDate'] ):'';
            $data_response['used_time']                 = isset($data['items'])? Helper::convert_ms_to_date_time(  $data['items']['purchaseDate'] ):null;
            $data_response['amount']                    = isset($data['amount'])?$data['amount']:'';
            $data_response['currency']                  = isset($data['currency'])?$data['currency']:'';
            $data_response['wc_product_id']             = isset($data['wc_product_id'])?$data['wc_product_id']:''; 
            $data_response['wc_product_name']           = isset($data['wc_product_name'])?$data['wc_product_name']:'';  
            $data_response['wc_order_id']               = isset($data['wc_order_id'])? $data['wc_order_id']:'';
            $data_response['purchase_id']               = isset($data['items']['transactionId'])? $data['items']['transactionId']:'';
            $data_response['original_transaction_id']   = isset($data['items']['transactionId'])? $data['items']['transactionId']:'';
            
            $data_response['order_id']                  = $data_response['purchase_id'];
            
        }
        
        return $data_response;

    }
    
}
?>