<?php
//認証用のファイル
$credentialFile = './credential.json';
//サイトマップ
$sitemapIndexUrl = 'https://example.com/sitemap_index.xml';

//制限事項 // https://developers.google.com/search/apis/indexing-api/v3/quota-pricing
//1分以内に60回以上投げてはいけないので間隔をあける
$intervalSecondsPerAPI = 2;
//1日で投げられるAPIの上限
$limitPublishPerDay = 200;

require_once './vendor/autoload.php';

$options = ['exceptions' => false,'debug' => false];
$http = new GuzzleHttp\Client($options);

echo '-getting sitemapindex XML. ' . $sitemapIndexUrl . PHP_EOL;
$sitemapIndex = readSitemapXml($http, $sitemapIndexUrl);

$list = [];
foreach($sitemapIndex as $s=>$sitemap){
    $sitemapUrl = (String)$sitemap->loc;
    echo '-getting sitemap XML. ' . $sitemapUrl . PHP_EOL;
    $urlSet = readSitemapXml($http, $sitemapUrl);
    foreach($urlSet as $n=>$url){
        $sitemap = [];
        $sitemap['loc'] = (string)$url->loc;
        $sitemap['lastmod'] = toJST((string)$url->lastmod);
        $list[] = $sitemap;
        echo ' sitemap:' . $sitemap['lastmod'] . ' ' . $sitemap['loc'] .PHP_EOL;
    }
}

//更新日の新しい順に並び替え（変わってないものはリクエスト不要）
foreach($list as $n => $sitemap){
    $sort_keys[$n] = $sitemap['lastmod'];
}
array_multisort($sort_keys, SORT_DESC, $list);

echo '=== URL ====' . PHP_EOL;
echo count($list)   . PHP_EOL;
echo '============' . PHP_EOL;

//Indexing API
$client = new Google_Client();
$client->setAuthConfig($credentialFile);
$client->addScope(Google_Service_Indexing::INDEXING);
$httpClient = $client->authorize();
$endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

$results = [];
foreach($list as $n=>$sitemap){
    if($limitPublishPerDay <= $n){
        break;
    }
    
    $param = [];
    $param['type'] = 'URL_UPDATED';
    $param['url'] = $sitemap['loc'];
    
    $response = $httpClient->post($endpoint, ['json' => $param]);
    $body = $response->getBody()->getContents();
    $json = json_decode($body, true);
    
    $status = $response->getStatusCode();
    $results[$status] = ($results[$status]??0) + 1;

    if($status==200){
        $time = toJST($json["urlNotificationMetadata"]["latestUpdate"]["notifyTime"]);
        echo $status . ':' . $response->getReasonPhrase() . '|' . $sitemap['loc'] . '|' .$time .PHP_EOL;
    }else{
        $message = $json['error']['message']??'-';
        echo $status . ':' . $response->getReasonPhrase() . '|' . $sitemap['loc'] . '|-|' .$message .PHP_EOL;
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
