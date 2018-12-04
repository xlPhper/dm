<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/7/14
 * Time: 22:30
 */
class Weixin_CallbackController extends DM_Controller
{
    protected $_gzhInfo = null;

    public function init()
    {
        parent::init();
        $code = $this->_getParam('code');
        $gzhModel = new _Gzh();
        $gzhInfo = $gzhModel->getInfoByCode($code);
        if(isset($gzhInfo['GzhID'])){
            $this->_gzhInfo = $gzhInfo;
        }else{
            exit("error!");
        }
    }

    public function indexAction()
    {
        if(!$this->checkSignature()){
            exit;
        }else{
            if(isset($_GET['echostr'])) {
                $echoStr = $_GET["echostr"];
                echo $echoStr;
            }
        }
        $this->responseMsg();
    }

    private function responseMsg()
    {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if(!$postStr){
            $postStr = file_get_contents("php://input");
        }
        if (!empty($postStr)) {
            require APPLICATION_PATH . "/../library/Weixin/wxBizMsgCrypt.php";

            libxml_disable_entity_loader(true);
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);

            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $msgType = $postObj->MsgType;

            if($msgType == 'event') {
                if ($postObj->Event == 'CLICK') {
                    if($postObj->EventKey == 'woyaojinqun'){
                        //判断是否第一次点击
                        $clickModel = new Model_Gzh_Click;
                        if(!$clickModel->checkRepeat($this->gzhInfo['GzhID'], trim($postObj->FromUserName))){
                            //第一次点击
                            $txts = explode("======", $this->gzhInfo['ReplyMsg']);
                            $txt = trim($txts[1]);
                            //$this->replyTextMsg($fromUsername, $toUsername, $txt);
                            $this->sendText($fromUsername, $txt);
                            $mediaModel = new Model_Gzh_Media;
                            $mediaImg = $mediaModel->getLiebian($this->gzhInfo['GzhID']);
                            //$this->replyImageMsg($fromUsername, $toUsername, $mediaImg['WXMediaID']);
                            $this->sendImage($fromUsername, $mediaImg['WXMediaID']);

                            //加入统计
                            $statisticModel = new Model_Gzh_Statistic;
                            $statisticModel->addNumByGzh($this->gzhInfo['GzhID'], 'MENU', $fromUsername);
                        }else{
                            $txts = explode("======", $this->gzhInfo['ReplyMsg']);
                            $txt = trim($txts[2]);
                            $this->replyTextMsg($fromUsername, $toUsername, $txt);
                        }
                    }

                    if($postObj->EventKey == 'lijibaoming'){
                        if($this->gzhInfo['GzhID'] == 31){
                            $this->lijibaoming($fromUsername);
                        }
                    }
                }
            }
        }
    }

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $tmpArr = array($this->_gzhInfo['Token'], $timestamp, $nonce);
        // use SORT_STRING rule
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
}