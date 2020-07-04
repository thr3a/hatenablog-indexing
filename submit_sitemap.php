<?php
//ドメイン
$siteUrl = 'https://kanaxx.hatenablog.jp/';
//認証用のファイル
$credentialFile = './credential.json';

//サイトマップ
$sitemapOrIndexUrls = [];

//Search Console APIに制限はないけど、間隔をあける
$intervalSecondsPerAPI = 1;

require_once './vendor/autoload.php';

//コマンドラインパラメータ
foreach($argv as $n=>$v){
    if(startsWith($v, 'http://') || startsWith($v, 'https://') ){
        $sitemapOrIndexUrls[] = trim($v);
    }
}
if(empty($sitemapOrIndexUrls)){
    echo 'put sitemap or sitemap index url as commandline parameter';
    exit;
}

//URLからサイトマップ取りに行くところ
$options = ['exceptions' => false,'debug' => false];
$http = new GuzzleHttp\Client($options);

$list = [];

do{
    $url = array_shift($sitemapOrIndexUrls);
    echo '-getting XML >> ' . $url . PHP_EOL;
    $tags = readSitemapXml($http, $url);
    echo ' got ' . count($tags) . ' entries.' . PHP_EOL;

    foreach($tags as $name=>$data){
        $loc =  (String)$data->loc;
        if($name == 'sitemap'){
            $sitemapOrIndexUrls[] = $loc;
            echo ' sitemap URL :' . $loc . PHP_EOL;
        }elseif($name == 'url'){
            $list[] = $url;
            break;
        }else{
            echo " something wrong <$name> tag." . PHP_EOL;
        }
    }
}while(!empty($sitemapOrIndexUrls));

// var_dump($list);
echo '=== Sitemap URL ====' . PHP_EOL;
echo count($list)   . PHP_EOL;
echo '====================' . PHP_EOL;

//Search Console API
$client = new Google_Client();
$client->setAuthConfig($credentialFile);
$client->addScope('https://www.googleapis.com/auth/webmasters');
$httpClient = $client->authorize();
$endpointBase = 'https://www.googleapis.com/webmasters/v3/sites/' . urlencode($siteUrl) . '/sitemaps/';


$results = [];
foreach($list as $n=>$sitemap){
    
    $endpoint = $endpointBase . urlencode($sitemap);

    //このAPIはPUTするやつ
    $response = $httpClient->put($endpoint);
    $body = $response->getBody()->getContents();
    $json = json_decode($body, true);
    
    $status = $response->getStatusCode();
    $results[$status] = ($results[$status]??0) + 1;

    if($status==204){
        echo $status . ':' . $response->getReasonPhrase() . '|'  .$sitemap .PHP_EOL;
    }else{
        $message = $json['error']['message']??'-';
        echo $status . ':' . $response->getReasonPhrase() . '|'  .$message  .'|' . $message .PHP_EOL;
    }

    sleep($intervalSecondsPerAPI);
}

echo '=== Result ===' . PHP_EOL;
foreach($results as $status=>$count){
    echo $status . ':' . $count . PHP_EOL;
}
echo '==============' . PHP_EOL;


function readSitemapXml($http, $url){
    $response = $http->request('GET', $url);
    $body = $response->getBody()->getContents();
    $xml = new SimpleXMLElement($body);
    return $xml;
}

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
