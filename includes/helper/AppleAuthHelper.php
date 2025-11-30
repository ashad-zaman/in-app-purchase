<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AppleAuthHelper
{
    /**
     * Generate JWT token for Apple App Store Connect API
     *
     * @param string $issuerId   Your App Store Connect Issuer ID
     * @param string $keyId      Your App Store Connect API Key ID
     * @param string $privateKey Path to your .p8 private key file
     * @return string JWT Token
     * @throws Exception
     */
    public static function generateAppleJWT(): string
    {

         if(defined('INAPP_APPLE_AUTH_JSON'))
        {
            // $googleKeyPath = INAPP_APPLE_AUTH_JSON;
            $compression_service = new CompressionService();
 
            $compression_service_de = $compression_service->decompress( INAPP_APPLE_AUTH_JSON);

            $tmpFile = tmpfile();
            fwrite($tmpFile, $compression_service_de);

                // Get the file path
            $meta = stream_get_meta_data($tmpFile);
            $tmpFilePath = $meta['uri'];
             $appleKeyPath = $tmpFilePath ;
 
        }else{
            throw new \RuntimeException('Google service account path not defined');
        }
        // Load private key contents
        if (file_exists($appleKeyPath)) {
            $appleKeyPath=__DIR__ . '/AuthKey_BTXNL6J47Y.p8';
            $privateKey = file_get_contents($appleKeyPath);
        }

        if (!$privateKey) {
            throw new Exception("Private key not found or invalid.");
        }

        // JWT claims 
        $time = time();
        $claims = [
            "iss" => INAPP_APPLE_AUTH_ISSUER_ID,        // Issuer ID
            "iat" => $time,            // Issued at
            "exp" => $time + (20 * 60),// Expiration (max 20 minutes)
            "aud" => "appstoreconnect-v1"
        ];

        // Encode the JWT
        return JWT::encode($claims, $privateKey, 'ES256', INAPP_APPLE_AUTH_KEY_ID); 
    }
}
