<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_ExceptionController extends AdminBase
{

    /**
     * 设备异常信息表信息列表
     */
    public function listAction()
    {
        try{
            $page = $this->_getParam('Page',1);
            $pagesize = $this->_getParam('Pagesize',100);

            $exception_model = new Model_Exception();

            $select = $exception_model->fromSlaveDB()->select();
            $select->order('ExceptionID DESC');
            $res = $exception_model->getResult($select, $page, $pagesize);

            $this->showJson(self::STATUS_OK,'',$res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常');
        }
    }

    public function infoAction()
    {
        try{
            $id = $this->_getParam('ExceptionID',null);

            if (!$id){
                $this->showJson(self::STATUS_FAIL,'请传ID');
            }

            $exception_model = new Model_Exception();

            $res = $exception_model->findByID($id);

            $this->showJson(self::STATUS_OK,'',$res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常');
        }
    }


}