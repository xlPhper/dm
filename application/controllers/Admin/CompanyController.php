<?php
/**
 * Created by PhpStorm.
 * User: McGrady
 * Date: 2018/11/14
 * Time: 12:39
 */
require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_CompanyController extends AdminBase
{
    /**
     * 公司列表页
     */
    public function listAction(){
        $page               = $this->_getParam('Page',1);
        $pagesize           = $this->_getParam('Pagesize',100);
        $companyName        = trim($this->_getParam('CompanyName'));
        $deviceAllocation   = $this->_getParam('WeixinIds');

        $cModel = new Model_Company();
        $aModel = new Model_Role_Admin();

        $select = $cModel->fromSlaveDB()->select()->from($cModel->getTableName().' as c',['c.CompanyID','CompanyName','WeixinIds','a.Username','a.AdminID','AddTime'])->setIntegrityCheck(false);
        $select->joinLeft('admins as a','c.CompanyID=a.CompanyId',['Username']);
        $select->where('c.DeleteTime = ?','0000-00-00 00:00:00')->where('a.IsSuper = ?','Y');

        if($companyName){
            $select->where('c.CompanyName = ?',$companyName);
        }
        if($deviceAllocation){
            $devicesIds = explode(',', $deviceAllocation);
            $devicesWheres = [];
            foreach ($devicesIds as $Id) {
                $devicesWheres[] = 'FIND_IN_SET(' . (int)$Id. ', WeixinIds)';
            }
            $select->where(implode(' OR ', $devicesWheres));
        }

        $res = $cModel->getResult($select,$page,$pagesize);

        foreach ($res['Results'] as &$value){
            $value['Creater']   = $value['Username'];
            $value['YyAdminID'] = Helper_OpenEncrypt::encrypt($value['AdminID']);
//            unset($value['AdminID']);
        }

        $this->showJson(1,'success',$res);
    }

    /**
     * 添加编辑公司分配设备
     */
    public function saveAction(){
        $companyName        = $this->_getParam('CompanyName');
        $companyId          = $this->_getParam('CompanyID','');
        $categoryId         = $this->_getParam('CategoryId','');

        $userName               = strtolower(trim($this->_getParam('Username')));
        $passWord               = $this->_getParam('Password');
        $repeatPassWord         = $this->_getParam('RepeatPassWord');

        if(!$companyName){
            $this->showJson(0,'公司名称必填');
        }
        if(!$categoryId){
            $this->showJson(0,'请选择设备标签');
        }
        if(!$userName){
            $this->showJson(0,'请填写用户名');
        }
        if(!preg_match('/^[0-9a-zA-Z]+$/',$userName)){
            $this->showJson(0,'用户名格式不合法');
        }

        $cModel = new Model_Company();
        $aModel = new Model_Role_Admin();

        $cateS = $this->categoryList($categoryId);
        $deviceAllocation = implode(',',$cateS);
        $companyData = [
            'CompanyName'     => trim($companyName),
            'WeixinIds'       => $deviceAllocation,
            'CategoryId'      => $categoryId,
            'AdminId'         => $this->getLoginUserId(),
//            'AdminId'         => 1,
        ];

        $adminData = [
            'Username'      => $userName,
            'Status'        => 1,
            'IsSuper'       => 'Y',
//            'DepartmentID'  => 0,
        ];

        try{
            $cModel->getAdapter()->beginTransaction();

                if($companyId){
                    
                    $adminId = $this->_getParam('SuperAdminId');
                    if(!$adminId){
                        throw new \Exception('admin参数错误');
                    }

                    if($passWord && $repeatPassWord && ($passWord!=$repeatPassWord)){
                        throw new \Exception('两次密码输入不一致');
                    }
                    $cModel->update($companyData, ['CompanyID = ?' => $companyId]);

                    if($passWord){
                        $adminData['Password'] = md5($passWord);
                    }

                    $aCheck = $aModel->select()
                        ->where('Username = ?',$userName)
                        ->where('AdminID != ?' , $adminId)
                        ->query()
                        ->fetch();
                    if($aCheck){
                        throw new \Exception('用户名已存在');
                    }
                    $aModel->update($adminData,['CompanyId = ?' =>$companyId,'IsSuper = ?' =>'Y']);

                }else{
                    if(!$passWord || !$repeatPassWord ){
                        throw new \Exception('密码不能为空');
                    }
                    if($passWord!=$repeatPassWord){
                        throw new \Exception('两次密码输入不一致');
                    }
                    $aCheck = $aModel->select()->where('Username = ?',$userName)->query()->fetch();
                    if($aCheck){
                        throw new \Exception('用户名已存在');
                    }

                    $companyData['AddTime']         = date('Y-m-d H:i:s');
                    $companyId = $cModel->insert($companyData);
                    $adminData['Password'] = md5($passWord);
                    $adminData['CompanyId'] = $companyId;
                    $aModel->insert($adminData);
                }

            $cModel->getAdapter()->commit();
            $this->showJson(1,'操作成功',['CompanyId'=> $companyId]);
        }catch (\Exception $e){
            $cModel->getAdapter()->rollBack();
            $this->showJson(0, $e->getMessage());
        }

    }


    /**
     * 删除
     */
    public function deleteAction(){
        $companyId          = $this->_getParam('CompanyID');
        if(!$companyId){
            $this->showJson(0,'参数公司id不能为空');
        }

        $cModel = new Model_Company();
        $aModel = new Model_Role_Admin();

        $company = $aModel->fetchRow(['CompanyId = ?' => $companyId]);
        if (!$company) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }

        try {
            $data = [
                'DeleteTime'  => date('Y-m-d H:i:s'),
            ];
            //对公司表进行软删除
            $cModel->update($data,['CompanyID = ?'=> $companyId]);

            $company->delete();
            $this->showJson(1,'操作成功');
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '删除失败,err:'.$e->getMessage());
        }

    }

    public function detailAction(){
        $companyId = (int)$this->_getParam('CompanyId');
        if ($companyId < 1) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }
        $cModel = new Model_Company();
        $aModel = new Model_Role_Admin();

        $company = $cModel->fetchRow(['CompanyId = ?' => $companyId]);
        if (!$company) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }

        $select = $cModel->fromSlaveDB()->select()->from($cModel->getTableName().' as c',['CompanyID','CompanyName','WeixinIds','CategoryId','AdminID','AddTime'])->setIntegrityCheck(false);
        $select->joinLeft('admins as a','a.CompanyId = c.CompanyID',['Username','AdminID as SuperAdminId']);
        $select->where('c.DeleteTime = ?','0000-00-00 00:00:00')
            ->where('c.CompanyID = ?',$companyId)
            ->where('a.IsSuper = ?','Y');
        $res = $cModel->fetchRow($select)->toArray();

        $adminSelect = $aModel->fromSlaveDB()->select()->from('admins',['Username']);
        $result = $adminSelect->where('AdminID = ?',$res['AdminID'])->query()->fetch();
        $res['Creater']   = $result['Username'];
        $this->showJson(1,'',$res);
    }


    public function categoryList($category){
        $data = [];
        if($category){
            $wModel = new Model_Weixin();
            $select = $wModel->select()->from($wModel->getTableName(),['WeixinID']);
            $where_msg ='';
            $cateS = explode(',',$category);
            foreach($cateS as $w){
                $where_msg .= "FIND_IN_SET(".$w.",CategoryIds) OR ";
            }
            $where_msg = rtrim($where_msg,'OR ');
            $select->where($where_msg);
            $weixins = $wModel->fetchAll($select);

            foreach ($weixins as $value){
                $data[] = $value['WeixinID'];
            }
            return $data;
        }else{
            return $data;
        }

    }

}