<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_ResourceController extends OpenBase
{

    public function indexAction()
    {

    }

    /**
     *  列表
     */
    public function listAction()
    {
        try{
            $Page = $this->_getParam('Page',1);
            $Pagesize = $this->_getParam('Pagesize',100);
            $Type = (int)$this->_getParam("Type",0);
            $model = new Model_Open_Resource();
            $select = $model->fromMasterDB()->select()->setIntegrityCheck(false);
            if (!empty($Type)){
                $select->where("Type = ?",$Type);
            }
            $DepartmentID = (int)$this->_getParam("DepartmentID",-1);
//            $admin = (new Model_Role_Admin())->getInfoByID($this->getLoginUserId());
//            $DepartmentID = empty($admin["DepartmentID"])?0:$admin["DepartmentID"];
            if (!empty($DepartmentID)){
                $select->where("DepartmentID = ?",$DepartmentID);
            }
            $select->order(["ResourceID desc"]);
            $res = $model->getResult($select,$Page,$Pagesize);
            $this->showJson(self::STATUS_OK,'',$res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,''.$e->getMessage());
        }
    }

    /**
     * 详情
     */
    public function infoAction()
    {
        $ResourceID = (int)$this->_getParam('ResourceID',0);
        $model = new Model_Open_Resource();
        $row = $model->fetchRow("ResourceID = {$ResourceID}");
        if(!$row){
            $this->showJson(0,"not find");
        }
        $this->showJson(self::STATUS_OK,'',$row->toArray());
    }

    /**
     * 七牛资源 添加/修改
     */
    public function addAction()
    {
        try{
            $ResourceID = (int)$this->_getParam('ResourceID',0);
            $Url = trim($this->_getParam("Url"));
            $Type = (int)$this->_getParam("Type",0);
            $Source = (int)$this->_getParam("Source",1);
            $Related = (int)$this->_getParam("Related",0);

            $model = new Model_Open_Resource();
            $now = date("Y-m-d H:i:s");
            $DepartmentID = (new Model_Role_Admin())->getDependentParentID($this->getLoginUserId());
            if(in_array($Type,[Resource_Image,Resource_Audio,Resource_Video])){
                $this->showJson(0,"类型错误");
            }
            $data = [
                "DepartmentID" => $DepartmentID,
                "Url"          => $Url,
                "Type"         => $Type,
                "Source"       => $Source,
                "Related"      => $Related,
                "UpdateTime"   => $now,
            ];
            if ($ResourceID){
                $model->update($data,['ResourceID = ?'=>$ResourceID]);
            }else{
                $data["AdminID"] = $this->getLoginUserId();
                $data["CreateTime"] = $now;
                $model->insert($data);
            }
            $this->showJson(self::STATUS_OK,'保存成功');
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'保存失败'.$e->getMessage());
        }
    }


    /**
     * 删除七牛资源
     */
    public function deleteAction()
    {
        $this->showJson(0, "禁止删除");
        try {
            $ResourceID = (int)$this->_getParam('ResourceID', 0);
            $model  = new Model_Open_Resource();
            $model->delete(['ResourceID = ?' => $ResourceID]);
            $this->showJson(self::STATUS_OK, '');
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '' . $e->getMessage());
        }
    }
}