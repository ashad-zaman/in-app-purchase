<?php 
class Iap_Products {

    const SUBSCRIPTION_MONTHLY = "lt.antanukas.subscription.monthly";
    public static function get_display_names(): array
    {
        return [
            self::SUBSCRIPTION_MONTHLY => 'Elektroninių ir audio knygų mėnesio prenumerata'
        ];
    }
}