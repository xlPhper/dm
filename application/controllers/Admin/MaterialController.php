<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_MaterialController extends AdminBase
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

        $model = new Model_Materials();
        $category_model = new Model_Category();

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
        $select->order('MaterialID DESC');

        $res = $model->getResult($select, $page, $pagesize);

        $categories = $category_model->getIdToName();

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

        $materialId = (int)$this->_getParam('MaterialID');
        if ($materialId < 1) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }

        $material = $materialModel->fetchRow(['MaterialID = ?' => $materialId]);
        if (!$material) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }

        try {
            $material->delete();
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
            if ($materialId > 0) {
                $params['UpdateTime'] = date('Y-m-d H:i:s');
                $materialModel->update($params, ['MaterialID = ?' => $materialId]);
            } else {
                $params['AddTime'] = date('Y-m-d H:i:s');
                $materialId = $materialModel->insert($params);
            }
        } catch (\Exception $e) {
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
        $status = (int)$this->_getParam('Status');
        if ($status != 1 && $status != 2) {
            $this->showJson(self::STATUS_FAIL, '状态值非法');
        }
        $name = trim($this->_getParam('MaterialName', ''));
        if ($name === '') {
            $this->showJson(self::STATUS_FAIL, '素材名称必填');
        }

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
            'Status' => $status,
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
            'AddressName' => $addressName
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

}