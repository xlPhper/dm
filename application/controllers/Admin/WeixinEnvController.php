<?php
require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_WeixinEnvController extends AdminBase
{
    /**
     * 列表
     */
    public function listAction()
    {
        $page       = (int)$this->_getParam('page',1);
        $pageSize   = (int)$this->_getParam('pagesize',100);
        $StartTime  = $this->_getParam('StartTime');
        $EndTime = $this->_getParam('EndTime');
        $WeixinID = $this->_getParam('WeixinID',[]);
        $model = new Model_Weixin_Env();
        $select     = $model ->select()->setIntegrityCheck(false);
        $select->from($model->getTableName()." as e");
        if(!empty($StartTime)){
            $select->where('e.CreateTime >= ?',$StartTime);
        }
        if(!empty($EndTime)){
            $EndTime = "$EndTime 23:59:59";
            $select->where('e.CreateTime <= ?',$EndTime);
        }
        if (is_array($WeixinID) && count($WeixinID) > 0){
            $select->where("e.WeixinID in (?)",$WeixinID);
        }
        $select->group(array("e.WeixinID"));
//        var_dump($select->__toString());exit();
        $res = $model->getResult($select,$page,$pageSize);
        $this->showJson(1,"",$res);
    }
    /**
     * 环境详情
     */
    public function infoAction()
    {
        $EnvID = (int)$this->_getParam("EnvID",0);
        $model = new Model_Weixin_Env();
        $Order = $model->fetchRow("EnvID = {$EnvID}")->toArray();
        $Detail = (new Model_Order_Detail())->fetchAll("EnvID = {$EnvID}")->toArray();
        $Order["Detail"] = $Detail;
        $this->showJson(1,'',$Order);
    }
    /**
     * 环境删除
     */
    public function deleteAction()
    {
        $this->showJson(0,"禁止删除");
        $EnvID = (int)$this->_getParam("EnvID",0);
        $model = new Model_Weixin_Env();
        $model->delete("EnvID = {$EnvID}");
        $this->showJson(1,"删除成功");
    }
}