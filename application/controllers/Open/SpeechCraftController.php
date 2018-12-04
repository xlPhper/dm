<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_SpeechCraftController extends OpenBase
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
            $CategoryID = (int)$this->_getParam("CategoryID",0);
            $IsContent = (int)$this->_getParam("IsContent",0);
            $IsImages = (int)$this->_getParam("IsImages",0);
            $IsAudio = (int)$this->_getParam("IsAudio",0);
            $IsVideo = (int)$this->_getParam("IsVideo",0);

            $Content = trim($this->_getParam("Content"));
            $model = new Model_Open_SpeechCraft();
            $select = $model->fromMasterDB()->select()->setIntegrityCheck(false);
            if (!empty($CategoryID)){
                $select->where("CategoryID = ?",$CategoryID);
            }
            if (!empty($Content)){
                $select->where("Content like ? or Remark like ?","%".addslashes($Content)."%");
            }
            $bitWhere = [];
            if(!empty($IsContent)){
                $bitWhere[] = "Type & 1 != 0";
            }
            if(!empty($IsImages)){
                $bitWhere[] = "Type & 2 != 0";
            }
            if(!empty($IsAudio)){
                $bitWhere[] = "Type & 4 != 0";
            }
            if(!empty($IsVideo)){
                $bitWhere[] = "Type & 8 != 0";
            }
            if(count($bitWhere)){
                $select->where(implode(" or ",$bitWhere));
            }

            $adminModel = new Model_Role_Admin();
            if($this->admin["IsSuper"] == "Y"){
                $DepartmentIDs = (new Model_Department())->getParentID($this->admin["CompanyId"]);
                if(!count($DepartmentIDs)){
                    $DepartmentIDs[] = -1;
                }
                $select->where("DepartmentID in (?)",$DepartmentIDs);
            }else{
                $DepartmentID = $adminModel->getDependentParentID($this->admin["AdminID"]);
                $DepartmentID = empty($DepartmentID)?-1:$DepartmentID;
                $select->where("DepartmentID = ?",$DepartmentID);
            }

            $select->order(["SpeechCraftID asc"]);
            $res = $model->getResult($select,$Page,$Pagesize);
            $related = array_column($res["Results"],"SpeechCraftID");
            $resourceModel = new Model_Open_Resource();
            $resourceArray = $resourceModel->batchGet($related);
            $resource = [];
            foreach ($resourceArray as $r) {
                $resource[$r["Related"]][] = $r;
            }
            foreach ($res["Results"] as &$r) {
                $r["ImageArray"] = json_decode($r["Images"],true);
                if(!empty($resource[$r["SpeechCraftID"]])){
                    $r["Resource"] = $resource[$r["SpeechCraftID"]];
                }else{
                    $r["Resource"] = [];
                }
            }
            //echo $select->__toString();exit;
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
        $SpeechCraftID = (int)$this->_getParam('SpeechCraftID',0);
        $model = new Model_Open_SpeechCraft();
        $row = $model->fetchRow("SpeechCraftID = {$SpeechCraftID}");
        if(!$row){
            $this->showJson(0,"not find");
        }
        $this->showJson(self::STATUS_OK,'',$row->toArray());
    }

    /**
     * 话术 添加/修改
     */
    public function addAction()
    {

        $SpeechCraftID = (int)$this->_getParam('SpeechCraftID',0);
        $CategoryID = (int)$this->_getParam("CategoryID",0);
        $Content = trim($this->_getParam("Content"));
        $Images = trim($this->_getParam("Images"));
        $Audio = trim($this->_getParam("Audio"));
        $Video = trim($this->_getParam("Video"));
        $Remark = trim($this->_getParam("Remark"));
        $DepartmentID = (int)$this->_getParam("DepartmentID",0);
        if(empty($DepartmentID)){
            $this->showJson(0,"请选择部门");
        }
        if (empty($CategoryID)){
            $this->showJson(0,"请选择类型");
        }
        $Type = 0;
        $Resources = [];
        if(!empty($Content)){
            $Type += Resource_Content;
        }
        $ImageArray = json_decode($Images,true);
        if (is_array($ImageArray) && count($ImageArray)) {
            $Type += Resource_Image;
            foreach ($ImageArray as $Image) {
                $Resources[] = [
                    "Type" => Resource_Image,
                    "Url"  => $Image,
                ];
            }
        }
        if (!empty($Audio)) {
            $Type += Resource_Audio;
            $Resources[] = [
                "Type" => Resource_Image,
                "Url"  => $Audio,
            ];
        }
        if (!empty($Video)) {
            $Type += Resource_Video;
            $Resources[] = [
                "Type" => Resource_Image,
                "Url"  => $Video,
            ];
        }
        $model = new Model_Open_SpeechCraft();

        $now = date("Y-m-d H:i:s");

        $data = [
            "CategoryID"   => $CategoryID,
            "DepartmentID" => $DepartmentID,
            "Content"      => $Content,
            "Images"       => $Images,
            "Audio"        => $Audio,
            "Video"        => $Video,
            "Remark"       => $Remark,
            "Type"         => $Type,
            "UpdateTime"   => $now,
        ];
        $db = $model->getAdapter();
        try {
            $db->beginTransaction();
            if ($SpeechCraftID){
                $model->update($data,['SpeechCraftID = ?'=>$SpeechCraftID]);
            }else{
                $data["AdminID"] = $this->getLoginUserId();
                $data["CreateTime"] = $now;
                $SpeechCraftID = $model->insert($data);
            }
            $resourceModel = new Model_Open_Resource();
            $resourceModel->batchAdd($DepartmentID,$Resources,$SpeechCraftID);
            $db->commit();
            $this->showJson(1,"更新成功");
        } catch (Exception $e) {
            $db->rollBack();
            $this->showJson(0,"更新失败：".$e->getMessage());
        }
    }

    /**
     * 删除话术
     */
    public function deleteAction()
    {
        $this->showJson(0, "禁止删除");
        try {
            $SpeechCraftID = (int)$this->_getParam('SpeechCraftID', 0);
            $model  = new Model_Open_SpeechCraft();
            $model->delete(['SpeechCraftID = ?' => $SpeechCraftID]);
            $this->showJson(self::STATUS_OK, '');
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '' . $e->getMessage());
        }
    }

    /**
     *  列表
     */
    public function categoryListAction()
    {
        try{
            $Page = $this->_getParam('Page',1);
            $Pagesize = $this->_getParam('Pagesize',100);
            $IsSuper = $this->_getParam("IsSuper","N");
            $model = new Model_Open_SpeechCraftCategory();
            $select = $model->fromMasterDB()->select()->setIntegrityCheck(false);

            $adminModel = new Model_Role_Admin();
            if($this->admin["IsSuper"] == "Y" || $IsSuper == "Y"){
                $DepartmentIDs = (new Model_Department())->getParentID($this->admin["CompanyId"]);
                if(!count($DepartmentIDs)){
                    $DepartmentIDs[] = -1;
                }
                $select->where("DepartmentID in (?)",$DepartmentIDs);
            }else{
                $DepartmentID = $adminModel->getDependentParentID($this->admin["AdminID"]);
                $DepartmentID = empty($DepartmentID)?-1:$DepartmentID;
                $select->where("DepartmentID = ?",$DepartmentID);
            }

            $select->order(["CategoryID asc"]);
//            var_dump($select->__toString());exit();
            $Res = $model->getResult($select,$Page,$Pagesize);
            $model->getFiled($Res["Results"],"DepartmentID","departments","Name","DepartmentName");
            $this->showJson(self::STATUS_OK,'',$Res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,''.$e->getMessage());
        }
    }

    /**
     * 详情
     */
    public function categoryInfoAction()
    {

        $CategoryID = (int)$this->_getParam('CategoryID',0);
        $model = new Model_Open_SpeechCraftCategory();
        $row = $model->fetchRow("CategoryID = {$CategoryID}");
        if(!$row){
            $this->showJson(0,"not find");
        }
        $this->showJson(self::STATUS_OK,'',$row->toArray());
    }

    /**
     * 话术 添加/修改
     */
    public function categoryAddAction()
    {
        try{
            $CategoryID = (int)$this->_getParam('CategoryID',0);
            $DepartmentID = (int)$this->_getParam('DepartmentID',0);
            $Name = $this->_getParam('Name','');
            $ParentID = (int)$this->_getParam("ParentID",0);
            if(empty($Name)){
                $this->showJson(0,"名称不能为空");
            }
            if(empty($DepartmentID)){
                $this->showJson(0,"请选择部门");
            }
            $model = new Model_Open_SpeechCraftCategory();
            $row = $model->fetchRow("Name = '{$Name}' and DepartmentID = {$DepartmentID} and CategoryID != {$CategoryID}");
            if($row){
                $this->showJson(0,"名称不能重复");
            }
            $data = [
                "Name"         => $Name,
                "ParentID"     => $ParentID,
                "DepartmentID" => $DepartmentID
            ];
            if ($CategoryID){
                $model->update($data,['CategoryID = ?'=>$CategoryID]);
            }else{
                $model->insert($data);
            }
            $this->showJson(self::STATUS_OK,'保存成功');
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'保存失败'.$e->getMessage());
        }
    }

    /**
     * 删除话术
     */
    public function categoryDeleteAction()
    {
        $this->showJson(0, "禁止删除");
        try {
            $CategoryID = (int)$this->_getParam('CategoryID',0);
            $model  = new Model_Open_SpeechCraftCategory();
            $model->delete(['CategoryID = ?' => $CategoryID]);
            $this->showJson(self::STATUS_OK, '');
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '' . $e->getMessage());
        }
    }
}