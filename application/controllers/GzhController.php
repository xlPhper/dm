<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/7/24
 * Ekko: 17:58
 */

class GzhController extends DM_Controller
{
    protected $AppID = 'wx66e826d34e8b45df';
    protected $AppSecret = 'a5b29cff4ca9ca7656fffaf91ea106f4';


    public function indxeAction()
    {
        echo 'isGzh';exit;
    }

    public function setTicketAction()
    {
        $ToKenModel = new Model_ToKen();
        $Data = $ToKenModel->getByAppID($this->AppID);
        $Time = time();
        $Type = 1;
        if ($Data) {
            if ((strtotime($Data['AddTime']) + $Data['Expires'] - 600) > $Time){
                $ToKen = $Data['AccessToken'];
            }else{
                $Type = 2;
            }
        } else {
            $Type = 3;
        }
        if ($Type!=1){
            $tokeninfo = $this->getToken($this->AppID, $this->AppSecret);
            if (isset($tokeninfo['errcode'])) {
                $this->showJson(0, "获取用户信息失败" . $tokeninfo['errcode'] . ":" . $tokeninfo['errmsg']);
            }
            $ToKenData = [
                'AccessToken' => $tokeninfo['access_token'],
                'Expires' => $tokeninfo['expires_in'],
                'AddTime' => date('Y-m-d H:i:s'),
            ];
            if ($Type==3){
                $ToKenData['AppID'] = $this->AppID;
                $ToKenModel->insert($ToKenData);
            }else{
                $ToKenModel->update($ToKenData,['AppID = ?' => $this->AppID]);
            }
            $ToKen = $tokeninfo['access_token'];

        }
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $ToKen;
        $data = json_encode([
            'expire_seconds' => 86400,
            'action_name' => 'QR_SCENE',
            'action_info' => array(
                'scene' => array('scene_id' => 8)
            )
        ]);
        $ticket = $this->curl($url, $data, false, false, 5);
        $ticket = json_decode($ticket);
        $ticketUrl = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$ticket->ticket;
        var_dump($ticketUrl);exit;
    }

    public function ticketAction()
    {

        //获得参数 signature nonce token timestamp echostr
        $nonce     = $_GET['nonce'];
        $token     = 'f0cc24f5baec2b412d1927fe3e813db3';
        $timestamp = $_GET['timestamp'];
        $echostr   = $_GET['echostr'];
        $signature = $_GET['signature'];
        //形成数组，然后按字典序排序
        $array = array();
        $array = array($nonce, $timestamp, $token);
        sort($array);
        //拼接成字符串,sha1加密 ，然后与signature进行校验
        $str = sha1( implode( $array ) );
        if( $str == $signature && $echostr ){
            //第一次接入weixin api接口的时候
            echo  $echostr;
            exit;
        }else{
            /*
             获得请求时POST:XML字符串
             不能用$_POST获取，因为没有key
              */
            $xml_str = file_get_contents("php://input");;
            if(empty($xml_str)){
                return '错误';
            }
            if(!empty($xml_str)){
                // 解析该xml字符串，利用simpleXML
                libxml_disable_entity_loader(true);
                //禁止xml实体解析，防止xml注入
                $request_xml = simplexml_load_string($xml_str, 'SimpleXMLElement', LIBXML_NOCDATA);
                //判断该消息的类型，通过元素MsgType
                switch ($request_xml->MsgType){
                    case 'event':
                        //判断具体的时间类型（关注、取消、点击）
                        $event = $request_xml->Event;
                        if ($event=='subscribe') { // 关注事件
                            if ($request_xml->EventKey){
                                $this->_doSubscribe($request_xml);
                            }else{
                                $this->_doSubscribe($request_xml);
                            }
                        }elseif ($event=='CLICK') {//菜单点击事件
                            $this->_doClick($request_xml);
                        }elseif ($event=='VIEW') {//连接跳转事件
                            $this->_doView($request_xml);
                        }else{

                        }
                        break;
                    case 'text'://文本消息
                        $this->_doText($request_xml);
                        break;
                    case 'image'://图片消息
                        $this->_doImage($request_xml);
                        break;
                    case 'voice'://语音消息
                        $this->_doVoice($request_xml);
                        break;
                    case 'video'://视频消息
                        $this->_doVideo($request_xml);
                        break;
                    case 'shortvideo'://短视频消息
                        $this->_doShortvideo($request_xml);
                        break;
                    case 'location'://位置消息
                        $this->_doLocation($request_xml);
                        break;
                    case 'link'://链接消息
                        $this->_doLink($request_xml);
                        break;
                }
            }
        }

    }
    private $_msg_template = array(
        'text' => '<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[%s]]></Content></xml>',//文本回复XML模板
        'image' => '<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[image]]></MsgType><Image><MediaId><![CDATA[%s]]></MediaId></Image></xml>',//图片回复XML模板
        'music' => '<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[music]]></MsgType><Music><Title><![CDATA[%s]]></Title><Description><![CDATA[%s]]></Description><MusicUrl><![CDATA[%s]]></MusicUrl><HQMusicUrl><![CDATA[%s]]></HQMusicUrl><ThumbMediaId><![CDATA[%s]]></ThumbMediaId></Music></xml>',//音乐模板
        'news' => '<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[news]]></MsgType><ArticleCount>%s</ArticleCount><Articles>%s</Articles></xml>',// 新闻主体
        'news_item' => '<item><Title><![CDATA[%s]]></Title><Description><![CDATA[%s]]></Description><PicUrl><![CDATA[%s]]></PicUrl><Url><![CDATA[%s]]></Url></item>',//某个新闻模板
    );

    /**
     * 发送文本信息
     * @param  [type] $to      目标用户ID
     * @param  [type] $from    来源用户ID
     * @param  [type] $content 内容
     * @return [type]          [description]
     */
    private function _msgText($to, $from, $content) {
        $response = sprintf($this->_msg_template['text'], $to, $from, time(), $content);
        die($response);
    }
    //关注后做的事件
    private function _doSubscribe($request_xml){
        //处理该关注事件，向用户发送关注信息
        $content = '你好!你是由用户ID为'.$request_xml->EventKey.'的用户添加的'.$request_xml->Ticket;
        $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $content);
    }

    private function _doText($request_xml){
        //接受文本信息
        $content = '你好';
        $this->_msgText($request_xml->FromUserName, $request_xml->ToUserName, $content);
    }
    /**
     * 获取access_token
     * @param $AppID
     * @param $AppSecret
     */
    public function getToken($AppID,$AppSecret)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$AppID.'&secret='.$AppSecret;
        $result = $this->curl($url, [], false,false, 5);
        return json_decode($result,true);
    }

    public function curl($url,$fields, $ispost=true, $isJson = false, $timeout=30){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        if ($ispost){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }else{
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36');

        //禁止ssl验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($isJson) {
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8','Content-Length:' . strlen($fields)]);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch))
        {
            throw new \Exception(curl_error($ch),0);
        }
        else
        {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode)
            {
                return "http status code exception : ".$httpStatusCode;
            }
        }
        curl_close($ch);
        return $response;
    }

}