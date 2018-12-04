<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/8
 * Time: 19:43
 */
class Group_UrlController extends DM_Controller
{
    public function testAction()
    {
        var_dump(1);exit;
    }
    /**
     * 根据公众号文章里面的内容获取群二维码
     */
    public function addAction()
    {
        $WeixinID = $this->_getParam('WeixinID', Model_Task::WEIXIN_FREE);
        $Url = $this->_getParam('Url');

        $groupUrlModel = new Model_Group_Url();
        $UrlID = $groupUrlModel->add($Url);

        if($UrlID) {
            $taskModel = new Model_Task();

            $RequestData = [
                'UrlID' =>  $UrlID,
                'Url' => $Url
            ];
            $taskModel->add($WeixinID, $taskModel::TYPE_GET_QRCODE_BY_ARTICLE, $RequestData);
        }
        $this->showJson(1);
    }

    /**
     * 将表中的字段添加到群表中
     */
    public function runAction()
    {
        $groupUrlModel = new Model_Group_Url();
        $groupModel = new Model_Group();

        $urlData = $groupUrlModel->getNotUsed();
        foreach($urlData as $datum) {
            $groupModel->add(Model_Weixin::STATUS_FREE, $datum['QRCode']);
            $groupUrlModel->setUsed($datum['UrlID']);
        }
    }

    public function updateQrcodeAction()
    {
        $groupUrlModel = new Model_Group_Url();
        $UrlID = $this->_getParam('UrlID');
        $QRCode = $this->_getParam('QRCode');
        if(empty($QRCode) || empty($UrlID)){
            $this->showJson(0, '无效的参数');
        }
        $groupUrlModel->updateQRCode($UrlID, $QRCode);
        $this->showJson(1);
    }
}