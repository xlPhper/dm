<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_MaterialController extends OpenBase
{
    /**
     * 素材列表接口
     */
    public function listAction()
    {
        // 内容/类别/价格/标签/位置/添加日期
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 100);
        $type = $this->_getParam('Type',null);

        $textContent = trim($this->_getParam('TextContent', ''));
        $tagIds = trim($this->_getParam('TagID', ''));
        $productCateId = (int)$this->_getParam('ProductCateID');
        $addTimeStart = trim($this->_getParam('AddTimeStart'));
        $addTimeEnd = trim($this->_getParam('AddTimeEnd'));
        $address = trim($this->_getParam('Address', ''));
        $minPrice = trim($this->_getParam('MinPrice', ''));
        $maxPrice = trim($this->_getParam('MaxPrice', ''));
        $name = trim($this->_getParam('MaterialName', ''));
        $order = $this->_getParam('Order',-1);//排序
        $user = $this->_getParam('User','');//上传者

        $model = new Model_Materials();
        $category_model = new Model_Category();

        //获取部门成员
        $adminIds = $this->getDepartmentAdminIds();
        if(!$adminIds){
            $adminIds = [$this->getLoginUserId()];
        }
        $select = $model->fromSlaveDB()->select();
        if ($type){
            $select->where('Type = ?', $type);
        }
        if ($productCateId > 0) {
            $childIds = Model_Category::findChildIds($productCateId);
            $childIds[] = $productCateId;
            $select->where('ProductCateID in (?)', $childIds);
        }
        if ($textContent !== '') {
            $select->where('TextContent like ?', '%' . $textContent . '%');
        }
        if ($tagIds !== '') {
            $tagIds = explode(',', $tagIds);
            $tagWheres = [];
            foreach ($tagIds as $tagId) {
                $tagWheres[] = 'FIND_IN_SET(' . (int)$tagId. ', TagIDs)';
            }
            $select->where(implode(' OR ', $tagWheres));
        }
        if ($addTimeStart !== '') {
            $select->where('AddTime >= ?', $addTimeStart);
        }
        if ($addTimeEnd !== '') {
            $select->where('AddTime <= ?', $addTimeEnd);
        }
        if ($address !== '') {
            $select->where('Address like ?', '%' . $address . '%');
        }
        if ($minPrice !== '') {
            $select->where('SalePrice >= ?', $minPrice);
        }
        if ($maxPrice !== '') {
            $select->where('SalePrice <= ?', $maxPrice);
        }
        if ($name !== '') {
            $select->where('MaterialName like ?', '%'.$name.'%');
        }
        if($user ){
            $select->where('AdminID = ?',$user);
        }else{
            $select->where('AdminID in (?)',$adminIds);
        }
        $select->where('Status = ?',1);
        $select->order('MaterialID DESC');
        if($order == 1 ){
            $select->order('AddTime DESC');
        }
        if($order == 2 ){
            $select->order('UsedNum DESC');
        }

        $res = $model->getResult($select, $page, $pagesize);

        $categories = $category_model->getIdToName(null,PLATFORM_OPEN);

        foreach ($res['Results'] as &$d) {
            // ProductTags 42,43

            if ($d['TagIDs']){
                $arr = explode(",", $d['TagIDs']);
                $label = [];
                foreach ($arr as $id) {
                    $label[] = $categories[$id]??$id;
                }
                $d['Tags'] = implode(',',$label);
            }
        }

        $this->showJson(1, '', $res);
    }

    /**
     * 纯素材编辑
     */
    public function modifyAction()
    {
        $params = $this->ValidParams();

        $materialModel = new Model_Materials();

        $materialId = (int)$this->_getParam('MaterialID');
        if ($materialId > 0) {
            $material = $materialModel->fetchRow(['MaterialID = ?' => $materialId]);
            if (!$material) {
                $this->showJson(self::STATUS_FAIL, 'id非法');
            }
            if ($params['Type'] != $material['Type']) {
                $this->showJson(self::STATUS_FAIL, '编辑时不能改变类型');
            }
        }

        try {
            if ($materialId > 0) {
                $params['UpdateTime'] = date('Y-m-d H:i:s');
                $materialModel->update($params, ['MaterialID = ?' => $materialId]);
            } else {
                $params['AddTime'] = date('Y-m-d H:i:s');
                $params['AdminID'] = $this->getLoginUserId();
                $materialId = $materialModel->insert($params);
            }
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:' . $e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功', ['MaterialID' => $materialId]);
    }

    /**
     * 素材详情接口
     */
    public function detailAction()
    {
        $materialModel = new Model_Materials();

        $materialId = (int)$this->_getParam('MaterialID');
        if ($materialId < 1) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }

        $material = $materialModel->fromSlaveDB()->fetchRow(['MaterialID = ?' => $materialId]);
        if (!$material) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }

        $material = $material->toArray();
        $material['Comments'] = json_decode($material['Comments'], 1);
        if ($material['ProductCateID'] > 0) {
            $pIds = Model_Category::findParentIds((int)$material['ProductCateID']);
            $pIds[] = $material['ProductCateID'];
            $material['ParentCategoryIds'] = implode(',', $pIds);
        } else {
            $material['ParentCategoryIds'] = '';
        }

        $this->showJson(self::STATUS_OK, '操作成功', $material);
    }

    public function deleteAction()
    {
        $materialModel = new Model_Materials();

        $materialId = $this->_getParam('MaterialID');

        if ($materialId < 1) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }

        $materialArr = explode(',',$materialId);

        foreach ($materialArr as $value) {
            $material = $materialModel->fetchRow(['MaterialID = ?' => $value]);
            if (!$material) {
                $this->showJson(self::STATUS_FAIL, 'id非法');
            }
        }

        try {
            $materialModel->delete(['MaterialID in (?)'=>$materialArr]);
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '删除失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '删除成功');
    }


    /**
     * 添加/编辑素材
     */
    public function editAction()
    {
        $params = $this->getValidParams();

        $materialModel = new Model_Materials();

        $materialId = (int)$this->_getParam('MaterialID');

        if ($materialId > 0) {
            $material = $materialModel->fetchRow(['MaterialID = ?' => $materialId]);
            if (!$material) {
                $this->showJson(self::STATUS_FAIL, 'id非法');
            }
            if ($params['Type'] != $material['Type']) {
                $this->showJson(self::STATUS_FAIL, '编辑时不能改变类型');
            }
        }

        try {

            $materialModel->getAdapter()->beginTransaction();
            $isSche = $this->_getParam('IsSche');//是否排期
            $isSync = $this->_getParam('IsSync');//是否同步

            if ($materialId > 0) {

                $params['UpdateTime'] = date('Y-m-d H:i:s');
                $params['UsedNum'] = $material['UsedNum'] + 1;
                $materialModel->update($params, ['MaterialID = ?' => $materialId]);
            }else{
                if(!$isSync){
                    $params['Status'] = 3;
                }
                $params['AddTime'] = date('Y-m-d H:i:s');
                $params['UsedNUm'] = 1;
                $params['AdminID'] = $this->getLoginUserId();
                $materialId = $materialModel->insert($params);

            }


            $publishTime = $this->_getParam('PublishTime', '');//排期执行时间
           
            if(!$publishTime) {
                $publishTime = date('Y-m-d H:i');
            }

            $wxIdType = trim($this->_getParam('WxIdType', 'WX_ID'));
            if ($wxIdType != 'WX_ID' && $wxIdType != 'WX_TAG') {
                $this->showJson(self::STATUS_FAIL, '微信id分类非法');
            }
            $wexinIds = $this->_getParam('WeixinIDs', '');
            if ($wexinIds === '') {
                $this->showJson(self::STATUS_FAIL, '微信ids必填');
            }
            $startDate = date('Y-m-d 00:00:00',strtotime($publishTime));
            $endDate = date('Y-m-d 23:59:59',strtotime($publishTime));


            $tmpScheConfigs = [];
            $mateModel = new Model_Materials();

            $execTimeType = Helper_Until::getExecTimeType($publishTime);
            if (false === $execTimeType) {
                $this->showJson(self::STATUS_FAIL, '排期配置格式时间非法');
            }

            $mate = $mateModel->fetchRow(['MaterialID = ?' => $materialId]);
            if (!$mate) {
                $this->showJson(self::STATUS_FAIL, '排期配置中素材id'.$materialId.'不存在');
            }

            if ($execTimeType == 'RAND') {
                $unix = strtotime($publishTime . ':00');
            } else {
                $unix = strtotime($publishTime);
            }
            $tmpScheConfigs[$unix] = [
                'ExecType' => $execTimeType,
                'ExecTime' => $publishTime,
                'MateID' => (int)$materialId
            ];
            ksort($tmpScheConfigs);

            $validScheConfigs = json_encode(array_values($tmpScheConfigs));

            $data =  [
                'WeixinIDs'     => $wexinIds,
                'StartDate'     => $startDate,
                'EndDate'       => $endDate,
                'ScheConfigs'   => $validScheConfigs,
                'NormalMateNum' => 1,
                'NextRunTime'   => $publishTime,
                'WxIdType'      => $wxIdType,
                'AddTime'       => date('Y-m-d H:i:s'),
                'AdminID' => $this->getLoginUserId(),
            ];

            $scheModel = Model_Schedules::getInstance();
            $scheModel->insert($data);

            $materialModel->getAdapter()->commit();
        } catch (\Exception $e) {
            $materialModel->getAdapter()->rollBack();
            $this->showJson(self::STATUS_FAIL, '操作失败,err:' . $e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功', ['MaterialID' => $materialId]);
    }

    /**
     * 获取合法参数
     */
    private function getValidParams()
    {
        /**
         * 发文内容1/素材类别1/分类id
        普通: 媒体类型, 素材content/ 评论留言/地理位置/状态
        商品: 媒体类型(固定为图片), martielContent / 编号 / sku / 商品标签/ 商品标题 / 原价 / 特价 / 二维码链接 / 样式 id / 评论留言/ 地理位置 / 状态
         */
        $textContent = trim($this->_getParam('TextContent'));
        if ('' === $textContent) {
            $this->showJson(self::STATUS_FAIL, '发文内容必填');
        }
        $type = (int)$this->_getParam('Type');
        if ($type != 1 && $type != 2) {
            $this->showJson(self::STATUS_FAIL, '素材类型非法');
        }
        $tagIds = trim($this->_getParam('TagIDs', ''));
        // 评论
        $comments = trim($this->_getParam('Comments', ''));
        $position = trim($this->_getParam('Position', ''));
        $address = trim($this->_getParam('Address', ''));
        $addressId = trim($this->_getParam('AddressID', ''));
        $name = trim($this->_getParam('MaterialName', ''));

        // 如果是商品类型素材, 媒体类型固定为图片
        if ($type == 1) {
            $mediaType = (int)$this->_getParam('MediaType');
            if (!in_array($mediaType, [1, 2, 3, 4])) {
                $this->showJson(self::STATUS_FAIL, '媒体类型非法');
            }
            $productNum = '';
            $sku = '';
            $productCateId = 0;
            $title = '';
            $marketPrice = 0;
            $salePrice = 0;
            $styleId = 0;
            $link = '';
            $useStyleIndexs = '';
        } else {
            $mediaType = 1;
            $styleId = (int)$this->_getParam('StyleID', 0);
            $productNum = trim($this->_getParam('ProductNum', ''));
            $sku = trim($this->_getParam('Sku', ''));
            $productCateId = (int)$this->_getParam('ProductCateID');
            if ($productCateId < 1) {
                $this->showJson(self::STATUS_FAIL, '分类id非法');
            }

            $title = trim($this->_getParam('ProductTitle', ''));
            if ($title === '' && $styleId > 0) {
                $this->showJson(self::STATUS_FAIL, '商品标题必填');
            }
            $marketPrice = trim($this->_getParam('MarketPrice', '0'));
            if ($marketPrice <= 0 && $styleId > 0) {
                $this->showJson(self::STATUS_FAIL, '市场价非法');
            }
            $salePrice = trim($this->_getParam('SalePrice', '0'));
            if ($salePrice <= 0 && $styleId > 0) {
                $this->showJson(self::STATUS_FAIL, '售价非法');
            }

            $link = trim($this->_getParam('ProductLink', ''));
            if ('' === $link && $styleId > 0) {
                $this->showJson(self::STATUS_FAIL, '商品二维码链接非法');
            }
            $useStyleIndexs = trim($this->_getParam('UseStyleIndexs', ''));
        }
        $mediaContent = trim($this->_getParam('MediaContent', ''));
        if ($mediaType == 4) {
            $mediaContent = '';
        }
        $addressCustom = trim($this->_getParam('AddressCustom', ''));

        // 当 addressId 为空时,认为是国外的, 请求相应接口获取addressId 和 name
        if ($addressId === '' && $position !== '') {
            $positionArr = explode(',', $position);
            $longitude = $positionArr[1];
            if ($longitude > 180) {
                $longitude = $longitude % 180 - 180;
            } elseif ($longitude < -180) {
                $longitude = $longitude % 180 + 180;
            }
            $positionArr[1] = $longitude;
            $position = implode(',', $positionArr);
            $addressData = $this->getForeignAddressId($position);
            $addressName = $addressData['name'];
            $addressId = $addressData['id'];
        } else {
            $addressName = '';
        }

        return [
            'TextContent' => $textContent,
            'Type' => $type,
            'TagIDs' => $tagIds,
            'Comments' => $comments,
            'Position' => $position,
            'Address' => $address,
            'AddressID' => $addressId,
            'Status' => 1,
            'MediaType' => $mediaType,
            'MediaContent' => $mediaContent,
            'ProductNum' => $productNum,
            'Sku' => $sku,
            'ProductCateID' => $productCateId,
            'MaterialName' => $name,
            'ProductTitle' => $title,
            'MarketPrice' => $marketPrice,
            'SalePrice' => $salePrice,
            'StyleID' => $styleId,
            'ProductLink' => $link,
            'UseStyleIndexs' => $useStyleIndexs,
            'AddressCustom' => $addressCustom,
            'AddressName' => $addressName,

        ];
    }

    private function ValidParams()
    {
        /**
         * 发文内容1/素材类别1/分类id
        普通: 媒体类型, 素材content/ 评论留言/地理位置/状态
        商品: 媒体类型(固定为图片), martielContent / 编号 / sku / 商品标签/ 商品标题 / 原价 / 特价 / 二维码链接 / 样式 id / 评论留言/ 地理位置 / 状态
         */
        $textContent = trim($this->_getParam('TextContent'));
        if ('' === $textContent) {
            $this->showJson(self::STATUS_FAIL, '发文内容必填');
        }
        $type = (int)$this->_getParam('Type');
        if ($type != 1 && $type != 2) {
            $this->showJson(self::STATUS_FAIL, '素材类型非法');
        }
        $tagIds = trim($this->_getParam('TagIDs', ''));
        // 评论
        $comments = trim($this->_getParam('Comments', ''));
        $position = trim($this->_getParam('Position', ''));
        $address = trim($this->_getParam('Address', ''));
        $addressId = trim($this->_getParam('AddressID', ''));


        // 如果是商品类型素材, 媒体类型固定为图片
        if ($type == 1) {
            $mediaType = (int)$this->_getParam('MediaType');
            if (!in_array($mediaType, [1, 2, 3, 4])) {
                $this->showJson(self::STATUS_FAIL, '媒体类型非法');
            }
            $productNum = '';
            $sku = '';
            $productCateId = 0;
            $title = '';
            $marketPrice = 0;
            $salePrice = 0;
            $styleId = 0;
            $link = '';
            $useStyleIndexs = '';
        } else {
            $mediaType = 1;
            $styleId = (int)$this->_getParam('StyleID', 0);
            $productNum = trim($this->_getParam('ProductNum', ''));
            $sku = trim($this->_getParam('Sku', ''));
            $productCateId = (int)$this->_getParam('ProductCateID');
            if ($productCateId < 1) {
                $this->showJson(self::STATUS_FAIL, '分类id非法');
            }

            $title = trim($this->_getParam('ProductTitle', ''));
            if ($title === '' && $styleId > 0) {
                $this->showJson(self::STATUS_FAIL, '商品标题必填');
            }
            $marketPrice = trim($this->_getParam('MarketPrice', '0'));
            if ($marketPrice <= 0 && $styleId > 0) {
                $this->showJson(self::STATUS_FAIL, '市场价非法');
            }
            $salePrice = trim($this->_getParam('SalePrice', '0'));
            if ($salePrice <= 0 && $styleId > 0) {
                $this->showJson(self::STATUS_FAIL, '售价非法');
            }

            $link = trim($this->_getParam('ProductLink', ''));
            if ('' === $link && $styleId > 0) {
                $this->showJson(self::STATUS_FAIL, '商品二维码链接非法');
            }
            $useStyleIndexs = trim($this->_getParam('UseStyleIndexs', ''));
        }
        $mediaContent = trim($this->_getParam('MediaContent', ''));
        if ($mediaType == 4) {
            $mediaContent = '';
        }
        $addressCustom = trim($this->_getParam('AddressCustom', ''));

        // 当 addressId 为空时,认为是国外的, 请求相应接口获取addressId 和 name
        if ($addressId === '' && $position !== '') {
            $positionArr = explode(',', $position);
            $longitude = $positionArr[1];
            if ($longitude > 180) {
                $longitude = $longitude % 180 - 180;
            } elseif ($longitude < -180) {
                $longitude = $longitude % 180 + 180;
            }
            $positionArr[1] = $longitude;
            $position = implode(',', $positionArr);
            $addressData = $this->getForeignAddressId($position);
            $addressName = $addressData['name'];
            $addressId = $addressData['id'];
        } else {
            $addressName = '';
        }

        return [
            'TextContent' => $textContent,
            'Type' => $type,
            'TagIDs' => $tagIds,
            'Comments' => $comments,
            'Position' => $position,
            'Address' => $address,
            'AddressID' => $addressId,
            'Status' => 1,
            'MediaType' => $mediaType,
            'MediaContent' => $mediaContent,
            'ProductNum' => $productNum,
            'Sku' => $sku,
            'ProductCateID' => $productCateId,
            'MaterialName' => '',
            'ProductTitle' => $title,
            'MarketPrice' => $marketPrice,
            'SalePrice' => $salePrice,
            'StyleID' => $styleId,
            'ProductLink' => $link,
            'UseStyleIndexs' => $useStyleIndexs,
            'AddressCustom' => $addressCustom,
            'AddressName' => $addressName,

        ];
    }


    private function getForeignAddressId($position)
    {
        $url = 'https://api.foursquare.com/v2/venues/search?ll='.$position.'&oauth_token=5KUR2SAJ2PO15ULE51LPL0KOEVCJNDHBRYDFNUJOI44DP5FW&v='.date('Ymd');
        $response = file_get_contents($url);
        $res = json_decode($response, 1);
        if (isset($res['meta']['code']) && $res['meta']['code'] == 200 && isset($res['response']['venues'][0])) {
            $data = [
                'id' => 'foursquare_' . $res['response']['venues'][0]['id'],
                'name' => $res['response']['venues'][0]['name']
            ];
        } else {
            $data = [
                'id' => '',
                'name' => ''
            ];
        }

        return $data;
    }

    /**
     * 校验地址
     */
    public function addressValidAction()
    {
        $position = trim($this->_getParam('Position', ''));

        $positionArr = explode(',', $position);
        $longitude = $positionArr[1];
        if ($longitude > 180) {
            $longitude = $longitude % 180 - 180;
        } elseif ($longitude < -180) {
            $longitude = $longitude % 180 + 180;
        }
        $positionArr[1] = $longitude;
        $position = implode(',', $positionArr);
        $addressData = $this->getForeignAddressId($position);
        $addressName = $addressData['name'];
        $addressId = $addressData['id'];

        if (empty($addressId)) {
            $this->showJson(self::STATUS_FAIL, '该地址无法定位,请重新选择');
        }

        $this->showJson(self::STATUS_OK, '合法地址');
    }

    /**
     * 商品素材样式
     */
    public function stylesAction()
    {
        $styles = (new Model_Styles())->fromSlaveDB()->fetchAll()->toArray();

        $this->showJson(self::STATUS_OK, '操作成功', $styles);
    }

    /**
     * 批量删除
     */
    public function batchDeleteAction()
    {
        $materialIds = trim($this->_getParam('MaterialIDs', ''));
        if ($materialIds === '') {
            $this->showJson(self::STATUS_FAIL, 'ids非法');
        }

        $materialIds = explode(',', $materialIds);
        $tmpIds = [];
        foreach ($materialIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $tmpIds[] = $id;
            }
        }
        if (!$tmpIds) {
            $this->showJson(self::STATUS_FAIL, 'ids非法');
        }

        try {
            (new Model_Materials())->delete(['MaterialID in (?)' => $tmpIds]);
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '删除失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '删除成功');
    }

    public function batchCateAction()
    {
        $materialIds = trim($this->_getParam('MaterialIDs', ''));
        if ($materialIds === '') {
            $this->showJson(self::STATUS_FAIL, '素材ids非法');
        }

        $materialIds = explode(',', $materialIds);
        $tmpIds = [];
        foreach ($materialIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $tmpIds[] = $id;
            }
        }
        if (!$tmpIds) {
            $this->showJson(self::STATUS_FAIL, '素材ids非法');
        }

        $productCateId = (int)$this->_getParam('ProductCateID');
        if ($productCateId < 1) {
            $this->showJson(self::STATUS_FAIL, '商品分类非法');
        }

        $model = new Model_Materials();
        $mates = $model->fetchAll(['MaterialID in (?)' => $tmpIds, 'Type = ?' => 2])->toArray();
        if (count($mates) != count($tmpIds)) {
            $this->showJson(self::STATUS_FAIL, '存在非商品类型素材');
        }

        try {
            (new Model_Materials())->update(['ProductCateID' => $productCateId], ['MaterialID in (?)' => $tmpIds]);
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '更新失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '更新成功');
    }

    public function batchTagAction()
    {
        $materialIds = trim($this->_getParam('MaterialIDs', ''));
        if ($materialIds === '') {
            $this->showJson(self::STATUS_FAIL, '素材ids非法');
        }

        $materialIds = explode(',', $materialIds);
        $tmpIds = [];
        foreach ($materialIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $tmpIds[] = $id;
            }
        }
        if (!$tmpIds) {
            $this->showJson(self::STATUS_FAIL, '素材ids非法');
        }

        $tagIds = trim($this->_getParam('TagIDs', ''));
        if ($tagIds === '') {
            $this->showJson(self::STATUS_FAIL, '标签ids非法');
        }

        try {
            (new Model_Materials())->update(['TagIDs' => $tagIds], ['MaterialID in (?)' => $tmpIds]);
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '更新失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '更新成功');

    }

    /**
     * 素材任务管理列表
     */
    public function taskListAction()
    {
        $page       = $this->_getParam('Page', 1);
        $pagesize   = $this->_getParam('Pagesize', 100);
        $taskId     = $this->_getParam('ScheduleID','');
        $found      = $this->_getParam('AdminID','');
        $startDate  = $this->_getParam('StartDate','');
        $endDate    = $this->_getParam('EndDate','');
        $status     = $this->_getParam('Status',-1);

        $sModel = new Model_Schedules();
        $aModel = new Model_Role_Admin();
        $wModel = new Model_Weixin();

        $userId = $this->getLoginUserId();
//        $userId = 151;
//        $this->admin['DepartmentID'] = 26;
        $select = $sModel->fromSlaveDB()->select()->from($sModel->getTableName(),['WeixinIDs','ScheduleID','NextRunTime','Status','AddTime','AdminID']);
        //查看当前账号的部门
        $admins = [];
        if($this->admin['DepartmentID']){
            $adminData = $aModel->fetchAll(['DepartmentID = ?'=>$this->admin['DepartmentID']]);
            foreach ($adminData as $value){
                $admins[] = $value['AdminID'];
            }
        }
        if(empty($admins)){
            $admins = [$userId];
        }
        if($taskId){
            $select->where('ScheduleID = ?',$taskId);
        }
        if($found){
            $select->where('AdminID = ?',$found);
        }
        if($startDate){
            $select->where('NextRunTime > ?',$startDate);
        }
        if($endDate){
            $select->where('NextRunTime < ?',$endDate);
        }
        if($status>0){
            $select->where('Status = ?',$status);
        }

        $select->where('AdminID in (?)',$admins);
        $select->order('AddTime desc');

        $res = $sModel->getResult($select,$page,$pagesize);

        foreach ($res['Results'] as &$value){
            $wxs = explode(',',$value['WeixinIDs']);
            $result = $wModel->fetchAll(['WeixinID in (?)'=>$wxs]);
            $wxIdArr = [];
            foreach ($result as $val){
                $wxIdArr[] = $val['Nickname'];
            }
            $value['Weixin'] = implode(',',$wxIdArr);
            $adminData = $aModel->getInfoByID($value['AdminID']);
            $value['Username'] = $adminData['Username'];
        }
        $this->showJson(1,'操作成功',$res);
    }

    public function taskEditAction()
    {

        $scheduleID = (int)$this->_getParam('ScheduleID');

        $schedulesModel = new Model_Schedules();

        if ($scheduleID > 0) {
            $schedule = $schedulesModel->fetchRow(['ScheduleID = ?' => $scheduleID]);
            if (!$schedule) {
                $this->showJson(self::STATUS_FAIL, 'id非法');
            }
        }
        $nextRunTime = $this->_getParam('NextRunTime');
        if($nextRunTime){
            $time = Helper_Until::getExecTimeType($nextRunTime);
            if (false === $time) {
                $this->showJson(self::STATUS_FAIL, '排期配置格式时间非法');
            }
        }else{
            $time = date('Y-m-d H:i');
        }
        $wexinIds = $this->_getParam('WeixinIDs', '');

        $data = ['NextRunTime' => $time,'WeixinIDs'=>$wexinIds];
        $schedulesModel->update($data,['ScheduleID = ?'=> $scheduleID]);
        $this->showJson(1,'操作成功',['ScheduleID'=> $scheduleID]);


    }

    /**
     * 排期任务详情
     */
    public function taskDetailAction()
    {
        $scheduleID = (int)$this->_getParam('ScheduleID');
        if(!$scheduleID){
            $this->showJson(self::STATUS_FAIL, '参数排期id必传');
        }

        $schedulesModel = new Model_Schedules();
        $materialModel  = new Model_Materials();

        $schedule = $schedulesModel->fetchRow(['ScheduleID = ?' => $scheduleID])->toArray();
        if (!$schedule) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }
        $materialId = json_decode($schedule['ScheConfigs'])[0]->MateID;
        $material = $materialModel->fetchRow(['MaterialID = ?'=> $materialId]);
        $material = $material?$material->toArray():'';
        $response = [
            'Schedule'  => $schedule,
            'Material'  => $material,
        ];
        $this->showJson(1,'操作成功',$response);
    }

    /**
     * 删除排期管理任务
     */
    public function taskDeleteAction()
    {
        $scheduleID = (int)$this->_getParam('ScheduleID');
        if (!$scheduleID) {
            $this->showJson(self::STATUS_FAIL, '参数排期id必传');
        }
        $schedulesModel = new Model_Schedules();

        $schedule = $schedulesModel->fetchRow(['ScheduleID = ?' => $scheduleID])->toArray();
        if ($schedule['AdminID'] != $this->getLoginUserId()) {
            $this->showJson(self::STATUS_FAIL, '您无法删除该任务');
        }

        if ($schedulesModel->delete(['ScheduleID = ?' => $scheduleID])) {
            $this->showJson(1, '操作成功');
        } else {
            $this->showJson(0, '系统错误');
        }
    }

    /**
     * @throws Zend_Db_Statement_Exception
     * 设置标签
     */
    public function setTagAction()
    {
        $categoryIds     = $this->_getParam('CategoryID');
        $materialIds      = $this->_getParam('MaterialID');
        $type            = $this->_getParam('Type');

        $categoryArr    = explode(',',$categoryIds);
        $materialArr    = explode(',',$materialIds);

        if(!$categoryIds){
            $this->showJson(0,'标签id不能为空');
        }
        if(!$materialIds){
            $this->showJson(0,'素材id不能为空');
        }

        $mModel = new Model_Materials();
        // 校验部门数据
        $depArr = $this->getDepartmentAdminIds();
        if(!$depArr){
            $depArr = [$this->getLoginUserId()];
        }

        $checkArr = [];
        $checkResult = $mModel->select()->where('AdminID in (?)',$depArr)->query()->fetchAll();

        foreach ($checkResult as $value){
            $checkArr[] = $value['MaterialID'];
        }
        $diffArr = array_diff($materialArr,$checkArr);

        if($diffArr){
            $this->showJson(0,'操作异常数据');
        }


        //新增标签
        if($type==1){
            foreach ($materialArr as $value){
                $cateResult = $mModel->select()->where('MaterialID = ?',$value)->query()->fetch();
                $cateIds = $cateResult['TagIDs'].','.$categoryIds;
                $mModel->update(['TagIDs' => $cateIds],['MaterialID = ?'=>$value]);
            }
            $this->showJson(1, '操作成功');
        }
        //覆盖标签
        if($type ==2){
            $mModel->update(['TagIDs' => $categoryIds],['MaterialID in (?)'=>$materialArr]);
            $this->showJson(1, '操作成功');
        }
        //删除标签
        if($type ==3){
            foreach ($materialArr as $value){
                $cateResult = $mModel->select()->where('MaterialID = ?',$value)->query()->fetch();
                $cateArr = explode(',',$cateResult['TagIDs']);
                foreach ($categoryArr as $key => $id){
                    $cateArr = array_diff($cateArr,[$id]);
                }
                $cateStr = implode(',',$cateArr);
                $mModel->update(['TagIDs' => $cateStr],['MaterialID = ?'=>$value]);
            }
            $this->showJson(1, '操作成功');
        }
    }

    /**
     * 获取同部门成员列表(返回名称)
     */
    public function findMemberAction()
    {
        $res = $this->getDepartmentAdminIds();
        $aModel = new Model_Role_Admin();
        $result = $aModel->getNames();

        $data = [];
        foreach ($res as &$d) {
            foreach ($result as $key => $id) {
                if($d == $key){
                    $data[] = [
                        'id'    => $d,
                        'Username'  => $id,
                    ];
                }
            }
        }
        $this->showJson(1,'管理员',$data);
    }

}