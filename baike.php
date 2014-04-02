<?php

function getEncyclopediaInfo ($name) {
    $name_gbk = iconv('utf-8', 'gbk', $name);  // 将字符转换成GBK编码，若文件为GBK编码可去掉本行
    $encode = urlencode($name_gbk);  // 对字符进行URL编码
    $url = "http://baike.baidu.com/list-php/dispose/searchword.php?word={$encode}&pic=1";
    $get_contents = httpGetRequest_baike($url);  // 获取跳转页内容
    $get_contents_gbk = iconv('gbk', 'utf-8', $get_contents);  // 将获取的网页转换成UTF-8编码，若文件为GBK编码可去掉本行
    preg_match("/URL=(\S+)'>/s", $get_contents_gbk, $out);  // 获取跳转后URL
    $real_link = "http://baike.baidu.com{$out[1]}";

    $get_contents2 = httpGetRequest_baike($real_link);  // 获取跳转页内容
    preg_match('#"Description"\scontent="(.+?)"\s\/\>#is', $get_contents2, $matchresult);
    if (isset($matchresult[1]) && $matchresult[1] != "") {
        return $matchresult[1];
    } else {
        return "抱歉，没有找到与“{$name}”相关的百科结果。";
    }
}


function httpGetRequest_baike ($url) {
    $headers = array(
        "User-Agent: Mozilla/5.0 (Windows NT 5.1; rv:14.0) Gecko/20100101 Firefox/14.0.1",
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language: en-us,en;q=0.5",
        "Referer: http://www.baidu.com/"
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $output = curl_exec($ch);
    curl_close($ch);

    if ($output === false) {
        return "cURL Error: ".curl_error($ch);
    }
    return $output;
}
