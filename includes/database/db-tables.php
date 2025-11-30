<?php
    function antanukaswpiap_activate() { 
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_wpiap_subscriptions = $wpdb->prefix . 'wpiap_subscriptions';   
        $checkSQL_for_wpiap_subscription = "show tables like '".$table_wpiap_subscriptions."'"; 
        if($wpdb->get_var($checkSQL_for_wpiap_subscription) != $table_wpiap_subscriptions)
        {
            $sql_wpiap_subscriptions = "CREATE TABLE   $table_wpiap_subscriptions  (
            id BIGINT(11) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(11)  NOT NULL,
            platform  ENUM('ios', 'android') NOT NULL,
            product_id VARCHAR(255) NOT NULL,
            purchase_id  VARCHAR(255) NOT NULL, /*Google Order ID or Apple Original Transaction ID*/
            wc_subscription_id BIGINT(11)  DEFAULT NULL,
            status ENUM('active', 'expired', 'cancelled', 'refunded', 'failed') DEFAULT 'active',
            start_time TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
            expiry_time TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00', 
            auto_renewing BOOLEAN DEFAULT NULL,
            amount DECIMAL(10, 2) DEFAULT NULL,
            currency VARCHAR(10) DEFAULT NULL, 
            raw_response JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_platform_purchase_id (platform, purchase_id),
            PRIMARY KEY  (id),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status)
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
  
            // we do not execute sql directly
            // we are calling dbDelta which cant migrate database
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_wpiap_subscriptions);
        }
        

        $table_wpiap_android_subscriptions = $wpdb->prefix . 'wpiap_android_subscriptions';   
        $checkSQL_for_wpiap_android_subscriptions = "show tables like '".$table_wpiap_android_subscriptions."'"; 

        if($wpdb->get_var($checkSQL_for_wpiap_android_subscriptions) != $table_wpiap_android_subscriptions)
        {
             $sql_wpiap_android_subscriptions = "CREATE TABLE   $table_wpiap_android_subscriptions  (
            subscription_id BIGINT(11) NOT NULL, 
            package_name VARCHAR(255) NOT NULL,
            order_id VARCHAR(255) NOT NULL,
            purchase_token TEXT NOT NULL,
            amount DECIMAL(10, 2) DEFAULT NULL,
            currency VARCHAR(10) DEFAULT NULL,
            raw_response TEXT DEFAULT NULL,
            UNIQUE KEY unique_order_id (order_id),
            UNIQUE KEY unique_token (purchase_token(100)),
            FOREIGN KEY (subscription_id) REFERENCES  wpant_wpiap_subscriptions(id) ON UPDATE CASCADE ON DELETE CASCADE
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
  
            // we do not execute sql directly
            // we are calling dbDelta which cant migrate database
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_wpiap_android_subscriptions);
        }

        $table_wpiap_ios_subscriptions = $wpdb->prefix . 'wpiap_ios_subscriptions';   
        $checkSQL_for_wpiap_ios_subscriptions = "show tables like '".$table_wpiap_ios_subscriptions."'"; 
        if($wpdb->get_var($checkSQL_for_wpiap_ios_subscriptions) != $table_wpiap_ios_subscriptions)
        {
            $sql_for_wpiap_ios_subscriptions = "CREATE TABLE   $table_wpiap_ios_subscriptions  (
            subscription_id BIGINT(11) NOT NULL, 
            receipt TEXT NOT NULL,
            original_transaction_id VARCHAR(255) NOT NULL,
            raw_response TEXT DEFAULT NULL,
            UNIQUE KEY unique_transaction (original_transaction_id),
            FOREIGN KEY (subscription_id) REFERENCES wpant_wpiap_subscriptions(id) ON UPDATE CASCADE ON DELETE CASCADE
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
  
            // we do not execute sql directly
            // we are calling dbDelta which cant migrate database
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_for_wpiap_ios_subscriptions);
        }
 

      }
      register_activation_hook( __FILE__, 'antanukaswpiap_activate' );

    //   function myplugin_deactivate() {
    //     global $wpdb;
    //     $table_name = $wpdb->prefix . 'myplugin_table';
    //     $sql = "DROP TABLE IF EXISTS $table_name"; // Use IF EXISTS
    //     $wpdb->query($sql); // Execute the query
    //   }
    //   register_deactivation_hook( __FILE__, 'myplugin_deactivate' );
 
 
 
    // Your plugin's main file
    register_uninstall_hook( __FILE__, 'antanukaswpiap_uninstall' );

    function antanukaswpiap_uninstall() {
        // Delete custom database tables or options here
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpiap_purchases");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpiap_subscriptions");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpiap_product_purchases");
       // delete_option('my_plugin_option');
    }

 
    function antanukaswpiap_product_activate() { 
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_wpiap_products = $wpdb->prefix . 'wpiap_products';   
        $checkSQL_for_wpiap_products = "show tables like '".$table_wpiap_products."'"; 
        if($wpdb->get_var($checkSQL_for_wpiap_products) != $table_wpiap_products)
        {
            $sql_wpiap_products = "CREATE TABLE   $table_wpiap_products  (
            id BIGINT(11) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(11)  NOT NULL,
            platform  ENUM('ios', 'android') NOT NULL,
            product_id VARCHAR(255) NOT NULL,
            purchase_id  VARCHAR(255) NOT NULL,  
            wc_order_id BIGINT(11)  DEFAULT NULL,
            status ENUM('available', 'used', 'refunded', 'failed', 'pending') DEFAULT 'available',
            book_id BIGINT(11) DEFAULT NULL,
            purchase_time TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',
            used_time TIMESTAMP DEFAULT '0000-00-00 00:00:00', 
            amount DECIMAL(10, 2) DEFAULT NULL,
            currency VARCHAR(10) DEFAULT NULL, 
            raw_response JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_platform_purchase_id (platform, purchase_id),
            PRIMARY KEY (id),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status)
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
  
            // we do not execute sql directly
            // we are calling dbDelta which cant migrate database
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_wpiap_products);
        }
        

        $table_wpiap_android_products = $wpdb->prefix . 'wpiap_android_products';   
        $checkSQL_for_wpiap_android_products = "show tables like '".$table_wpiap_android_products."'"; 

        if($wpdb->get_var($checkSQL_for_wpiap_android_products) != $table_wpiap_android_products)
        {
             $sql_wpiap_android_prouducts = "CREATE TABLE   $table_wpiap_android_products  (
            product_id BIGINT(11) NOT NULL, 
            package_name VARCHAR(255) NOT NULL,
            order_id VARCHAR(255) NOT NULL,
            purchase_token TEXT NOT NULL,
            raw_response TEXT DEFAULT NULL,
            UNIQUE KEY unique_order_id (order_id),
            UNIQUE KEY unique_token (purchase_token(100)),
            KEY product_id (product_id),
            CONSTRAINT wpant_wpiap_android_products_ibfk_1 FOREIGN KEY (product_id) REFERENCES  wpant_wpiap_products(id) ON UPDATE CASCADE ON DELETE CASCADE
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
  
            // we do not execute sql directly
            // we are calling dbDelta which cant migrate database
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_wpiap_android_prouducts);
        }

        $table_wpiap_ios_products = $wpdb->prefix . 'wpiap_ios_products';   
        $checkSQL_for_wpiap_ios_products = "show tables like '".$table_wpiap_ios_products."'"; 
        if($wpdb->get_var($checkSQL_for_wpiap_ios_products) != $table_wpiap_ios_products)
        {
            $sql_for_wpiap_ios_subscriptions = "CREATE TABLE   $table_wpiap_ios_products  (
            product_id BIGINT(11) NOT NULL, 
            receipt TEXT NOT NULL,
            original_transaction_id VARCHAR(255) NOT NULL,
            raw_response TEXT DEFAULT NULL,
            UNIQUE KEY unique_transaction (original_transaction_id),
            KEY product_id (product_id),
            CONSTRAINT wpant_wpiap_ios_products_ibfk_1 FOREIGN KEY (product_id) REFERENCES wpant_wpiap_products(id) ON UPDATE CASCADE ON DELETE CASCADE
            )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
  
            // we do not execute sql directly
            // we are calling dbDelta which cant migrate database
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($table_wpiap_ios_products);
        }
 

      }
      register_activation_hook( __FILE__, 'antanukaswpiap_product_activate' );

    //   function myplugin_deactivate() {
    //     global $wpdb;
    //     $table_name = $wpdb->prefix . 'myplugin_table';
    //     $sql = "DROP TABLE IF EXISTS $table_name"; // Use IF EXISTS
    //     $wpdb->query($sql); // Execute the query
    //   }
    //   register_deactivation_hook( __FILE__, 'myplugin_deactivate' );
 
 
 
    // Your plugin's main file
    register_uninstall_hook( __FILE__, 'antanukaswpiap_product_uninstall' );

    function antanukaswpiap_product_uninstall() {
        // Delete custom database tables or options here
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpiap_products");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpiap_android_products");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpiap_ios_products");
       // delete_option('my_plugin_option');
    }