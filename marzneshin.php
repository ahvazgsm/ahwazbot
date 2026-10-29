<?php
#-----------------------------#
function token_panelm($code_panel){
    $panel = select("marzban_panel","*","code_panel",$code_panel,"select");
    if (!is_array($panel) || empty($panel) || empty($panel['url_panel'])) {
        return array('error' => 'تنظیمات پنل سنایی/مرزنشین پیدا نشد یا ناقص است.');
    }
    if(!empty($panel['datelogin'])){
        $date = json_decode($panel['datelogin'],true);
        if(isset($date['time'])){
        $timecurrent = time();
        $start_date = time() - strtotime($date['time']);
        if($start_date <= 3600){
            return $date;
        }
        }
    }
    $url_get_token = rtrim($panel['url_panel'], '/').'/api/admins/token';
    $data_token = array(
        'username' => $panel['username_panel'],
        'password' => $panel['password_panel']
    );
    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT_MS => ($GLOBALS['request_exec_timeout'] ?? null) ?: 10000,
        CURLOPT_CONNECTTIMEOUT_MS => 5000,
        CURLOPT_POSTFIELDS => http_build_query($data_token),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'accept: application/json'
        )
    );
    $curl_token = curl_init($url_get_token);
    curl_setopt_array($curl_token, $options);
    $token = curl_exec($curl_token);
    if (curl_error($curl_token)) {
        $error = curl_error($curl_token);
        curl_close($curl_token);
        return array('error' => $error);
    }
    $httpCode = (int) curl_getinfo($curl_token, CURLINFO_HTTP_CODE);
    curl_close($curl_token);

    $body = json_decode((string) $token, true);
    if (!is_array($body)) {
        return array('error' => 'پاسخ نامعتبر از API پنل (HTTP '.$httpCode.').');
    }
    if ($httpCode >= 400) {
        return array('error' => $body['detail'] ?? $body['message'] ?? ('خطای ورود به پنل: HTTP '.$httpCode));
    }
    if(isset($body['access_token'])){
        $time = date('Y/m/d H:i:s');
        $data = json_encode(array(
            'time' => $time,
            'access_token' => $body['access_token']
            ));
        update("marzban_panel","datelogin",$data,'name_panel',$panel['name_panel']);
    }
    return $body;
}

#-----------------------------#

function getuserm($username_account,$location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panelm($marzban_list_get['code_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/users/' . $username_account;
    $headers = array(
            'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->get();
    return $response;
}
#-----------------------------#
function ResetUserDataUsagem($username_account,$location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panelm($marzban_list_get['code_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/users/' . $username_account.'/reset';
    $headers = array(
            'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->post(array());
    return $response;
}
function revoke_subm($username_account,$location)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panelm($marzban_list_get['code_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/users/' . $username_account.'/revoke_sub';
    $headers = array(
            'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->post(array());
    return $response;
}
#-----------------------------#
function adduserm($location,$data_limit,$username_ac,$timestamp,$name_product,$note ='',$data_limit_reset = 'no_reset')
{
    global $pdo;
    $product = select('product',"*","name_product",$name_product,"select");
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    if (!is_array($marzban_list_get) || empty($marzban_list_get['url_panel'])) {
        return array('error' => 'تنظیمات پنل انتخاب‌شده پیدا نشد.');
    }
    if (!is_array($product)) { $product = array(); }
    if(!empty($product['inbounds'])){
     $marzban_list_get['proxies'] = $product['inbounds'];   
    }
    $Check_token = token_panelm($marzban_list_get['code_panel']);
    if (!is_array($Check_token) || empty($Check_token['access_token'])) {
        return array('error' => $Check_token['error'] ?? $Check_token['detail'] ?? 'دریافت توکن پنل ناموفق بود.');
    }
    $serviceIds = json_decode($marzban_list_get['proxies'] ?? '[]', true);
    if (!is_array($serviceIds)) { $serviceIds = array(); }
    $data = array(
            'service_ids' => $serviceIds,
            "data_limit" => $data_limit,
            "username" => $username_ac,
            "note" => $note,
            "data_limit_reset_strategy" => $data_limit_reset,
        );
    if ($name_product == "usertest"){
        if($marzban_list_get['on_hold_test'] == "0"){
        if ($timestamp == 0) {
            $data["expire"] = null;
            $data["expire_strategy"] = "never";
        } else {
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            $formattedDate = $date->format('Y-m-d\TH:i:s');
            $data["expire_date"] = $formattedDate;
            $data["expire_strategy"] = "fixed_date";
        }
        }else{
            if($timestamp == 0 ){
                $data["expire_date"] = null;
            }else{
            $data["expire_date"] = null;
            $data["expire_strategy"] = "start_on_first_use";
            $data["usage_duration"] = $timestamp - time();
            }
        }
    }else{
        if($marzban_list_get['conecton'] == "offconecton"){
        if ($timestamp == 0) {
            $data["expire"] = null;
            $data["expire_strategy"] = "never";
        } else {
            $date = new DateTime();
            $date->setTimestamp($timestamp);
            $formattedDate = $date->format('Y-m-d\TH:i:s');
            $data["expire_date"] = $formattedDate;
            $data["expire_strategy"] = "fixed_date";
        }
        }else{
            if($timestamp == 0 ){
                $data["expire_strategy"] = "never";
                $data["expire_date"] = null;
            }else{
            $data["expire_date"] = null;
            $data["expire_strategy"] = "start_on_first_use";
            $data["usage_duration"] = $timestamp - time();
            }
        }
        }
    $payload = json_encode($data);
    $url = rtrim($marzban_list_get['url_panel'], '/')."/api/users";
    $headers = array(
            'accept: application/json',
            'Content-Type: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->post($payload);
    if (!is_array($response)) {
        return array('error' => 'پاسخ نامعتبر از API هنگام ساخت اکانت.');
    }
    if (!empty($response['status']) && (int)$response['status'] >= 400) {
        $err = json_decode($response['body'] ?? '', true);
        $response['error'] = is_array($err) ? ($err['detail'] ?? $err['message'] ?? ('HTTP '.$response['status'])) : ('HTTP '.$response['status']);
    }
    return $response;
}
//----------------------------------
function Get_System_Statsm($location){
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panelm($marzban_list_get['code_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/system/stats/users';
    $headers = array(
            'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->get();
    return $response;
}
//----------------------------------
function removeuserm($location,$username_account)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panelm($marzban_list_get['code_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/users/'.$username_account;
    $headers = array(
            'accept: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->delete();
    return $response;
}
//----------------------------------
function Modifyuserm($location,$username_account,array $data)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panelm($marzban_list_get['code_panel']);
    $payload = json_encode($data);
    $url =  $marzban_list_get['url_panel'].'/api/users/'.$username_account;
    $headers = array(
            'accept: application/json',
            'Content-Type: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->put($payload);
    return $response;
}
//----------------------------------
function enableuser($location,$username_account)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panelm($marzban_list_get['code_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/users/'.$username_account.'/enable';
    $headers = array(
            'accept: application/json',
            'Content-Type: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->post(array());
    return $response;
}
function disableduser($location,$username_account)
{
    $marzban_list_get = select("marzban_panel", "*", "name_panel", $location,"select");
    $Check_token = token_panelm($marzban_list_get['code_panel']);
    $url =  $marzban_list_get['url_panel'].'/api/users/'.$username_account.'/disable';
    $headers = array(
            'accept: application/json',
            'Content-Type: application/json'
    );
    $req = new CurlRequest($url);
    $req->setHeaders($headers);
    $req->setBearerToken($Check_token['access_token']);
    $response = $req->post(array());
    return $response;
}