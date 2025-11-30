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
 * Summary of Apple_Iap_Product_Manager
 */
class Apple_Iap_Product_Manager
{

    // Apple App specific settings
    private $appId = 'lt.xxxx.reader';
    private $localeToEnsure = 'en-US';
    private $localDescription = '39 testo knyga įterpimui';

    // ---------- JWT ----------

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

    /**
     * Summary of handle
     * @param string $action
     * @param array $params
     * @return array{data: mixed, error: null, success: bool|array{data: null, error: string, success: bool}}
     */
    public function handle(string $action, array $params = [])
    {
        try {

            $data = $this->appleHandler($action, $params);

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
     * Summary of appleHandler
     * @param string $action
     * @param array $params
     * @throws Exception
     */
    private function appleHandler(string $action, array $params)
    {
        $client = self::appleClient();

        $inAppProduct = [
            'json' => [
                'data' => [
                    'type' => 'inAppPurchases',
                    'attributes' => [
                        "locale" => "en-US",
                        'name' => $params['title'],
                        'productId' => $params['productId'],
                        'inAppPurchaseType' => $params['type'] ?? 'CONSUMABLE',
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


        try {
            switch ($action) {

                case 'insert': // ***** This does not allow to create IAP product in API
                    // $priceMapping = ApplePriceTierHelper::getTier($params['price']);
                    $response = $client->post('/v2/inAppPurchases', $inAppProduct);
                    return json_decode($response->getBody(), true);

                case 'findiap': // ***** This does not allow to create IAP product in API
                    // $priceMapping = ApplePriceTierHelper::getTier($params['price']);
                    $response = $client->GET("/v1/inAppPurchases?filter[productId]=" . urlencode($params['productId']));
                    return json_decode($response->getBody(), true);

                case 'getAllTerritories': // ***** This does not allow to create IAP product in API

                    $response = $client->GET("/v1/territories?limit=200");
                    $territories = [];
                    $response = json_decode($response->getBody(), true);
                    if (!empty($response['data'])) {
                        foreach ($response['data'] as $t) {
                            $territories[] = [
                                'type' => 'territories',
                                'id' => $t['id']
                            ];
                        }
                    }

                    return $territories;

                case 'availability': // ***** This does not allow to create IAP product in API

                    $payload = [
                        'json' => [
                            "data" => [
                                "type" => "inAppPurchaseAvailabilities",
                                "attributes" => [
                                    "availableInNewTerritories" => true   // ✅ REQUIRED field (boolean)
                                ],
                                "relationships" => [
                                    "inAppPurchase" => [
                                        "data" => [
                                            "type" => "inAppPurchases",
                                            "id" => $params['iapId']
                                        ]
                                    ],
                                    "availableTerritories" => [
                                        "data" => $params['territories']
                                    ]
                                ]
                            ]
                        ]
                    ];

                    $response = $client->POST("/v1/inAppPurchaseAvailabilities", $payload);
                    $response = json_decode($response->getBody(), true);



                    return $response;



                case 'localizations': // ***** This does not allow to create IAP product in API


                    $payload = [
                        'json' => [
                            "data" => [
                                "type" => "inAppPurchaseLocalizations",
                                "attributes" => [
                                    "locale" => $this->localeToEnsure,
                                    "name" => $params['title'],
                                    "description" => $params['description']
                                ],
                                "relationships" => [
                                    "inAppPurchaseV2" => [
                                        "data" => [
                                            "type" => "inAppPurchases",
                                            "id" => $params['iapId']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];
                    $response = $client->POST("/v1/inAppPurchaseLocalizations", $payload);


                    return json_decode($response->getBody(), true);


                case 'getPricePoints':


                    $pps = $client->get("/v2/inAppPurchases/" . $params['iapId'] . "/pricePoints?filter[territory]=LTU&include=territory&limit=200");
                    $data = json_decode($pps->getBody(), true);

                    // Find the closest price to the target
                    $bestMatch = null;
                    $minDiff = PHP_FLOAT_MAX;

                    foreach ($data['data'] as $point) {
                        $price = (float) ($point['attributes']['customerPrice'] ?? 0);
                        $diff = abs($price - $params['targetPrice']);
                        if ($diff < $minDiff) {
                            $minDiff = $diff;
                            $bestMatch = [
                                'id' => $point['id'],
                                'price' => $price,
                                'currency' => $point['attributes']['currency'],
                            ];
                        }
                    }
                    return $bestMatch;

                case 'createInAppPurchasePriceSchedules':

                    $payload = [
                        'json' => [
                            'data' => [
                                'type' => 'inAppPurchasePriceSchedules',
                                'relationships' => [
                                    'inAppPurchase' => [
                                        'data' => [
                                            'type' => 'inAppPurchases',
                                            'id' => $params['iapId']
                                        ]
                                    ],

                                    'baseTerritory' => [
                                        'data' => [
                                            'type' => 'territories',
                                            'id' => 'LTU'
                                        ]
                                    ],

                                    'manualPrices' => [
                                        'data' => [
                                            [
                                                'type' => 'inAppPurchasePrices',
                                                'id' => '${local-28}' // local inline reference
                                            ]
                                        ]
                                    ]
                                ]
                            ],

                            'included' => [
                                [
                                    'type' => 'inAppPurchasePrices',
                                    'id' => '${local-28}',
                                    'relationships' => [
                                        'territory' => [
                                            'data' => [
                                                'type' => 'territories',
                                                'id' => 'LTU'
                                            ]
                                        ],
                                        'inAppPurchasePricePoint' => [
                                            'data' => [
                                                'type' => 'inAppPurchasePricePoints',

                                                'id' => $params['printpointId']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];
                    $response = $client->POST("/v1/inAppPurchasePriceSchedules", $payload);
                    return json_decode($response->getBody(), true);

                case 'iappStatus':


                    $response = $client->get('/v2/inAppPurchases/' . $params['iapId']);


                    return json_decode($response->getBody(), true);



                case 'iapSubmissions':
                    $payload = [
                        'data' => [
                            'type' => 'inAppPurchaseSubmissions',
                            'relationships' => [
                                'inAppPurchaseV2' => [
                                    'data' => [
                                        'type' => 'inAppPurchases',
                                        'id' => $params['iapId']
                                    ]
                                ]
                            ]
                        ]
                    ];

                    $response = $client->post('/v1/inAppPurchaseSubmissions', [
                        'json' => $payload
                    ]);


                    return json_decode($response->getBody(), true);

                case 'appStoreVersions':

                    $response = $client->get('/v1/apps/' . $params['appId'] . '/appStoreVersions?limit=1');

                    return json_decode($response->getBody(), true);




                case 'getIAPReviewScreenshots':

                    $response = $client->get('/v2/inAppPurchases/' . $params['iapId'] . '/appStoreReviewScreenshot?include=inAppPurchaseV2');

                    return json_decode($response->getBody(), true);



                case 'createInAppPurchaseReviewScreenshots':
                    $payload = [
                        'data' => [
                            'type' => 'inAppPurchaseAppStoreReviewScreenshots',
                            'attributes' => [
                                'fileName' => basename($params['filelocalPath']),    // your filename
                                'fileSize' => filesize($params['filelocalPath']), // required
                            ],
                            'relationships' => [
                                'inAppPurchaseV2' => [
                                    'data' => [
                                        'type' => 'inAppPurchases',
                                        'id' => $params['iapId']   // your IAP ID
                                    ]
                                ]
                            ]
                        ]
                    ];

                    $response = $client->post('/v1/inAppPurchaseAppStoreReviewScreenshots', [
                        'json' => $payload
                    ]);


                    return json_decode($response->getBody(), true);


                case 'uploadInAppPurchaseReviewScreenshots':

                    $multipart = [];
                    // foreach ($params['uploadFields'] as $key => $value) {
                    //     $multipart[] = [
                    //         'name' => $key,
                    //         'contents' => $value
                    //     ];
                    // }

                    $multipart[] = [
                        'name' => 'file',
                        'contents' => file_get_contents($params['filelocalPath']),
                        'filename' => basename($params['filelocalPath']),
                        'headers' => [
                            'Content-Type' => mime_content_type($params['filelocalPath'])
                        ]
                    ];

                    echo "Uploading to URL: " . $params['uploadUrl'] . "\n";

                    //                          print_r($multipart);

                    $filePath = $params['filelocalPath'];
                    $file = file_get_contents($filePath);

                    $options = [
                        'headers' => [
                            'Content-Type' => 'image/jpeg',
                            'Expect' => '',
                            'Host' => 'northamerica-1.object-storage.apple.com'
                        ],
                        'body' => $file,
                    ];
                    print_r($options);
                    $response = $client->request('PUT', $params['uploadUrl'], $options);


                    // $response = $client->post($params['uploadUrl'], [
                    //     'multipart' => $multipart
                    // ]);     


                    return json_decode($response->getBody(), true);

                case 'commitInAppPurchaseReviewScreenshots':
                    $checksum = base64_encode(hash_file('sha256', $params['filelocalPath'], true));
                    $payload = [
                        'data' => [
                            'id' => $params['screenshotId'],
                            'type' => 'inAppPurchaseAppStoreReviewScreenshots',
                            'attributes' => [
                                'uploaded' => true
                            ]
                        ]
                    ];
                     
                    $response = $client->patch("/v1/inAppPurchaseAppStoreReviewScreenshots/" . $params['screenshotId'], [
                        'json' => $payload
                    ]);


                    return json_decode($response->getBody(), true);



                case 'getIAPReviewDetail':

                    $response = $client->get(' /v2/inAppPurchases/' . $params['iapId'] . '/inAppPurchaseAppStoreReviewDetail');

                    return json_decode($response->getBody(), true);
                case 'createIAPReviewDetail':
                    $payload = [
                        'data' => [
                            'type' => 'inAppPurchaseAppStoreReviewDetails',
                            'attributes' => [
                                'reviewNotes' => 'This IAP unlocks premium features...'
                            ],
                            'relationships' => [
                                'inAppPurchase' => [
                                    'data' => [
                                        'type' => 'inAppPurchases',
                                        'id' => $params['iapId']
                                    ]
                                ]
                            ]
                        ]
                    ];

                    $response = $client->post('/v1/inAppPurchaseAppStoreReviewDetails', [
                        'json' => $payload
                    ]);


                    return json_decode($response->getBody(), true);

                case 'updateIAPReviewDetail':
                    $payload = [
                        'data' => [
                            'type' => 'appReviewDetails',
                            'id' => $params['reviewDetailId'],
                            'attributes' => [
                                'contactFirstName' => 'Ashad',
                                'contactLastName' => 'Zaman',
                                'contactPhone' => '+880100000000',
                                'contactEmail' => 'support@example.com',
                                'demoAccountRequired' => false,
                                'notes' => 'Hi Apple Review Team, this app uses strict IAP verification.
                                                Please log in with test credentials from App Store Connect.'
                            ]
                        ]
                    ];

                    $response = $client->post('/v1/appReviewDetails/' . $params['reviewDetailId'], [
                        'json' => $payload
                    ]);


                    return json_decode($response->getBody(), true);


                case 'linkPriceScheduleToManualPrice':

                    if (!$params['priceScheduleId']) {
                        $payload = [
                            'json' => [
                                "data" => [
                                    "type" => "inAppPurchasePriceSchedules",
                                    "relationships" => [
                                        "inAppPurchase" => [
                                            "data" => [
                                                "type" => "inAppPurchases",
                                                "id" => $params['iapId']
                                            ]
                                        ],
                                        "manualPrices" => [
                                            "data" => [
                                                [
                                                    "type" => "inAppPurchasePrices",
                                                    "id" => $params['manualPriceId']
                                                ]
                                            ]
                                        ],
                                        "inAppPurchasePricePoint" => [
                                            "data" => [
                                                "type" => "inAppPurchasePricePoints",
                                                "id" => $params['printpointId']
                                            ]
                                        ],
                                        "baseTerritory" => [
                                            "data" => [
                                                "type" => "territories",
                                                "id" => "LT" // Lithuania
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];

                        // print_r($payload);
                        $response = $client->POST("/v1/inAppPurchasePriceSchedules", $payload);
                    } else {
                        $payload = [
                            'json' => [
                                "data" => [
                                    "type" => "inAppPurchasePriceSchedules",
                                    "relationships" =>
                                        [
                                            "manualPrices" => [
                                                "data" => [
                                                    [
                                                        "type" => "inAppPurchasePrices",
                                                        "id" => $params['manualPriceId']
                                                    ]
                                                ]
                                            ]
                                        ]
                                ]
                            ]
                        ];
                        $response = $client->PATCH("/v1/inAppPurchasePriceSchedules/" . $params['priceScheduleId'], $payload);

                    }

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
                throw new Exception($errorBody);
            }
            throw $e;
        }
    }
    // ---------- Start ----------



} // end of class




// Apple Example code to create IAP product
$object = new Apple_Iap_Product_Manager();
// $iap_product_id='';

$response = $object->handle('apple', 'getAppleIapId', [ 
    'package_name' => 'lt.xxxx.reader',
    'productId' => 'lt.xxxx.product.book.test',
    'appId' => '6443621016'
]);

if ($response['success']) {
    // print_r($response['data']);
    $iap_product_id = $response['data'];
} else {
    echo "Error: " . $response['error'];
}

$response = $object->handle('apple', 'get', [ 
    'package_name' => 'lt.xxxx.reader',
    'productId' => 'lt.xxxx.product.book.test',
    'iap_uui_id' => $iap_product_id,
    'appId' => '6443621016'
]);

if ($response['success']) {
    print_r($response['data']);
} else {
    echo "Error: " . $response['error'];
}

 

$price = 2.39;
$response =  $object->handle('apple','insert', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Test Book 39', 
    'type' => 'CONSUMABLE',
    'appId' => '6443621016'
]);

echo "\n response";

print_r($response);

if ($response['success']) {
    echo "success";
//Add price if provided
    if ($price) {
        // $iapId = $response['data']['data']['id'] ?? null;
        $iapId = 6755039396;
        if ($iapId) {
           echo  $tier = ApplePriceTierHelper::mapPriceToTier($price); exit;
            if ($tier) {
                $priceResp =  $object->setPrice($iapId, $tier);
                $response['price'] = $priceResp;
            }
        }
    }

print_r($response);
} else {
    echo "Error: " . $response['error'];
}


//localizations Example Code

$territoriesResponse =  $object->handle('getAllTerritories');

$response =  $object->handle('availability', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6755039396',
    'iapId' => '6755039396',
    'territories' => $territoriesResponse['data']
]);

echo "\n availability response 3\n";

print_r($response);

if ($response['success']) {
    echo "success";

} else {
    echo "Error: " . $response['error'];
}


//localizations Example Code

$response =  $object->handle('localizations', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6755039396',
    'iapId' => '6755039396',
    'territories' => $territoriesResponse['data']
]);



if ($response['success']) {
    echo "success";

} else {  
    // 1. Extract the JSON part
$errorString = $response['error'];
// 1. Extract the JSON part
$jsonString = substr($errorString, strpos($errorString, '{'));

// 2. Decode the JSON string
$data = json_decode($jsonString);


    if ($data && isset($data->errors[0]->status) && isset($data->errors[0]->detail)) {
        $status = $data->errors[0]->status;
        $detail = $data->errors[0]->detail;

        echo "Status: " . $status . "\n";
        echo "Detail: " . $detail . "\n";
    } else {
        echo "Could not extract status and detail from the string.\n";
    }
}



getPricePoints Example Code

$response =  $object->handle('getPricePoints', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6443621016',
    'iapId' => '6755039396',
    'currency' => 'EUR',
    'manualPriceId' => '28',
    'printpointId' =>'eyJzIjoiNjc1NTAzOTM5NiIsInQiOiJMVFUiLCJwIjoiMTAwMjgifQ',
    'targetPrice' => 2.39,
    'territory'=>'LT',
]);

 print_r($response);

if ($response['success']) {
    echo "success";

} else { 
    echo   $response['error'];

}


//createInAppPurchasePriceSchedules Example Code

$response =  $object->handle('createInAppPurchasePriceSchedules', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6443621016',
    'iapId' => '6755039396',
    'currency' => 'EUR',
    'manualPriceId' => '28',
    'printpointId' =>'eyJzIjoiNjc1NTAzOTM5NiIsInQiOiJMVFUiLCJwIjoiMTAwMjgifQ',
    'targetPrice' => 2.39,
    'territory'=>'LT',
]);

 print_r($response);

if ($response['success']) {
    echo "success";

} else { 
    echo   $response['error'];
    // 1. Extract the JSON part
// $errorString = $response['error'];
// // 1. Extract the JSON part
// $jsonString = substr($errorString, strpos($errorString, '{'));

// // 2. Decode the JSON string
// $data = json_decode($jsonString);


    if ($data && isset($data->errors[0]->status) && isset($data->errors[0]->detail)) {
        $status = $data->errors[0]->status;
        $detail = $data->errors[0]->detail;

        echo "Status: " . $status . "\n";
        echo "Detail: " . $detail . "\n";
    } else {
        echo "Could not extract status and detail from the string.\n";
    }
}

 



// inAppPurchaseproduct status Example Code



$response =  $object->handle('iappStatus', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6443621016',
    'iapId' => '6755039396',
    'currency' => 'EUR',
    'manualPriceId' => '28',
    'printpointId' =>'eyJzIjoiNjc1NTAzOTM5NiIsInQiOiJMVFUiLCJwIjoiMTAwMjgifQ',
    'targetPrice' => 2.39,
    'territory'=>'LT',
]);

 print_r($response);

if ($response['success']) {
    echo "success";

    $status = $response['data']['data']['attributes']['state'];

    echo "IAP Status: $status\n"; 

} else { 
    echo   $response['error'];

}

$versionId = '';
$response =  $object->handle('appStoreVersions', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6443621016',
    'iapId' => '6755039396',
    'currency' => 'EUR',
    'manualPriceId' => '28',
    'printpointId' =>'eyJzIjoiNjc1NTAzOTM5NiIsInQiOiJMVFUiLCJwIjoiMTAwMjgifQ',
    'targetPrice' => 2.39,
    'territory'=>'LT',
]);

//  print_r($response);

if ($response['success']) {
    echo "success";

   $versionId = $response['data']['data'][0]['id'];

    echo "IAP versionId: $versionId\n"; 

} else { 
    echo   $response['error'];

}


// getIAPReviewScreenshots Example Code


$reviewDetailId='';
$response =  $object->handle('getIAPReviewScreenshots', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6443621016',
    'iapId' => '6755039396',
    'currency' => 'EUR',
    'manualPriceId' => ApplePriceTierHelper::mapPriceToTier($price) ,
    'printpointId' =>'eyJzIjoiNjc1NTAzOTM5NiIsInQiOiJMVFUiLCJwIjoiMTAwMjgifQ',
    'targetPrice' => $price,
    'versionId'=> $versionId,
    'territory'=>'LT',
]);

 print_r($response);

if ($response['success']) {
    echo "success";


   // Upload token + S3 instructions
 $uploadOperations = $body['data']['data']['attributes']['uploadOperations'];

 print_r( $uploadOperations );
echo 'assetId'.$assetId         = $body['data']['data']['id'];
echo 'uploadUrl'.$uploadUrl       = $uploadOperations[0]['url'];
 $uploadFields    = $uploadOperations[0]['fields'];

 print_r($uploadFields);

} else { 
    echo   $response['error'];

}

//createInAppPurchaseReviewScreenshots Example Code

$reviewDetailId='';
$response =  $object->handle('createInAppPurchaseReviewScreenshots', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6443621016',
    'iapId' => '6755039396',
    'currency' => 'EUR',
    'manualPriceId' => ApplePriceTierHelper::mapPriceToTier($price) ,
    'printpointId' =>'eyJzIjoiNjc1NTAzOTM5NiIsInQiOiJMVFUiLCJwIjoiMTAwMjgifQ',
    'targetPrice' => $price,
    'versionId'=> $versionId,
    'filelocalPath'=>dirname(__FILE__).'/assets/reviewscreenshots/screenshot_28_new.jpg',
    'territory'=>'LT',
]);

print_r($response);
if ($response['success']) {
    echo "success";


   // Upload token + S3 instructions
 $uploadOperations = $response['data']['data']['attributes']['uploadOperations'];

echo 'assetId=>'.$assetId         = $response['data']['data']['id'];
 $uploadUrl       = $uploadOperations[0]['url'];
 $uploadFields    = $uploadOperations[0]['requestHeaders'][0];

//  print_r($uploadFields);

} else { 
    echo   $response['error'];

}

echo "uploadInAppPurchaseReviewScreenshots\n    ";
//  // uploadInAppPurchaseReviewScreenshots Example Code
//   $assetId='78d8f608-57c5-4a74-9c8f-b9170c02fe6a';
// $uploadUrl ='https://northamerica-1.object-storage.apple.com/itmspod12-assets-massilia-200001/PurpleSource221/v4/56/b2/a1/56b2a139-d13d-e7e5-7ac8-8e2a94a93c93/CYWQbsW7IkotLRb0ZygU5ZP_wISMvA-LDUUVShDrorA_U003d-1763391504470?partNumber=1&uploadId=ddbefba0-c3c5-11f0-af21-70b2b918ef99&apple-asset-repo-correlation-key=F4KIBMLA5OIXI3C3PFXBJWXGPI&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Date=20251117T145824Z&X-Amz-SignedHeaders=host&X-Amz-Credential=MKIAP7F9QNTEY48OTE7F%2F20251117%2Fnorthamerica-1%2Fs3%2Faws4_request&X-Amz-Expires=604800&X-Amz-Signature=d0e7bc58d7cd38b59e4074c1e182daf5a300be5a9ab7f3bd065cfa5e9f79c13d';
 $uploadFields =[];
$uploadFields=[
     'Content-Type'  => 'image/jpeg'
]; 
$response =  $object->handle('uploadInAppPurchaseReviewScreenshots', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6443621016',
    'iapId' => '6755039396',
    'currency' => 'EUR',
    'manualPriceId' => ApplePriceTierHelper::mapPriceToTier($price) ,
    'printpointId' =>'eyJzIjoiNjc1NTAzOTM5NiIsInQiOiJMVFUiLCJwIjoiMTAwMjgifQ',
    'targetPrice' => $price,
    'versionId'=> $versionId,
    'filelocalPath'=>dirname(__FILE__).'/assets/reviewscreenshots/screenshot_28_new.jpg',
    'uploadUrl'=>$uploadUrl,
    'uploadFields'=>$uploadFields,
    'territory'=>'LT',
]);

 print_r($response);

if ($response['success']) {
    echo "success";



} else { 
    echo   $response['error'];

}

 exit;
 // commitInAppPurchaseReviewScreenshots Example Code
$assetId='409a1ba0-b168-483e-962e-494407366ab2';

$response =  $object->handle('commitInAppPurchaseReviewScreenshots', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6443621016',
    'iapId' => '6755039396',
    'currency' => 'EUR',
    'manualPriceId' => ApplePriceTierHelper::mapPriceToTier($price) ,
    'printpointId' =>'eyJzIjoiNjc1NTAzOTM5NiIsInQiOiJMVFUiLCJwIjoiMTAwMjgifQ',
    'targetPrice' => $price, 
    'filelocalPath'=>dirname(__FILE__).'/assets/reviewscreenshots/screenshot_28_new.jpg',
    'screenshotId'=>$assetId, 
    'territory'=>'LT',
]);

 print_r($response);

if ($response['success']) {
    echo "success";


} else { 
    echo   $response['error'];

} 

exit;


// Batch Get
$response = $object->handle('google', 'batchget', [ 
    'package_name' => 'com.example.myapp'
], [
    'skus' => ['premium_upgrade', 'coins_1000', 'subscription_monthly']
]);

if ($response['success']) {
    print_r($response['data']);
} else {
    echo "Error: " . $response['error'];
}

// Batch Update
$response = $object->handle('google', 'batchupdate', [ 
    'package_name' => 'com.example.myapp'
], [
    'products' => [
        [
            'sku' => 'premium_upgrade',
            'status' => 'active',
            'defaultLanguage' => 'en-US',
            'defaultPrice' => [
                'priceMicros' => '4990000',
                'currency' => 'EUR'
            ]
        ],
        [
            'sku' => 'subscription_monthly',
            'status' => 'active',
            'defaultLanguage' => 'en-US',
            'defaultPrice' => [
                'priceMicros' => '9990000',
                'currency' => 'EUR'
            ]
        ]
    ]
]);

if ($response['success']) {
    print_r($response['data']);
} else {
    echo "Error: " . $response['error'];
}

 
// inAppPurchaseSubmissions Example Code



$response =  $object->handle('iapSubmissions', [
    'productId' => 'lt.xxxx.product.book.39',
    'title' => 'Knyga 39', 
    'description' => '39 testo knyga įterpimui',
    'type' => 'CONSUMABLE',
    'appId' => '6443621016',
    'iapId' => '6755039396',
    'currency' => 'EUR',
    'manualPriceId' => '28',
    'printpointId' =>'eyJzIjoiNjc1NTAzOTM5NiIsInQiOiJMVFUiLCJwIjoiMTAwMjgifQ',
    'targetPrice' => 2.39,
    'territory'=>'LT',
]);

 print_r($response);

if ($response['success']) {
    echo "success";

} else { 
    echo   $response['error'];

}