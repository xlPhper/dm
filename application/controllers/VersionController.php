<?php

class VersionController extends DM_Controller
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
            $this->showJson(1,'',$res);
        }catch(Exception $e){
            $this->showJson(0,'抛出异常'.$e->getMessage());
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
            $this->showJson(1,'',$res);
        }catch(Exception $e){
            $this->showJson(0,'抛出异常');
        }
    }

    public function editAction()
    {
        try{
            $data = array();
            $version_id = $this->_getParam('VersionID','');
            $data['PackageName'] = $this->_getParam('PackageName','');
            $data['VersionCode'] = $this->_getParam('VersionCode','');
            $data['VersionName'] = $this->_getParam('VersionName','');
            $data['Describe'] = $this->_getParam('Describe','');
            $data['DownloadUrl'] = $this->_getParam('DownloadUrl','');
            $data['UpdateTime'] = date('Y-m-d H:i:s');
            $version_model = new Model_Version();
            if ($version_id){
                $res = $version_model->update($data,['VersionID = ?'=>$version_id]);
            }else{
                $res = $version_model->insert($data);
            }
            if ($res){
                $this->showJson(1,'设置成功');
            }else{
                $this->showJson(0,'设置失败');
            }
        }catch(Exception $e){
            $this->showJson(0,'抛出异常');
        }
    }

}