<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_DevicesController extends AdminBase
{

    public function listAction()
    {
        $page = $this->_getParam('Page',1);
        $pagesize = $this->_getParam('Pagesize',100);
        $online = $this->_getParam('Online', null);
        $work_status = $this->_getParam('WorkStatus', null);
        $device_no = $this->_getParam('DeviceNO', null);
        $weixin = $this->_getParam('Weixin', null);
        $serialNum = trim($this->_getParam('SerialNum', ''));
        $hasNum = trim($this->_getParam('HasNum'));

        $device_model = new Model_Device();
        $select = $device_model->fromSlaveDB()->select()->from($device_model->getTableName(),['DeviceID','DeviceNO','WorkStatus','OnlineStatus','OnlineWeixin','OnlineTime','Software', 'SerialNum']);

        if ($online != null){
            $select->where('OnlineStatus = ?',$online);
        }
        if ($work_status != null){
            $select->where('WorkStatus = ?',$work_status);
        }
        if ($device_no != null){
            $select->where('DeviceNO like ?','%'.$device_no.'%');
        }
        if ($weixin != null){
            $select->where('OnlineWeixin like ?','%'.$weixin.'%');
        }
        if ($serialNum !== '') {
            $select->where('SerialNum like ?', '%'.$serialNum.'%');
        }
        if ($hasNum == 'Y') {
            $select->where("SerialNum != ''");
        }
        $select->order('DeviceID DESC');

        $res = $device_model->getResult($select,$page,$pagesize);
        foreach ($res['Results'] as &$val){
            // Software
            if ($val['Software']){
                $software_json = json_decode($val['Software']);
                foreach ($software_json as $edition){
                    if ($edition->name == 'MMAddContact'){
                        $val['MMAddContact'] = $edition->vName;
                    }
                    if($edition->name == '微信'){
                        $val['WeixinEdition'] = $edition->vName;
                    }
                }
            }else{
                $val['MMAddContact'] = '0.0.0';
                $val['WeixinEdition'] = '0.0.0';
            }
        }
        $this->showJson(1,'列表',$res);
    }





    /**
     * 忘了这个接口有木有用
     */
    public function savePositionAction()
    {
        $weixin_id = $this->_getParam('WeixinID',null);
        $position = $this->_getParam('Position',null);
        if (empty($weixin_id)) {
            $this->showJson(0, '参数不存在');
        }
        $weixin_id_data = explode(',', $weixin_id);
        $weixin_model = new Model_Weixin();
        foreach ($weixin_id_data as $wei) {
            $res = $weixin_model->update(['Position'=>$position],['WeixinID = ?' => $wei]);
            if (!$res) {
                $this->showJson(0, '修改失败');
            }
        }
        $this->showJson(1, '修改成功');

    }

}