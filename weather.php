<?php

function weather ($city = '') {
    $keyword = urlencode($city);
    $url = "http://api2.sinaapp.com/search/weather/?appkey=0020130430&appsecert=fa6095e113cd28fd&reqtype=text&keyword={$keyword}";

    $content = file_get_contents($url);
    $jsondata = json_decode($content, true);

    if ($jsondata) {
        $weather = $jsondata['text']['content'];
        if ($weather) {
            return $weather;
        }
        return '查询失败';
    }
    return '请输入合法的城市名';
}
