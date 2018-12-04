<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_AdController extends AdminBase
{
    public function listAction()
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 100);

        $adModel = new Model_Ad();

        $startDate = trim($this->_getParam('StartDate', ''));
        $endDate = trim($this->_getParam('EndDate', ''));
        $status = trim($this->_getParam('Status', ''));
        $tagIds = intval($this->_getParam('TagIDs'));
        $type = trim($this->_getParam('Type'));

        $select = $adModel->fromSlaveDB()->select()->where('DeleteTime = ?', '0000-00-00 00:00:00');
        if ($startDate !== '') {
            $select->where('StartDate >= ?', $startDate);
        }
        if ($endDate !== '') {
            $select->where('EndDate <= ?', $endDate);
        }
        if ($status !== '') {
            $select->where('Status = ?', $status);
        }
        if ($type !== '') {
            $select->where('Type = ?', $type);
        }
        if ($tagIds !== '') {
            $tagIds = explode(',', $tagIds);
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ((int)$tagId > 0) {
                    $conditions[] = 'find_in_set(' . (int)$tagId . ', WxTagIDs)';
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }
        $select->order('AdID DESC');

        $res = $adModel->getResult($select, $page, $pagesize);
        $cateModel = new Model_Category();
        foreach ($res['Results'] as &$d) {
            $d['TagNames'] = '';
            if ($d['IdsType'] == 'WXTAG') {
                if (!empty($d['WxTagIDs'])) {
                    if ($d['WxTagIDs'] == -1) {
                        $d['TagNames'] = '全部';
                    } else {
                        $wxTagIds = explode(',', $d['WxTagIDs']);
                        $tags = $cateModel->fetchAll(['CategoryID in (?)' => $wxTagIds])->toArray();
                        $tmpTagNames = [];
                        foreach ($tags as $tag) {
                            $tmpTagNames[] = $tag['Name'];
                        }
                        $d['TagNames'] = implode(',', $tmpTagNames);
                    }
                }
            }
        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    public function editAction()
    {
        $idsType = trim($this->_getParam('IdsType', ''));
        $wxTagIds = trim($this->_getParam('WxTagIDs', ''));
        $type = trim($this->_getParam('Type', ''));
        $links = trim($this->_getParam('Links', ''));
        $clickWordNum = (int)$this->_getParam('ClickWordNum', 0);
        $clickAdPos = trim($this->_getParam('ClickAdPos', ''));
        $clickLike = trim($this->_getParam('ClickLike', ''));
        $startDate = trim($this->_getParam('StartDate', ''));
        $endDate = trim($this->_getParam('EndDate', ''));
        $execTime = trim($this->_getParam('ExecTime', ''));
        $clickUrls = trim($this->_getParam('ClickUrls', ''));
        $title = trim($this->_getParam('Title', ''));

        if (!in_array($idsType, ['DEVICE', 'WXTAG'])) {
            $this->showJson(self::STATUS_FAIL, 'idstype非法');
        }
        if ($wxTagIds === '') {
            $this->showJson(self::STATUS_FAIL, '微信标签/设备ids非法');
        }
        if (!in_array($type, [Model_Ad::TYPE_BDHOT, Model_Ad::TYPE_GZH, Model_Ad::TYPE_URL, Model_Ad::TYPE_MINA])) {
            $this->showJson(self::STATUS_FAIL, '广告类型非法');
        }
        if ($links === '') {
            $this->showJson(self::STATUS_FAIL, '链接必填');
        }
        if ($type == Model_Ad::TYPE_BDHOT && $clickWordNum < 1) {
            $this->showJson(self::STATUS_FAIL, '百度热词点击数须 > 0');
        }
        if ($type == Model_Ad::TYPE_GZH && ($clickAdPos === '' || !in_array($clickLike, ['Y', 'N']))) {
            $this->showJson(self::STATUS_FAIL, '公众号点击第几个广告非法或是否点赞非法');
        }
        if ($type == Model_Ad::TYPE_URL && $clickUrls === '') {
            $this->showJson(self::STATUS_FAIL, '点击链接必填');
        }
        if ($type == Model_Ad::TYPE_MINA && $clickAdPos === '') {
            $this->showJson(self::STATUS_FAIL, '公众号点击第几个广告非法法');
        }
        if (strtotime($startDate) === false) {
            $this->showJson(self::STATUS_FAIL, '开始时间非法');
        }
        if (strtotime(date('Y-m-d')) > strtotime($startDate)) {
            $this->showJson(self::STATUS_FAIL, '开始时间须 >= 今天');
        }
        if (strtotime($endDate) === false) {
            $this->showJson(self::STATUS_FAIL, '结束时间非法');
        }
        if (strtotime(date('Y-m-d')) > strtotime($endDate)) {
            $this->showJson(self::STATUS_FAIL, '结束时间须 >= 今天');
        }
        if (strtotime($startDate) > strtotime($endDate)) {
            $this->showJson(self::STATUS_FAIL, '结束时间须 >= 开始时间');
        }
        if ($execTime === '') {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }
        $execTime = json_decode($execTime, 1);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }
        $validExecTime = Helper_Timer::getValidOptions($execTime);
        if ($validExecTime === false) {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }

        $adModel = new Model_Ad();
        $adId = (int)$this->_getParam('AdID');
        if ($adId > 0) {
            $ad = $adModel->getByPrimaryId($adId);
            if (!$ad || $ad['DeleteTime'] != '0000-00-00 00:00:00') {
                $this->showJson(self::STATUS_FAIL, '参数非法');
            }
//            if ($ad['ExecutedNums'] > 0) {
//                $this->showJson(self::STATUS_FAIL, '已经执行过,不允许修改,如果有误,请删除重新添加');
//            }
            if ($ad['Type'] != $type) {
                $this->showJson(self::STATUS_FAIL, '类型不允许修改');
            }
        }

        // Get Next Run Time
        list($nextRunTime, $nextRunType) = Helper_Timer::getNextRunTime($startDate, $endDate, $validExecTime);
        if ($nextRunTime == '0000-00-00 00:00:00') {
            $this->showJson(self::STATUS_FAIL, '没有找到合适的下一次执行时间');
        }

        $params = [
            'IdsType' => $idsType,
            'WxTagIDs' => $wxTagIds,
            'Type' => $type,
            'Links' => $links,
            'ClickWordNum' => $clickWordNum,
            'ClickAdPos' => $clickAdPos,
            'ClickLike' => $clickLike,
            'ClickUrls' => $clickUrls,
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'ExecTime' => json_encode($validExecTime),
            'NextRunTime' => $nextRunTime,
            'NextRunType' => $nextRunType
        ];
        if ($title !== '') {
            $params['Title'] = $title;
        }

        try {
            if ($adId > 0) {
                $params['UpdateTime'] = date('Y-m-d H:i:s');
                $adModel->update($params, ['AdID = ?' => $adId]);
            } else {
                $params['AddTime'] = date('Y-m-d H:i:s');
                $adId = $adModel->insert($params);
            }
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功', ['AdID' => $adId]);
    }

    public function detailAction()
    {
        $adId = (int)$this->_getParam('AdID');
        if ($adId < 1) {
            $this->showJson(self::STATUS_FAIL, '广告id非法');
        }

        $adModel = new Model_Ad();
        $ad = $adModel->getByPrimaryId($adId);
        if (!$ad) {
            $this->showJson(self::STATUS_FAIL, '广告id非法');
        }

        $ad = $ad->toArray();
        $ad['ExecTime'] = json_decode($ad['ExecTime'], 1);

        $this->showJson(self::STATUS_OK, '操作成功', $ad);
    }

    public function deleteAction()
    {
        $adId = (int)$this->_getParam('AdID');
        if ($adId < 1) {
            $this->showJson(self::STATUS_FAIL, '广告id非法');
        }

        $adModel = new Model_Ad();
        $ad = $adModel->getByPrimaryId($adId);
        if (!$ad) {
            $this->showJson(self::STATUS_FAIL, '广告id非法');
        }

        try {
            $ad->DeleteTime = date('Y-m-d H:i:s');
            $ad->save();
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '删除失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '删除成功');
    }
}