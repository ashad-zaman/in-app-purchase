# in-app-purchase

In App subscription purchase and validaton implementation:

In app subscription purchase project performs google pay and appple pay receipt validation and acknowledgement checking to ensure the purchase if valid through API for mobile application.

API implemented to this file: in-app-purchase.php

OnTime product purchase:
In app product purchase   performs product purchase validation with acknowledgement for google pay and apple pay
This API also implemented to this file: in-app-purchase.php

Google pay and Apple pay product management:
Implementated product listing in UI with  add/edit/delete management in  google store and apple store

 ```php
 <?php
// Google Play Example of usages

$result = $object->handle('google','get',[
    'sku' => 'lt.xxxx.product.book.test' 
]
);

echo "Google Get Result:\n"; 
// print_r($result['data']->getStatus()); 
$listing=$result['data']->getListings();
foreach($listing as $key => $value){
    echo "getTitle:  =".$value->getTitle()."\n";
    echo "getDescription:=".$value->getDescription()."\n";
    echo "getBenefits: =".$value->getBenefits()."\n";
}
// print_r($listing);
// print_r($result['data']->getDeveloperPayload());
print_r($result['data']->getSku());
print_r($result['data']->getPrices());
print_r($result['data']->getDefaultPrice());


$result = $object->handle('google','list',  [ ]);

echo "Google list Result:\n";
// $listing=$result['data']->getListings();
print_r($result['data']->getListings());

exit;


$object=new In_APP_Google_Products_Manager();
Google Play Example
$result = $object->handle('google','insert',  [
    'sku' => 'lt.xxxx.product.book.39',
    'title' => 'Bandomoji knyga 39',
    'description' => '39 testo knyga įterpimui',
    'price' => 2.39,
    'currency' => 'EUR',
    'purchaseType' => 'managedUser',
    'language' => 'lt',
    'autoConvertMissingPrices' => true
]);

echo "Google Insert Result:\n";
print_r($result); 



*Update google one time products */
$result = $object->handle('google','update',  [
    'sku' => 'lt.xxxx.product.book.39',
    'title' => 'Bandomoji knyga 39 updated',
    'description' => '39 testo knyga įterpimui updating',
    'price' => 2.39,
    'currency' => 'EUR',
    'purchaseType' => 'managedUser',
    'language' => 'lt',
    'autoConvertMissingPrices' => true
]);







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
?>

 