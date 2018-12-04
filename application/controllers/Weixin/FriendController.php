<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/11
 * Time: 16:46
 */
class Weixin_FriendController extends DM_Controller
{
    public function acceptAction()
    {
        $data['WeixinID'] = $this->_getParam("WeixinID");
        $data['Account'] = $this->_getParam("Account", "");
        $data['Alias'] = $this->_getParam("Alias", "");
        $data['NickName'] = $this->_getParam("NickName", "");
        $data['Avatar'] = $this->_getParam("Avatar", "");

        if(empty($data['WeixinID'])){
            $this->showJson(0, "微信ID不能为空");
        }
        if(empty($data['Account']) && empty($data['Alias'])){
            $this->showJson(0, "无账号信息");
        }

        $friendModel = new Model_Weixin_Friend();
        $friendModel->add($data);
        $this->showJson(1);
    }

}