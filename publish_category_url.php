<?php
//認証用のファイル
$credentialFile = './credential.json';

//制限事項 // https://developers.google.com/search/apis/indexing-api/v3/quota-pricing
//1分以内に60回以上投げてはいけないので間隔をあける
$intervalSecondsPerAPI = 1;
//1日で投げられるAPIの上限
$limitPublishPerDay = 200;

require_once './vendor/autoload.php';

//コマンドラインパラメータ
$hatenaId='';
$atomEndpoint = $argv[1]??'';
$apiKey = $argv[2]??'';
$blogURL = $argv[3]??'';

if(empty($apiKey) || empty($atomEndpoint)){
    echo "command line parameters are wrong." .PHP_EOL;
    echo "publish_category_url.php ".PHP_EOL;
    echo "<atomEndpoint> .. ex) https://blog.hatena.ne.jp/(hatenaid)/(domain)/atom".PHP_EOL;
    echo "<blogURL> ex) https://yourdomain ".PHP_EOL;
    echo "<atom API key>". PHP_EOL;
    exit(1);
}

if( preg_match('@https://blog.hatena.ne.jp/([^/]*)/([^/]*)/atom@', $atomEndpoint, $_)){
    $hatenaId=$_[1];
    if(empty($blogURL)){
        $blogURL = $_[2];
    }
}else{
    echo 'atom Endpoint is not nomatch.';
    exit(1);
}

echo "--show parameters--" .PHP_EOL;
echo "hatenaId = $hatenaId" .PHP_EOL;
echo "atomEndpoint = $atomEndpoint" .PHP_EOL;
echo "apiKey = $apiKey" .PHP_EOL;
echo "blogURL = $blogURL".PHP_EOL;

$categoryAtomEndpoint = $atomEndpoint.'/category';

//カテゴリの一覧
$categories = [];
$options = ['exceptions' => false,'debug' => false, 'auth' => [$hatenaId, $apiKey]];
$http = new GuzzleHttp\Client($options);
$response = $http->request('GET', $categoryAtomEndpoint);
$status = $response->getStatusCode();
if(200 != $status){
    echo "cant get category data from $categoryAtomEndpoint" . PHP_EOL;
    exit(1);
}

$body = $response->getBody()->getContents();
$xml = new SimpleXMLElement($body);
foreach($xml->children("atom", true) as $x){
    $cat = (string)($x->attributes()['term']??'');
    if(!empty($cat)){
        $categories[] = $cat;
    }
}

echo '=== Category ====' . PHP_EOL;
echo count($categories)   . PHP_EOL;
echo '============' . PHP_EOL;

//Indexing API
$client = new Google_Client();
$client->setAuthConfig($credentialFile);
$client->addScope(Google_Service_Indexing::INDEXING);
$httpClient = $client->authorize();
$endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

$results = [];
foreach($categories as $n=>$cat){
    if($limitPublishPerDay <= $n){
        break;
    }
    $param = [];
    $param['type'] = 'URL_UPDATED';//'URL_NOTIFICATION_TYPE_UNSPECIFIED';
    $param['url'] = "https://" . $blogURL . "/archive/category/" . urlencode($cat);
    
    $response = $httpClient->post($endpoint, ['json' => $param]);
    $body = $response->getBody()->getContents();
    $json = json_decode($body, true);
    
    $status = $response->getStatusCode();
    $results[$status] = ($results[$status]??0) + 1;

    if($status==200){
        $time = toJST($json["urlNotificationMetadata"]["latestUpdate"]["notifyTime"]);
        echo $status . ':' . $response->getReasonPhrase() . '|' . $param['url'] . '|'.$time .PHP_EOL;
    }else{
        $message = $json['error']['message']??'-';
        echo $status . ':' . $response->getReasonPhrase() . '|' . $param['url'] . '|-|' .$message .PHP_EOL;
    }

    sleep($intervalSecondsPerAPI);
}

echo '=== Result ===' . PHP_EOL;
foreach($results as $status=>$count){
    echo $status . ':' . $count . PHP_EOL;
}
echo '==============' . PHP_EOL;


//Google APIのタイムスタンプがnano秒まであるので正規表現で削り取る
function toJST($datetime){
    $p = '/(\d{4})-(\d{2})-(\d{2})T(\d{2})\:(\d{2})\:(\d{2})\.[0-9]{9}Z/';
    if( preg_match($p, $datetime, $_)){
        $datetime = "$_[1]-$_[2]-$_[3]T$_[4]:$_[5]:$_[6]Z";
    }
    $t = new DateTime($datetime);
    $t->setTimeZone(new DateTimeZone('Asia/Tokyo'));
    return $t->format('Y-m-d H:i:s');
}

function startsWith($haystack, $needle){
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}
