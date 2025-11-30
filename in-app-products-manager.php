<?php


require_once __DIR__ . '/vendor/autoload.php';

use Google\Client as Google_Client;
use Google\Service\AndroidPublisher as Google_Service_AndroidPublisher;
use Google\Service\AndroidPublisher\InAppProduct as Google_Service_AndroidPublisher_InAppProduct;
use Google\Service\AndroidPublisher\BatchGetInAppProductsRequest;
use Google\Service\AndroidPublisher\BatchUpdateInAppProductsRequest;
use Firebase\JWT\JWT;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;

use ReceiptValidator\iTunes\Validator as ITunesValidator;
use ReceiptValidator\GooglePlay\ProductAccess as GooglePlayProductAccess;

/**
 * Manager for in-app products across different app stores.
 */
class In_APP_Products_Manager
{
    /* ---------------- PROPERTIES ---------------- */
    private ITunesValidator $appleValidator;
    private GooglePlayProductAccess $googleProductAccess;

    private string $package_name;
    private string $appId;

    /**
     * Constructor initializes the product access services for Apple and Google.
     *
     * @throws \RuntimeException If configuration is missing or invalid.
     */
    public function __construct()
    {


        $this->package_name = 'lt.antanukas.reader';
        $this->appId = 'lt.antanukas.reader';

        // Google configuration

        if (defined('INAPP_GOOGLE_SERVICE_ACCOUNT_JSON')) {
            // $googleKeyPath = INAPP_GOOGLE_SERVICE_ACCOUNT_JSON;
            $compression_service = new CompressionService();
            $compression_service_de = $compression_service->decompress(INAPP_GOOGLE_SERVICE_ACCOUNT_JSON);

            $tmpFile = tmpfile();
            fwrite($tmpFile, $compression_service_de);

            // Get the file path
            $meta = stream_get_meta_data($tmpFile);
            $tmpFilePath = $meta['uri'];
            $googleKeyPath = $tmpFilePath;

        } else {
            throw new \RuntimeException('Google service account path not defined');
        }

        $googleClient = new Google_Client();
        $googleClient->setAuthConfig($googleKeyPath);
        $googleClient->addScope(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);
        // fclose($temp_pointer);


        $googleService = new Google_Service_AndroidPublisher($googleClient);
        $this->googleProductAccess = new GooglePlayProductAccess($googleService);

        fclose($tmpFile);
    }


    /**
     * Summary of handle
     * @param string $store
     * @param string $action
     * @param array $params
     * @throws Exception
     * @return array{data: mixed, error: null, success: bool|array{data: null, error: string, success: bool}|array{data: ReceiptValidator\GooglePlay\ProductResponse, error: null, success: bool}}
     */
    public function handle(string $store, string $action, array $params = [])
    {
        try {
            if ($store === 'google') {
                $data = $this->googleHandler($action, $params);
            } elseif ($store === 'apple') {
                $data = $this->appleHandler($action, $params);
            } else {
                throw new Exception("Unsupported store: $store");
            }

            return [
                'success' => true,
                'data' => $data,
                'error' => null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Summary of googleHandler
     * @param string $action
     * @param array $params
     * @throws Exception
     * @return ReceiptValidator\GooglePlay\ProductResponse
     */
    private function googleHandler(string $action, array $params)
    {

        // $package = $config['package_name'];
        $package = $this->package_name;
        $iap_array_data = [
            'packageName' => $package,
            'sku' => $params['sku'],
            'status' => 'active',
            'defaultLanguage' => $params['language'] ?? 'en-US',
            'purchaseType' => $params['purchaseType'] ?? 'managedUser',
            'defaultPrice' => [
                'priceMicros' => bcmul((string) $params['price'], '1000000', 0),
                'currency' => $params['currency']

            ],

            'listings' => [
                $params['language'] ?? 'en-US' => [
                    'title' => $params['title'],
                    'description' => $params['description']
                ]
            ]
        ];

        $iap = new Google_Service_AndroidPublisher_InAppProduct($iap_array_data);



        switch ($action) {
            case 'insert':
                return $this->googleProductAccess
                    ->setPackageName($package)
                    ->setInAppProduct($iap)
                    ->setOptParams(["autoConvertMissingPrices" => $params['autoConvertMissingPrices']])
                    ->addInAppProduct();

            case 'get':
                return $this->googleProductAccess
                    ->setPackageName($package)
                    ->setSku($params['sku'])
                    ->getInAppProducts();

            case 'list':
                return $this->googleProductAccess
                    ->setPackageName($package)
                    ->getInAppProductList();

            case 'update':
                return $this->googleProductAccess
                    ->setPackageName($package)
                    ->setSku($params['sku'])
                    ->setInAppProduct($iap)
                    ->setOptParams(["autoConvertMissingPrices" => $params['autoConvertMissingPrices']])
                    ->updateInAppProduct();

            case 'patch':
                return $this->googleProductAccess
                    ->setPackageName($package)
                    ->setSku($params['sku'])
                    ->setInAppProduct($iap)
                    ->patchInAppProduct();

            case 'delete':
                return $this->googleProductAccess
                    ->setPackageName($package)
                    ->setSku($params['sku'])
                    ->deleteInAppProduct();

            case 'batchget':
                // $request = new Google_Service_AndroidPublisher_InappproductsBatchGetResponse([
                //     'names' => array_map(fn($sku) => "applications/$package/inappproducts/$sku", $params['skus'])
                // ]);
                return $this->googleProductAccess
                    ->setPackageName($package)
                    ->setOptParams($params['skus'])
                    ->batchGetInAppProducts();

            case 'batchupdate':
                $iapRequests = [];
                foreach ($params['products'] as $product) {
                    $iapRequests[] = new Google_Service_AndroidPublisher_InAppProduct($product);
                }
                $request = new Google_Service_AndroidPublisher_InappproductsBatchUpdateRequest([
                    'inappproducts' => $iapRequests
                ]);
                return $this->googleProductAccess
                    ->setPackageName($package)
                    ->setOptParams($params['skus'])
                    ->setInappproductsBatchUpdateRequest($request)
                    ->batchUpdateInAppProduct();

            default:
                throw new Exception("Unsupported Google action: $action");
        }
    }

    /* ---------------- APPLE ---------------- */

    /**
     * Summary of appleClient
     * @return GuzzleHttpClient
     */
    private static function appleClient()
    {

        $jwtToken = AppleAuthHelper::generateAppleJWT();

        return new GuzzleHttpClient([
            'base_uri' => 'https://api.appstoreconnect.apple.com/',
            'headers' => [
                'Authorization' => 'Bearer ' . $jwtToken,
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    // Set IAP price via price schedule

    /**
     * Summary of setPrice
     * @param mixed $iapId
     * @param mixed $tierCode
     * @return Psr\Http\Message\ResponseInterface
     */
    public function setPrice($iapId, $tierCode)
    {

        $client = self::appleClient();
        $body = [
            "data" => [
                "type" => "inAppPurchasePriceSchedules",
                "relationships" => [
                    "inAppPurchase" => [
                        "data" => [
                            "type" => "inAppPurchases",
                            "id" => $iapId
                        ]
                    ],
                    "manualPrices" => [
                        "data" => [
                            [
                                "type" => "inAppPurchaseManualPrices",
                                "id" => $tierCode
                            ]
                        ]
                    ]
                ]
            ]
        ];
        return $client->POST("inAppPurchasePriceSchedules", $body);
    }

    /**
     * Summary of appleHandler
     * @param string $action
     * @param array $params
     * @throws Exception
     */
    private static function appleHandler(string $action, array $params)
    {
        $client = self::appleClient();

        $iapId = [
            'json' => [
                'data' => [
                    'type' => 'inAppPurchases',
                    'attributes' => [
                        "locale" => "en-US",
                        'name' => $params['title'],
                        'productId' => $params['productId'],
                        'inAppPurchaseType' => $params['type'] ?? 'NON_CONSUMABLE',
                        "reviewNote" => "This is a book map for helping to find awesome book in app ",
                    ],
                    "relationships" => [
                        "app" => [
                            "data" => [
                                "type" => "apps",
                                "id" => $params['appId']
                            ]
                        ]
                    ]

                ]
            ]
        ];
        $iapLocalization = [
            'json' => [
                'data' => [
                    'type' => 'inAppPurchaseLocalizations',
                    'attributes' => [
                        "locale" => "en-US",
                        'name' => $params['title'],
                        "description" => "39 testo knyga įterpimui",
                    ],
                    "relationships" => [
                        "inAppPurchaseV2" => [
                            "data" => [
                                "type" => "inAppPurchases",
                                "id" => $params['appId']
                            ]
                        ]
                    ]

                ]
            ]
        ];

        try {
            switch ($action) {
                case 'insert': // ***** This does not allow to create IAP product in API
                    // $priceMapping = ApplePriceTierHelper::getTier($params['price']);
                    $response = $client->post('/v2/inAppPurchases', $iapId);
                    return json_decode($response->getBody(), true);

                case 'localizations': // ***** This does not allow to create IAP product in API
                    // $priceMapping = ApplePriceTierHelper::getTier($params['price']);
                    $response = $client->post('/v1/inAppPurchaseLocalizations', $iapLocalization);
                    return json_decode($response->getBody(), true);

                case 'get':
                    $response = $client->get("/v1/inAppPurchases/{$params['iap_uui_id']}");
                    return json_decode($response->getBody(), true);

                case 'iapList':
                    $response = $client->get("v1/apps/{$params['appId']}/inAppPurchases");
                    return json_decode($response->getBody(), true);

                case 'getAppleIapId':
                    $response = $client->get("v1/apps/{$params['appId']}/inAppPurchases");
                    $response_data = json_decode($response->getBody(), true);

                    foreach ($response_data['data'] as $iap) {
                        if ($iap['attributes']['productId'] === $params['productId']) {
                            return $iap['id'];
                        }
                    }
                    return null;

                case 'appList':
                    $response = $client->get("apps");
                    return json_decode($response->getBody(), true);

                case 'update':
                    $response = $client->patch("/v1/inAppPurchases/{$params['iap_uui_id']}", [
                        'json' => [
                            'data' => [
                                'id' => $params['id'],
                                'type' => 'inAppPurchases',
                                'attributes' => $params['attributes']
                            ]
                        ]
                    ]);
                    return json_decode($response->getBody(), true);

                case 'delete':
                    $response = $client->delete("/v1/inAppPurchases/{$params['iap_uui_id']}");
                    return $response->getStatusCode() === 204 ? ['deleted' => true] : ['deleted' => false];

                default:
                    throw new Exception("Unsupported Apple action: $action");
            }

        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                throw new Exception("Apple API error: " . $errorBody);
            }
            throw $e;
        }
    }
}


$object = new In_APP_Products_Manager();


// // Google Play Example of usages

// $result = $object->handle('google','get',[
//     'sku' => 'lt.antanukas.product.book.test' 
// ]
// );

// echo "Google Get Result:\n"; 
// // print_r($result['data']->getStatus()); 
// $listing=$result['data']->getListings();
// foreach($listing as $key => $value){
//     echo "getTitle:  =".$value->getTitle()."\n";
//     echo "getDescription:=".$value->getDescription()."\n";
//     echo "getBenefits: =".$value->getBenefits()."\n";
// }
// // print_r($listing);
// // print_r($result['data']->getDeveloperPayload());
// print_r($result['data']->getSku());
// print_r($result['data']->getPrices());
// print_r($result['data']->getDefaultPrice());


// $result = $object->handle('google','list',  [ ]);

// echo "Google list Result:\n";
// // $listing=$result['data']->getListings();
// print_r($result['data']->getListings());

// exit;


// $object=new In_APP_Products_Manager();
// Google Play Example
// $result = $object->handle('google','insert',  [
//     'sku' => 'lt.antanukas.product.book.39',
//     'title' => 'Bandomoji knyga 39',
//     'description' => '39 testo knyga įterpimui',
//     'price' => 2.39,
//     'currency' => 'EUR',
//     'purchaseType' => 'managedUser',
//     'language' => 'lt',
//     'autoConvertMissingPrices' => true
// ]);

// echo "Google Insert Result:\n";
// print_r($result); 



//*Update google one time products */
// $result = $object->handle('google','update',  [
//     'sku' => 'lt.antanukas.product.book.39',
//     'title' => 'Bandomoji knyga 39 updated',
//     'description' => '39 testo knyga įterpimui updating',
//     'price' => 2.39,
//     'currency' => 'EUR',
//     'purchaseType' => 'managedUser',
//     'language' => 'lt',
//     'autoConvertMissingPrices' => true
// ]);

// echo "Google update Result:\n";
// print_r($result); 
// exit;

//   Get

// Apple Example

// $iap_product_id='';

// $response = $object->handle('apple', 'getAppleIapId', [ 
//     'package_name' => 'lt.antanukas.reader',
//     'productId' => 'lt.antanukas.product.book.test',
//     'appId' => '6443621016'
// ]);

// if ($response['success']) {
//     // print_r($response['data']);
//     $iap_product_id = $response['data'];
// } else {
//     echo "Error: " . $response['error'];
// }

// $response = $object->handle('apple', 'get', [ 
//     'package_name' => 'lt.antanukas.reader',
//     'productId' => 'lt.antanukas.product.book.test',
//     'iap_uui_id' => $iap_product_id,
//     'appId' => '6443621016'
// ]);

// if ($response['success']) {
//     print_r($response['data']);
// } else {
//     echo "Error: " . $response['error'];
// }

// exit;

$price = 2.39;
// $response =  $object->handle('apple','insert', [
//     'productId' => 'lt.antanukas.product.book.39',
//     'title' => 'Test Book 39', 
//     'type' => 'NON_CONSUMABLE',
//     'appId' => '6443621016'
// ]);

// echo "\n response";

// print_r($response);

// if ($response['success']) {
//     echo "success";
// Add price if provided
//     if ($price) {
//         // $iapId = $response['data']['data']['id'] ?? null;
//         $iapId = 6755039396;
//         if ($iapId) {
//            echo  $tier = ApplePriceTierHelper::mapPriceToTier($price); exit;
//             if ($tier) {
//                 $priceResp =  $object->setPrice($iapId, $tier);
//                 $response['price'] = $priceResp;
//             }
//         }
//     }

// print_r($response);
// } else {
//     echo "Error: " . $response['error'];
// }


//localizations

// $response =  $object->handle('apple','localizations', [
//     'productId' => 'lt.antanukas.product.book.39',
//     'title' => 'Knyga 39', 
//     'description' => '39 testo knyga įterpimui',
//     'type' => 'NON_CONSUMABLE',
//     'appId' => '6755039396'
// ]);

// echo "\n response";

// print_r($response);

// if ($response['success']) {
//     echo "success";
//             // Add price if provided
//     //     if ($price) {
//     //         // $iapId = $response['data']['data']['id'] ?? null;
//     //         $iapId = 6755039396;
//     //         if ($iapId) {
//     //            echo  $tier = ApplePriceTierHelper::mapPriceToTier($price); exit;
//     //             if ($tier) {
//     //                 $priceResp =  $object->setPrice($iapId, $tier);
//     //                 $response['price'] = $priceResp;
//     //             }
//     //         }
//     //     }

//     // print_r($response);
// } else {
//     echo "Error: " . $response['error'];
// }


// exit;


// // Batch Get
// $response = $object->handle('google', 'batchget', [ 
//     'package_name' => 'com.example.myapp'
// ], [
//     'skus' => ['premium_upgrade', 'coins_1000', 'subscription_monthly']
// ]);

// if ($response['success']) {
//     print_r($response['data']);
// } else {
//     echo "Error: " . $response['error'];
// }

// // Batch Update
// $response = $object->handle('google', 'batchupdate', [ 
//     'package_name' => 'com.example.myapp'
// ], [
//     'products' => [
//         [
//             'sku' => 'premium_upgrade',
//             'status' => 'active',
//             'defaultLanguage' => 'en-US',
//             'defaultPrice' => [
//                 'priceMicros' => '4990000',
//                 'currency' => 'EUR'
//             ]
//         ],
//         [
//             'sku' => 'subscription_monthly',
//             'status' => 'active',
//             'defaultLanguage' => 'en-US',
//             'defaultPrice' => [
//                 'priceMicros' => '9990000',
//                 'currency' => 'EUR'
//             ]
//         ]
//     ]
// ]);

// if ($response['success']) {
//     print_r($response['data']);
// } else {
//     echo "Error: " . $response['error'];
// }



// // Apple Example
// $response =  $object->handle('apple','insert', [
//     'issuer_id' => 'YOUR_ISSUER_ID',
//     'key_id' => 'YOUR_KEY_ID',
//     'private_key_file' => 'AuthKey.p8',
//     'app_id' => 'YOUR_APP_ID'
// ], [
//     'sku' => 'premium_upgrade',
//     'title' => 'Premium Upgrade',
//     'price_tier' => 3, // Apple uses price tiers (see Apple Price Tiers doc)
//     'type' => 'NON_CONSUMABLE'
// ]);
// if ($response['success']) {
//     print_r($response['data']);
// } else {
//     echo "Error: " . $response['error'];
// }
// $response = $object->handle('apple', 'list', [
//     'issuer_id' => 'your-issuer-id',
//     'key_id' => 'your-key-id',
//     'private_key_file' => '/path/to/AuthKey.p8'
// ], [
//     'app_id' => '1234567890'
// ]);

// print_r($response);

// $response = $object->handle('apple', 'prices', [
//     'issuer_id' => 'your-issuer-id',
//     'key_id' => 'your-key-id',
//     'private_key_file' => '/path/to/AuthKey.p8'
// ], [
//     'id' => 'abcd1234-ef56-7890-gh12-ijklmnop3456'
// ]);

// print_r($response);

