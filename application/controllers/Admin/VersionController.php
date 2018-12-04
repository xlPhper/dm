<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_VersionController extends AdminBase
{

    /**
     * 版本号信息列表
     */
    public function listAction()
    {
        try{
            $name = $this->_getParam('Name','');
            $page = $this->_getParam('Page',1);
            $pagesize = $this->_getParam('Pagesize',100);

            $version_model = new Model_Version();

            $select = $version_model->select();
            if ($name){
                $select->where('PackageName = ?',$name);
            }
            $select->where('IsTest = 0');
            $res = $version_model->getResult($select, $page, $pagesize);
            $this->showJson(self::STATUS_OK,'',$res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * 版本号详情
     */
    public function infoAction()
    {
        try{
            $version_id = $this->_getParam('VersionID','');
            $version_model = new Model_Version();
            $res = $version_model->findByID($version_id);
            $this->showJson(self::STATUS_OK,'',$res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常');
        }
    }

    public function saveAction()
    {
        try{
            $data = array();
            $version_id = $this->_getParam('VersionID','');
            $data['PackageName'] = $this->_getParam('PackageName','');
            $data['VersionCode'] = $this->_getParam('VersionCode','');
            $data['VersionName'] = $this->_getParam('VersionName','');
            $data['Describe']    = $this->_getParam('Describe','');
            $data['DownloadUrl'] = $this->_getParam('DownloadUrl','');
            $data['Source']       = $this->_getParam('Source',2);
            $data['UpdateTime'] = date('Y-m-d H:i:s');
            if(!in_array($data['Source'], [1, 2, 3])){
                $this->showJson(0,'来源状态非法');
            }
            $version_model = new Model_Version();
            if ($version_id){
                $res = $version_model->update($data,['VersionID = ?'=>$version_id]);
            }else{
                $res = $version_model->insert($data);
            }
            if ($res){
                $this->showJson(self::STATUS_OK,'设置成功');
            }else{
                $this->showJson(self::STATUS_FAIL,'设置失败');
            }
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常');
        }
    }

    public function delAction()
    {
        try{
            $version_id = $this->_getParam('VersionID','');
            $version_model = new Model_Version();
            $res = $version_model->delete(['VersionID = ?'=>$version_id]);
            if ($res){
                $this->showJson(self::STATUS_OK,'删除成功');
            }else{
                $this->showJson(self::STATUS_FAIL,'删除失败');
            }
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常');
        }
    }


}