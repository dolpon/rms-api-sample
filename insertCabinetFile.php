<?php

/* 
詳細解説記事はこちら  
https://virusee.net/rms-api-cabinetfile-insert/
*/

require_once('config.php');
require_once('util.php');
require_once('class/cabinetFileSetting.php');
require_once('class/cabinetUploadFileInfo.php');

ini_set('xdebug.var_display_max_children', -1);
ini_set('xdebug.var_display_max_data', -1);
ini_set('xdebug.var_display_max_depth', -1);


/***
 * 画像情報のセット
 * */

// 送信したいファイルの情報設定
$cabinetUploadFileInfo = new CabinetUploadFileInfo(__DIR__ . '/image/test_image.jpg'); // 送信したいファイルの絶対パスを指定 mimeとかいろんな情報をconstructerでメンバーに設定している
// NOTE: $cabinetUploadFileInfo->extensionがnullの場合対応していない拡張子のファイルなのでエラーを起こした方が良い

// customVarDump($cabinetUploadFileInfo);

// 画像の名前やパスなど
$cabinetFileSetting = new CabinetFileSetting();
$cabinetFileSetting->fileName = 'test_' . randomStr(3) . '_' . date_format(new DateTime('now', new DateTimeZone('Asia/Tokyo')), 'YmdHis');
$cabinetFileSetting->folderId = 0; // 0は基本フォルダ folderIdはcabinet.folders.getで確認可能
$cabinetFileSetting->filePath = basename($cabinetUploadFileInfo->filePath); // 拡張子つける 送信する実ファイルの名前を正確に「hoge.jpg」とか
$cabinetFileSetting->overWrite = "true"; // 文字列指定でなければならない「true」とか「false」とか。overWriteがtrueかつfilePathの指定がある場合、filePathをキーとして画像情報を上書きすることができます

// 楽天へRMS APIを使って画像アップロード
list($reqXml, $httpStatusCode, $response) = cabinetFileInsert($cabinetFileSetting, $cabinetUploadFileInfo);



//////////////// 関数群 ////////////////////

/**
* APIのリクエストを行う
* xmlを作って file_get_contentsでpostしてる
* @see https://virusee.net/rms-api-cabinetfile-insert/
* @param $cabinetFileSetting 挿入したい画像設定のクラスオブジェクト
* @param $cabinetUploadFileInfo 挿入したい画像ファイルのオブジェクト
* @return リクエストしたxml文字列, httpステータスコード, レスポンス文字列(xmlで返ってくる)
*/
function cabinetFileInsert($cabinetFileSetting, $cabinetUploadFileInfo) {
  $authkey = base64_encode(RMS_SERVICE_SECRET . ':' . RMS_LICENSE_KEY);

  $url = RMS_API_CABINET_FILE_INSERT;
  
  $reqXml = _createRequestXml($cabinetFileSetting);
  
  $cabinetUploadFileInfoArray = array(
      'file' => $cabinetUploadFileInfo //アップロードするファイルのCabinetUploadFileInfoオブジェクトを渡す
  );
  
  $params = array(
      'xml' => $reqXml
  );
  
  list($response,$httpStatusCode) = httpPost($url, $params, $cabinetUploadFileInfoArray);
  return array($reqXml, $httpStatusCode, $response);
}

/***
 * 指定したURLに指定したパラメータのリクエストとファイルアップロードのPOSTを行う
 * 
 * @param $url POSTするURL
 * @param $params リクエストパラメーター
 * @param $cabinetUploadFileInfoArray アップロードするファイル CabinetUploadFileInfoオブジェクト
 * 
 * */
function httpPost($url, $params, $cabinetUploadFileInfoArray = []){
    $isMultipart = (count($cabinetUploadFileInfoArray)) ? true : false;
    $authkey = base64_encode(RMS_SERVICE_SECRET . ':' . RMS_LICENSE_KEY);

    // RMSのファイル設定部分
    $boundary = md5(mt_rand() . microtime());
    $contentType = "Content-Type: multipart/form-data; boundary=\"$boundary\"";
    $data = '';
    foreach($params as $key => $value) {
        $data .= "--$boundary" . "\r\n";
        $data .= 'Content-Disposition: form-data; name=' . '"'. $key . '"' . "\r\n" . "\r\n";
        $data .= $value . "\r\n";
    }
    // ファイルアップロード部分
    foreach($cabinetUploadFileInfoArray as $key => $cabinetUploadFileInfo) {
        $data .= "--$boundary" . "\r\n";
        $data .= sprintf('Content-Disposition: form-data; name="%s"; filename="%s"%s', $key, basename($cabinetUploadFileInfo->filePath), "\r\n");
        $data .= 'Content-Type: '. $cabinetUploadFileInfo->mimeType . "\r\n" . "\r\n";
        $data .= file_get_contents($cabinetUploadFileInfo->filePath) . "\r\n";
    }
    $data .= "--$boundary--";

    $headers = array(
        "Connection: keep-alive",
        "Proxy-Connection: keep-alive",
        $contentType,
        'Content-Length: '.strlen($data),
        "Authorization: ESA {$authkey}",
        "Accept: */*",
        // "Accept-Encoding: gzip,deflate",
        // "Accept-Language: ja,en-US;q=0.8,en;q=0.6"
    );
    $header = implode("\r\n", $headers);
    customVarDump($header);
    customVarDump($data);

    $options = array('http' => array(
        'method'  => 'POST',
        'ignore_errors' => true, //trueにすると40x,50x系のエラーでも内容を取得できる。
        'content' => $data,
        'header'  => $header
    ));

    $response = file_get_contents($url, false, stream_context_create($options));
    
    $httpResponseHeader = implode("\r\n", $http_response_header);
    $httpStatusCode = extract_response_http_code($httpResponseHeader);

    return array($response, $httpStatusCode);
}

/*
* 渡したclassオブジェクトからリクエストのXMLを自動生成する
*/
function _createRequestXml($cabinetFile) {

  // リクエストXMLのガワを作る
  $rootXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><request/>');
  $fileInsertRequestXml = $rootXml->addChild('fileInsertRequest');
  $fileXml = $fileInsertRequestXml->addChild('file');
  
  // 受け取った商品情報オブジェクトをarrayに変換
  $array = _convertClassObjectToArray($cabinetFile);
  
  _arrayToXml($array, $fileXml);  // リクエストのXMLをarray情報から作成する
  
  return $rootXml->asXML(); // リクエストのXMLを返却する
}

/**
 * Convert an array to XML
 * @param array $array
 * @param SimpleXMLElement $xml
 * @param array $parentKeyName (その要素が配列で、子要素を親要素の単数形にして登録したい時指定)
 */
function _arrayToXml($array, &$xml, $parentKeyName=null){
  foreach ($array as $key => $value) {
    if(is_array($value)){
      if(is_int($key)){
          if(!empty($parentKeyName)) {
            // 親要素が存在する時、子要素を親要素の単数形の名前にして登録
            $key = singularByPlural($parentKeyName);
          }
      }
      $label = $xml->addChild($key);
      _arrayToXml($value, $label, $key);
    }
    else if(!is_null($value)){
      // 値がセットされている時だけxml要素に追加
      $xml->addChild($key, $value);
    }
  }
}

/**
 * Convert an classObject to array
 */
function _convertClassObjectToArray($object) {
  $json = json_encode($object);
  return (array)json_decode($json, true);
}


//////////////// 結果をブラウザで表示 ////////////////////

?>

<!DOCTYPE html>
<html>
  <head>
    <title>cabinet.file.insert | CabinetAPI</title>
    <meta charset="UTF-8">
    <style>
      pre,code {
        width:100%;
        overflow: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
      }
    </style>
  </head>
  <body>
    <div style="width:100%;">
      <h1>リクエスト</h1>
      <pre>
        <?php echo htmlspecialchars($reqXml, ENT_QUOTES);; ?>
      </pre>
      <h1>レスポンス結果</h1>
      <h2>HTTP Status code</h2>
      <pre>
        <?php echo $httpStatusCode; ?>
      </pre>
      <h2>生レスポンス</h2>
      <pre>
        <?php 
          error_log(print_r($response, true));
          echo htmlspecialchars(returnFormattedXmlString($response) , ENT_QUOTES); ?>
      </pre>
    </div>
  </body>
</html>

