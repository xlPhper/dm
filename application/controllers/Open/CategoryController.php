<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_CategoryController extends OpenBase
{
    /**
     * 客户分类列表
     */
    public function friendListAction()
    {
        $search = $this->getParam('Search', null);
        $state = $this->getParam('State', 2); // 1-查数量 2-不查数量
        $adminID = $this->_getParam('AdminID');

        $categoryModel = Model_Category::getInstance();
        $adminModel = Model_Role_Admin::getInstance();

        $adminId = $this->getLoginUserId();

        $adminInfo = $adminModel->getInfoByID($adminId);

        if ($adminInfo == false){
            $this->showJson(self::STATUS_FAIL, '管理员不存在');
        }

        $departmentID = [];

        if ($adminInfo['IsSuper'] == 'Y'){
            $departmentID = $adminModel->getDepartmentIDs($adminInfo['CompanyId']);
        }else{
            $departmentID[] = $adminInfo['DepartmentID'];
        }

        $res = $categoryModel->findByDepartmentID($search,$adminInfo['CompanyId'],$departmentID,CATEGORY_TYPE_WEIXINFRIEND,$adminID,PLATFORM_OPEN);

        if ($res == false){
            $this->showJson(self::STATUS_OK, '无查询结果',[]);
        }

        $categoryIds = [];
        if ($state == 1) {
            foreach ($res as $c) {
                $categoryIds[] = $c['CategoryID'];
            }

            $friendModel = Model_Weixin_Friend::getInstance();
            $categoryList = $friendModel->getFriendCategoryList($categoryIds);

            $categoryData = [];
            foreach ($categoryList as $d) {
                $categoryData[$d['CategoryID']] = $d['Num'];
            }

            foreach ($res as &$c) {
                $c['Num'] = empty($categoryData[$c['CategoryID']]) ? 0 : $categoryData[$c['CategoryID']];
            }
        }

        $this->showJson(self::STATUS_OK, '', $res);
    }

    /**
     * 素材分类列表
     */
    public function mateListAction()
    {
        $search = $this->getParam('Search', null);
        $state = $this->getParam('State', 2); // 1-查数量 2-不查数量
        $adminID = $this->_getParam('AdminID');


        $categoryModel = Model_Category::getInstance();
        $adminModel = Model_Role_Admin::getInstance();

        $adminId = $this->getLoginUserId();

        $adminInfo = $adminModel->getInfoByID($adminId);

        if ($adminInfo == false){
            $this->showJson(self::STATUS_FAIL, '管理员不存在');
        }

        $departmentID = [];

        if ($adminInfo['IsSuper'] == 'Y'){
            $departmentID = $adminModel->getDepartmentIDs($adminInfo['CompanyId']);
        }else{
            $departmentID[] = $adminInfo['DepartmentID'];
        }

        $res = $categoryModel->findByDepartmentID($search,$adminInfo['CompanyId'],$departmentID,CATEGORY_TYPE_MATE_TAG,$adminID,PLATFORM_OPEN);

        if ($res == false){
            $this->showJson(self::STATUS_OK, '无查询结果',[]);
        }

        $categoryIds = [];
        if ($state == 1) {
            foreach ($res as $c) {
                $categoryIds[] = $c['CategoryID'];
            }

            $materialsModel = Model_Materials::getInstance();
            $categoryList = $materialsModel->getMaterialCategoryList($categoryIds);

            $categoryData = [];
            foreach ($categoryList as $d) {
                $categoryData[$d['CategoryID']] = $d['Num'];
            }

            foreach ($res as &$c) {
                $c['Num'] = empty($categoryData[$c['CategoryID']]) ? 0 : $categoryData[$c['CategoryID']];
            }
        }

        $this->showJson(self::STATUS_OK, '', $res);
    }

    /**
     * 个号标签列表
     */
    public function weixinListAction()
    {
        $search = $this->getParam('Search', null);
        $state = $this->getParam('State', 2); // 1-查数量 2-不查数量
        $adminID = $this->_getParam('AdminID');


        $categoryModel = Model_Category::getInstance();
        $adminModel = Model_Role_Admin::getInstance();

        $adminId = $this->getLoginUserId();

        $adminInfo = $adminModel->getInfoByID($adminId);

        if ($adminInfo == false){
            $this->showJson(self::STATUS_FAIL, '管理员不存在');
        }

        $departmentID = [];

        if ($adminInfo['IsSuper'] == 'Y'){
            $departmentID = $adminModel->getDepartmentIDs($adminInfo['CompanyId']);
        }else{
            $departmentID[] = $adminInfo['DepartmentID'];
        }

        $res = $categoryModel->findByDepartmentID($search,$adminInfo['CompanyId'],$departmentID,CATEGORY_TYPE_WEIXIN,$adminID,PLATFORM_OPEN);

        if ($res == false){
            $this->showJson(self::STATUS_OK, '无查询结果',[]);
        }

        $categoryIds = [];
        if ($state == 1) {
            foreach ($res as $c) {
                $categoryIds[] = $c['CategoryID'];
            }

            $weixinModel = Model_Weixin::getInstance();
            $categoryList = $weixinModel->getWeixinCategoryList($categoryIds);

            $categoryData = [];
            foreach ($categoryList as $d) {
                $categoryData[$d['CategoryID']] = $d['Num'];
            }

            foreach ($res as &$c) {
                $c['Num'] = empty($categoryData[$c['CategoryID']]) ? 0 : $categoryData[$c['CategoryID']];
            }
        }

        $this->showJson(self::STATUS_OK, '', $res);
    }


    /**
     * 添加标签
     */
    public function addAction()
    {
        $name = $this->getParam('Name', null);
        $type = $this->getParam('Type', null);

        if (empty($name)) {
            $this->showJson(0, '标签名称非法');
        }
        if (empty($type)) {
            $this->showJson(0, '选择你需要查询分类的Type');
        }
        if (!in_array($type, CATEGORY_TYPES)) {
            $this->showJson(0, '分类不合法');
        }

        $categoryModel = Model_Category::getInstance();
        $adminModel = Model_Role_Admin::getInstance();

        $categroyName = $categoryModel->findCategoryByNameType($name, $type);
        if ($categroyName) {
            $this->showJson(0, '该分类已添加');
        }

        // 获取管理员ID
        $adminId = $this->getLoginUserId();
        $adminInfo = $adminModel->getInfoByID($adminId);

        $data = [
            'Name' => $name,
            'Type' => $type,
            'ParentID' => 0,
            'CreateDate' => date('Y-m-d H:i:s'),
            'AdminID' => empty($adminId) ? 0 : $adminId,
            'CompanyId' => empty($adminInfo['CompanyId']) ? 0 : $adminInfo['CompanyId'],
            'DepartmentID' => empty($adminInfo['DepartmentID']) ? 0 : $adminInfo['DepartmentID'],
            'Platform' => PLATFORM_OPEN
        ];
        $res = $categoryModel->insert($data);
        if ($res == false) {
            $this->showJson(self::STATUS_FAIL, '添加失败');
        } else {
            $this->showJson(self::STATUS_OK, '添加成功');
        }

    }

    /**
     * 删除分类
     */
    public function delAction()
    {
        set_time_limit(0);   // 设置脚本最大执行时间
        ini_set('memory_limit', '1024M');

        $categoryId = $this->_getParam('CategoryID', null);

        if (empty($categoryId)) {
            $this->showJson(0, '参数非法');
        }

        $categoryModel = Model_Category::getInstance();

        $category = $categoryModel->getCategoryById($categoryId);

        if ($category) {

            switch ($category['Type']) {

                // 个号标签
                case CATEGORY_TYPE_WEIXIN:

                    $weixinModel = Model_Weixin::getInstance();
                    $weixinList = $weixinModel->findIsYyWeixins($categoryId,PLATFORM_OPEN);

                    foreach ($weixinList as $w) {

                        // 个号的现有标签
                        $original_category = explode(',', $w['CategoryIds']);

                        // 要删除标签是否在标签中
                        $in_category = in_array($categoryId, $original_category);
                        if ($in_category) {
                            // 确定位置
                            $key = array_search($categoryId, $original_category);
                            // 删除
                            array_splice($original_category, $key, 1);
                        }
                        $update_data = ['YyCategoryIds' => implode(',', $original_category)];
                        $weixinModel->update($update_data, ['FriendID = ?' => $w['FriendID']]);
                    }
                    break;

                // [好友/客户]标签
                case CATEGORY_TYPE_WEIXINFRIEND:

                    $friendModel = Model_Weixin_Friend::getInstance();
                    $userList = $friendModel->findByCategoryID($categoryId);

                    foreach ($userList as $u) {

                        // 客户的现有标签
                        $original_category = explode(',', $u['CategoryIds']);

                        // 要删除标签是否在标签中
                        $in_category = in_array($categoryId, $original_category);
                        if ($in_category) {
                            // 确定位置
                            $key = array_search($categoryId, $original_category);
                            // 删除
                            array_splice($original_category, $key, 1);
                        }
                        $update_data = ['CategoryIds' => implode(',', $original_category)];
                        $friendModel->update($update_data, ['FriendID = ?' => $u['FriendID']]);
                    }
                    break;

                // 素材标签
                case CATEGORY_TYPE_MATE_TAG:

                    $materialsModel = Model_Materials::getInstance();
                    $mateList = $materialsModel->findByTagID($categoryId);

                    foreach ($mateList as $m) {

                        // 素材现有标签
                        $original_category = explode(',', $m['TagIDs']);

                        // 要删除标签是否在标签中
                        $in_category = in_array($categoryId, $original_category);

                        if ($in_category) {
                            // 确定位置
                            $key = array_search($categoryId, $original_category);
                            // 删除
                            array_splice($original_category, $key, 1);
                        }

                        $update_data = ['TagIDs' => implode(',', $original_category)];
                        $materialsModel->update($update_data, ['MaterialID = ?' => $m['MaterialID']]);
                    }
                    break;
            }
            $categoryModel->delete(['CategoryID = ?'=>$categoryId]);

            $this->showJson(1, '删除成功');

        } else {
            $this->showJson(0, '标签ID查询错误');
        }
    }


}