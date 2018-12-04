<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_StathourController extends AdminBase
{

    public function listAction()
    {
        $page = $this->_getParam('Page',1);
        $pagesize = $this->_getParam('Pagesize',100);
        $date = $this->_getParam('Date',null);
        $hour = $this->_getParam('Hour',null);

        try{

            $model = new Model_StatHours();

            $select = $model->fromSlaveDB()->select();

            if ($date){
                $select->where('Date = ?',$date);
            }

            if ($hour){
                $select->where('Hour = ?',$hour);
            }

            $res = $model->getResult($select, $page, $pagesize);

            $this->showJson(1,'列表',$res);

        }catch(EXception $e){
            $this->showJson(0,'抛出异常'.$e->getMessage());
        }

    }



}