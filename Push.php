<?php

function https_request($url, $data = null) {
    # 初始化一个cURL会话
    $curl = curl_init();
    //设置请求选项, 包括具体的url
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);  //禁用后cURL将终止从服务端进行验证
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    if (!empty($data)) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);  //设置具体的post数据
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($curl);  //执行一个cURL会话并且获取相关回复

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    echo $httpCode;
    curl_close($curl);  //释放cURL句柄,关闭一个cURL会话
    return $response;
}

var_dump(https_request('http://192.168.1.246:9550', [
    'type'=>'peer',// peer 为点对点推送 group 群推  notice 全部推送
    'user_id' => '125', 
    'content' => '点对点推送信息'
]));

