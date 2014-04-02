<?php

$weixin = new Weixin('ruchee', 'Ruchee的荒草园子');
$weixin->api();



class Weixin {

    private $wx;


    public function __construct ($token = '', $appname = '') {
        $this->wx = new WeixinCallback;
        $this->wx->set_token($token);
        $this->wx->set_appname($appname);
    }


    public function api () {
        if (!isset($_GET['echostr'])) {
            $this->wx->responseMsg();
        } else {
            $this->wx->valid();
        }
    }


    public function test ($param) {
        $this->wx->receiveText($param);
    }

}



class WeixinCallback {

    private $token   = '';
    private $appname = '';


    public function set_token ($token) {
        $this->token = $token;
    }


    public function set_appname ($appname) {
        $this->appname = $appname;
    }


    public function valid() {
        $echoStr = $_GET['echostr'];
        if ($this->checkSignature()) {
            exit($echoStr);
        }
    }


    private function checkSignature () {
        $signature = $_GET['signature'];
        $timestamp = $_GET['timestamp'];
        $nonce = $_GET['nonce'];
        $token = $this->token;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }


    public function responseMsg () {
        $postStr = $GLOBALS['HTTP_RAW_POST_DATA'];
        if (!empty($postStr)) {
            $this->logger("R {$postStr}");
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $RX_TYPE = trim($postObj->MsgType);

            switch ($RX_TYPE) {
            case 'event':
                $result = $this->receiveEvent($postObj);
                break;
            case 'text':
                $result = $this->receiveText($postObj);
                break;
            case 'image':
                $result = $this->receiveImage($postObj);
                break;
            case 'location':
                $result = $this->receiveLocation($postObj);
                break;
            case 'voice':
                $result = $this->receiveVoice($postObj);
                break;
            case 'video':
                $result = $this->receiveVideo($postObj);
                break;
            case 'link':
                $result = $this->receiveLink($postObj);
                break;
            default:
                $result = "未知的消息类型：{$RX_TYPE}";
                break;
            }
            $this->logger("T {$result}");
            echo $result;
        } else {
            exit('');
        }
    }


    /**
     * 事件推送
     */
    private function receiveEvent ($object) {
        $content = '';
        switch ($object->Event) {
        case 'subscribe':  // 订阅
            $content = "欢迎关注{$this->appname}";
            $content .= (!empty($object->EventKey)) ? ("\n来自二维码场景 ".str_replace('qrscene_', '', $object->EventKey)) : '';
            break;
        case 'unsubscribe':  // 取消订阅
            $content = '取消关注';
            break;
        case 'SCAN':  // 扫描场景
            $content = "扫描场景 {$object->EventKey}";
            break;
        case 'CLICK':  // 点击菜单
            switch ($object->EventKey) {
            case 'COMPANY':
                $content = "{$this->appname}提供互联网相关产品与服务";
                break;
            default:
                $content = "点击菜单：{$object->EventKey}";
                break;
            }
            break;
            case 'LOCATION':  // 允许上传地理位置
                $content = "上传位置：纬度 {$object->Latitude};经度 {$object->Longitude}";
                break;
            default:
                $content = "接收到一个新的事件：{$object->Event}";
                break;
        }
        $result = $this->transmitText($object, $content);
        return $result;
    }


    /**
     * 文本请求
     */
    private function receiveText ($object) {
        $keyword = trim($object->Content);

        if (preg_match('/^天气.+/', $keyword)) {
            // 新浪天气预报
            include __DIR__.'/weather.php';
            $city = mb_substr($keyword, 2, mb_strlen($keyword)-2, 'utf-8');
            $content = weather($city);
        } else {
            switch ($keyword) {
            case '占卜':
                // 每日宜忌
                $url = 'http://api100.duapp.com/almanac/?appkey=trialuser';
                $content = json_decode(file_get_contents($url), true);
                break;
            case '笑话':
                // 随机笑话
                $url = 'http://api100.duapp.com/joke/?appkey=trialuser';
                $content = json_decode(file_get_contents($url), true);
                break;
            default:
                // xiaoi机器人
                include __DIR__.'/xiaoi.php';
                $content = XiaoI($object->FromUserName, $keyword);
                break;
            }
        }
        if (is_array($content)) {
            if (isset($content[0]['PicUrl'])) {
                $result = $this->transmitNews($object, $content);
            } else if (isset($content['MusicUrl'])) {
                $result = $this->transmitMusic($object, $content);
            }
        } else {
            $result = $this->transmitText($object, $content);
        }
        return $result;
    }


    /**
     * 图片请求
     */
    private function receiveImage ($object) {
        $content = array("MediaId"=>$object->MediaId);
        $result = $this->transmitImage($object, $content);
        return $result;
    }


    /**
     * 位置请求
     */
    private function receiveLocation ($object) {
        $content = "您发送的是位置，纬度为：{$object->Location_X}；经度为：{$object->Location_Y}；缩放级别为：{$object->Scale}；位置为：{$object->Label}";
        $result = $this->transmitText($object, $content);
        return $result;
    }


    /**
     * 音频请求
     */
    private function receiveVoice ($object) {
        if (empty($object->Recognition)) {
            $content = array('MediaId' => $object->MediaId);
            $result = $this->transmitVoice($object, $content);
        } else {
            $content = "您刚才说的是：{$object->Recognition}";
            $result = $this->transmitText($object, $content);
        }
        return $result;
    }


    /**
     * 视频请求
     */
    private function receiveVideo ($object) {
        $content = array(
            'MediaId' => $object->MediaId,
            'ThumbMediaId' => $object->ThumbMediaId,
            'Title' => '',
            'Description' => ''
        );
        $result = $this->transmitVideo($object, $content);
        return $result;
    }


    /**
     * 链接请求
     */
    private function receiveLink ($object) {
        $content = "您发送的是链接，标题为：{$object->Title}；内容为：{$object->Description}；链接地址为：{$object->Url}";
        $result = $this->transmitText($object, $content);
        return $result;
    }


    /**
     * 封装文本
     */
    private function transmitText ($object, $content) {
        $textTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[text]]></MsgType>
            <Content><![CDATA[%s]]></Content>
            </xml>";
        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content);
        return $result;
    }


    /**
     * 封装图片
     */
    private function transmitImage ($object, $imageArray) {
        $itemTpl = "<Image>
            <MediaId><![CDATA[%s]]></MediaId>
            </Image>";

        $item_str = sprintf($itemTpl, $imageArray['MediaId']);

        $textTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[image]]></MsgType>
            $item_str
            </xml>";

        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }


    /**
     * 封装音频
     */
    private function transmitVoice ($object, $voiceArray) {
        $itemTpl = "<Voice>
            <MediaId><![CDATA[%s]]></MediaId>
            </Voice>";

        $item_str = sprintf($itemTpl, $voiceArray['MediaId']);

        $textTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[voice]]></MsgType>
            $item_str
            </xml>";

        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }


    /**
     * 封装视频
     */
    private function transmitVideo ($object, $videoArray) {
        $itemTpl = "<Video>
            <MediaId><![CDATA[%s]]></MediaId>
            <ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
            <Title><![CDATA[%s]]></Title>
            <Description><![CDATA[%s]]></Description>
            </Video>";

        $item_str = sprintf($itemTpl, $videoArray['MediaId'], $videoArray['ThumbMediaId'], $videoArray['Title'], $videoArray['Description']);

        $textTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[video]]></MsgType>
            $item_str
            </xml>";

        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }


    /**
     * 封装新闻
     */
    private function transmitNews ($object, $newsArray) {
        if (!is_array($newsArray)) {
            return;
        }
        $itemTpl = "<item>
            <Title><![CDATA[%s]]></Title>
            <Description><![CDATA[%s]]></Description>
            <PicUrl><![CDATA[%s]]></PicUrl>
            <Url><![CDATA[%s]]></Url>
            </item>
            ";
        $item_str = '';
        foreach ($newsArray as $item) {
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $newsTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[news]]></MsgType>
            <Content><![CDATA[]]></Content>
            <ArticleCount>%s</ArticleCount>
            <Articles>$item_str</Articles>
            </xml>";

        $result = sprintf($newsTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
        return $result;
    }


    /**
     * 封装音乐
     */
    private function transmitMusic ($object, $musicArray) {
        $itemTpl = "<Music>
            <Title><![CDATA[%s]]></Title>
            <Description><![CDATA[%s]]></Description>
            <MusicUrl><![CDATA[%s]]></MusicUrl>
            <HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
            </Music>";

        $item_str = sprintf($itemTpl, $musicArray['Title'], $musicArray['Description'], $musicArray['MusicUrl'], $musicArray['HQMusicUrl']);

        $textTpl = "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[music]]></MsgType>
            $item_str
            </xml>";

        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }


    /**
     * 针对不同的服务器环境设定不同的日志记录方法
     */
    private function logger ($log_content) {
        if (isset($_SERVER['HTTP_APPNAME'])) {  // SAE
            sae_set_display_errors(false);
            sae_debug($log_content);
            sae_set_display_errors(true);
        } else if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") {  // 非本地访问
            $max_size = 10000;
            $log_filename = "log.xml";
            // 日志文件过大则先删除
            if (file_exists($log_filename) and (abs(filesize($log_filename)) > $max_size)) {
                unlink($log_filename);
            }
            // 如果文件已存在则追加
            file_put_contents($log_filename, date('H:i:s')." ".$log_content."\r\n", FILE_APPEND);
        }
    }

}
