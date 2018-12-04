<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/11
 * Time: 14:50
 */
class Message_FriendgroupController extends DM_Controller
{
    public function sendAction()
    {
        $data['Type'] = $this->_getParam('Type');
        $data['Text'] = $this->_getParam('Text', '');
        $data['Media'] = $this->_getParam('Media', '');
        $WeixinID = $this->_getParam('WeixinID', 0);

        $model = new Model_Message_Friendgroup();
        if($WeixinID == 0){
            $weixinModel = new Model_Weixin();
            $weixinData = $weixinModel->getOnline();
            foreach($weixinData as $datum){
                $data['WeixinID'] = $datum['WeixinID'];
                $model->add($data);
            }
        }else{
            $data['WeixinID'] = $WeixinID;
            $model->add($data);
        }
        $this->showJson(1);
    }
}