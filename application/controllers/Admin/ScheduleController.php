<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_ScheduleController extends AdminBase
{
    public function materialsAction()
    {
        $startDate = trim($this->_getParam('StartDate'));
        $endDate = trim($this->_getParam('EndDate'));
        $type = (int)$this->_getParam('Type', 1);
        $productCateId = (int)$this->_getParam('ProductCateID');
        $tagIds = trim($this->_getParam('TagIDs'));
        $mateIds = trim($this->_getParam('MateIDs'));
        $num = (int)$this->_getParam('Num');
        $textContent = trim($this->_getParam('TextContent', ''));
        $name = trim($this->_getParam('MaterialName', ''));

        if ($mateIds) {
            $mateIds = explode(',', $mateIds);
        } else {
            $mateIds = [];
        }
//        $dateNums = (strtotime($endDate.' 00:00:00') - strtotime($startDate. ' 00:00:00')) / 86400;
//        $limit = $num * $dateNums;

        $model = new Model_Materials();
        $select = $model->fromSlaveDB()->select()
            ->where('Type = ?', $type)
            ->where('Status = ?', '1');
        if ($mateIds) {
            $select->where('MaterialID not in (?)', $mateIds);
        }
        if ($tagIds) {
            $tagIds = explode(',', $tagIds);
            $tmpTagIds = [];
            foreach ($tagIds as $tagId) {
                $tmpTagIds[] = 'find_in_set(' . $tagId . ', TagIDs)';
            }
            $select->where(implode(' OR ', $tmpTagIds));
        }
        if ($startDate) {
            $select->where('AddTime >= ?', $startDate);
        }
        if ($endDate) {
            $select->where('AddTime <= ?', $endDate);
        }
        if ($textContent !== '') {
            $select->where('TextContent like ?', '%' . $textContent . '%');
        }
        if ($productCateId > 0) {
            $childIds = Model_Category::findChildIds($productCateId);
            $childIds[] = $productCateId;
            $select->where('ProductCateID in (?)', $childIds);
        }
        if ($name !== '') {
            $select->where('MaterialName like ?', '%'.$name.'%');
        }
        $select->order('rand()')->limit($num);

        $result = $model->fetchAll($select)->toArray();

        $this->showJson(1, 'ok', $result);
    }

    public function listAction()
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 100);

        // 发布时间, 状态
        // 排期id, 微信号,开始日期,结束日期,商品素材数量,普通素材数量,状态,操作
        $scheModel = new Model_Schedules();

        $startDate = trim($this->_getParam('StartDate', ''));
        $endDate = trim($this->_getParam('EndDate', ''));
        $status = trim($this->_getParam('Status', ''));

        $select = $scheModel->fromSlaveDB()->select()->where('DeleteTime = ?', '0000-00-00 00:00:00');
        if ($startDate !== '') {
            $select->where('StartDate >= ?', $startDate);
        }
        if ($endDate !== '') {
            $select->where('EndDate <= ?', $endDate);
        }
        if ($status != '') {
            $select->where('Status = ?', $status);
        }
        $select->order('ScheduleID DESC');

        $res = $scheModel->getResult($select, $page, $pagesize);
        $wxModel = new Model_Weixin();
        $cateModel = new Model_Category();
        foreach ($res['Results'] as &$d) {
            $weixinIds = explode(',', $d['WeixinIDs']);
            if ($d['WxIdType'] == 'WX_ID') {
                $weixins = $wxModel->fetchAll(['WeixinID in (?)' => $weixinIds])->toArray();
            } else {
                $weixins = $cateModel->fetchAll(['CategoryID in (?)' => $weixinIds])->toArray();
            }
            $tmpWxNames = [];
            foreach ($weixins as $wx) {
                if ($d['WxIdType'] == 'WX_ID') {
                    $tmpWxNames[] = $wx['Nickname'];
                } else {
                    $tmpWxNames[] = $wx['Name'];
                }
            }
            $d['WeixinNames'] = implode(',', $tmpWxNames);
        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    public function detailAction()
    {
        $scheduleId = (int)$this->_getParam('ScheduleID');
        if ($scheduleId < 1) {
            $this->showJson(self::STATUS_FAIL, '排期id非法');
        }

        $scheModel = new Model_Schedules();
        $schedule = $scheModel->fromSlaveDB()->fetchRow(['ScheduleID = ?' => $scheduleId]);
        if (!$schedule) {
            $this->showJson(self::STATUS_FAIL, '排期id非法');
        }

        $schedule = $schedule->toArray();
        $weixinIds = explode(',', $schedule['WeixinIDs']);
        if ($schedule['WxIdType'] == 'WX_ID') {
            $weixins = (new Model_Weixin())->fetchAll(['WeixinID in (?)' => $weixinIds])->toArray();
            $schedule['WeixinInfos'] = $weixins;
        } else {
            $schedule['WeixinInfos'] = [];
        }
        $schedule['ScheConfigs'] = json_decode($schedule['ScheConfigs'], 1);
        $mateModel = new Model_Materials();
        foreach ($schedule['ScheConfigs'] as &$config) {
            $mate = $mateModel->fetchRow(['MaterialID = ?' => $config['MateID']]);
            $config['MateInfo'] = $mate ? $mate->toArray() : [];

        }

        $this->showJson(self::STATUS_OK, '操作成功', $schedule);
    }

    public function deleteAction()
    {
        $scheduleId = (int)$this->_getParam('ScheduleID');
        if ($scheduleId < 1) {
            $this->showJson(self::STATUS_FAIL, '排期id非法');
        }

        $scheModel = new Model_Schedules();
        $schedule = $scheModel->fetchRow(['ScheduleID = ?' => $scheduleId]);
        if (!$schedule) {
            $this->showJson(self::STATUS_FAIL, '排期id非法');
        }

        try {
            $schedule->DeleteTime = date('Y-m-d H:i:s');
            $schedule->save();
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '删除失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '删除成功');
    }

    /**
     * 添加/编辑排期表
     */
    public function editAction()
    {
        $params = $this->getValidParams();

        $scheModel = new Model_Schedules();

        $scheduleId = (int)$this->_getParam('ScheduleID');
        if ($scheduleId > 0) {
            $schedule = $scheModel->fetchRow(['ScheduleID = ?' => $scheduleId]);
            if (!$schedule || $schedule['DeleteTime'] != '0000-00-00 00:00:00') {
                $this->showJson(self::STATUS_FAIL, '参数非法');
            }
            if ($schedule['ExecutedNums'] > 0) {
                $this->showJson(self::STATUS_FAIL, '已经执行过,不允许修改,如果有误,请删除重新添加');
            }
        }

        try {
            if ($scheduleId > 0) {
                $params['UpdateTime'] = date('Y-m-d H:i:s');
                $scheModel->update($params, ['ScheduleID = ?' => $scheduleId]);
            } else {
                $params['AddTime'] = date('Y-m-d H:i:s');
                $scheduleId = $scheModel->insert($params);
            }
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功', ['ScheduleID' => $scheduleId]);
    }

    private function getValidParams()
    {

        $wxIdType = trim($this->_getParam('WxIdType', 'WX_ID'));
        if ($wxIdType != 'WX_ID' && $wxIdType != 'WX_TAG') {
            $this->showJson(self::STATUS_FAIL, '微信id分类非法');
        }
        $wexinIds = $this->_getParam('WeixinIDs', '');
        if ($wexinIds === '') {
            $this->showJson(self::STATUS_FAIL, '微信ids必填');
        }
        $startDate = trim($this->_getParam('StartDate', ''));
        if ($startDate === '') {
            $this->showJson(self::STATUS_FAIL, '开始日期必填');
        }
        $endDate = trim($this->_getParam('EndDate', ''));
        if ($endDate === '') {
            $this->showJson(self::STATUS_FAIL, '结束日期必填');
        }

        // [{"ExecTime":"2018-09-01 06:20:00","MateID":1}, {"ExecTime":"2018-09-01 07:40:00","MateID":2}]
        $scheConfigs = trim($this->_getParam('ScheConfigs', ''));
        if ($scheConfigs === '') {
            $this->showJson(self::STATUS_FAIL, '排期配置必填');
        }
        $scheConfigs = json_decode($scheConfigs, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(self::STATUS_FAIL, '排期配置非法');
        }
        $tmpScheConfigs = [];
        $mateNormalNum = $mateProductNum = 0;
        $mateModel = new Model_Materials();
        foreach ($scheConfigs as $config) {
            if (!isset($config['ExecTime']) || !isset($config['MateID']) || (int)$config['MateID'] < 1) {
                $this->showJson(self::STATUS_FAIL, '排期配置格式非法');
            }
            $execTimeType = Helper_Until::getExecTimeType($config['ExecTime']);
            if (false === $execTimeType) {
                $this->showJson(self::STATUS_FAIL, '排期配置格式时间非法');
            }
            $config['ExecType'] = $execTimeType;

            $mate = $mateModel->fetchRow(['MaterialID = ?' => $config['MateID']]);
            if (!$mate) {
                $this->showJson(self::STATUS_FAIL, '排期配置中素材id'.$config['MateID'].'不存在');
            }
            if ($mate->Type == 1) {
                $mateNormalNum += 1;
            } else {
                $mateProductNum += 1;
            }
            if ($config['ExecType'] == 'RAND') {
                $unix = strtotime($config['ExecTime'] . ':00');
            } else {
                $unix = strtotime($config['ExecTime']);
            }
            $tmpScheConfigs[$unix] = [
                'ExecType' => $execTimeType,
                'ExecTime' => $config['ExecTime'],
                'MateID' => (int)$config['MateID']
            ];
        }
        ksort($tmpScheConfigs);
        $nextRunTime = time();
        foreach ($tmpScheConfigs as $execTime => $tmpConfig) {
            if ($execTime > $nextRunTime) {
                $nextRunTime = $execTime;
                break;
            }
        }
        $nextRunTime = date('Y-m-d H:i:s', $nextRunTime);
        $validScheConfigs = json_encode(array_values($tmpScheConfigs));

        return [
            'WeixinIDs' => $wexinIds,
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'ScheConfigs' => $validScheConfigs,
            'NormalMateNum' => $mateNormalNum,
            'ProductMateNum' => $mateProductNum,
            'NextRunTime' => $nextRunTime,
            'WxIdType' => $wxIdType,
            'AdminID' => $this->getLoginUserId()
        ];
    }

    /**
     * 添加养号排期任务
     */
    public function addYhAction()
    {
        // 微信id/素材id/执行时间
        // [{"ExecTime":"2018-09-01 06:20:00","MateID":1,"WxID":1}, {"ExecTime":"2018-09-01 06:21:00","MateID":2,"WxID":2}]
        $scheConfigs = trim($this->_getParam('ScheConfigs', ''));
        if ($scheConfigs === '') {
            $this->showJson(self::STATUS_FAIL, '排期配置必填');
        }
        $scheConfigs = json_decode($scheConfigs, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(self::STATUS_FAIL, '排期配置非法');
        }
        $tmpScheConfigs = [];
        $mateModel = new Model_Materials();
        $wxModel = new Model_Weixin();
        $mates = $wxs = [];
        foreach ($scheConfigs as $config) {
            if (!isset($config['ExecTime']) || !isset($config['MateID']) || (int)$config['MateID'] < 1 || !isset($config['WxID']) || (int)$config['WxID'] < 1) {
                $this->showJson(self::STATUS_FAIL, '排期配置格式非法');
            }
            $unix = strtotime($config['ExecTime']);
            if (false === $unix) {
                $this->showJson(self::STATUS_FAIL, '排期配置格式时间非法');
            }
            if ($unix < time()) {
                $this->showJson(self::STATUS_FAIL, '排期配置格式时间须大于当前时间');
            }

            if (!isset($mates[$config['MateID']])) {
                $mate = $mateModel->fetchRow(['MaterialID = ?' => $config['MateID']]);
                if (!$mate) {
                    $this->showJson(self::STATUS_FAIL, '排期配置中素材id'.$config['MateID'].'不存在');
                }
                $mates[$config['MateID']] = $mate;
            }
            if (!isset($wxs[$config['WxID']])) {
                $wx = $wxModel->fetchRow(['WeixinID = ?' => $config['WxID']]);
                if (!$wx) {
                    $this->showJson(self::STATUS_FAIL, '排期配置中wxid'.$config['WxID'].'不存在');
                }
                $wxs[$config['WxID']] = $wx;
            }

            $tmpScheConfigs[] = [
                'ExecTime' => date('Y-m-d H:i:s', $unix),
                'MateID' => (int)$config['MateID'],
                'WxID' => (int)$config['WxID']
            ];
        }
        ksort($tmpScheConfigs);

        $taskModel = new Model_Task();
        try {
            $taskModel->getAdapter()->beginTransaction();
            foreach ($tmpScheConfigs as $tmpConfig) {
                $mate = $mates[$tmpConfig['MateID']];
                $nextRunTime = $tmpConfig['ExecTime'];
                $taskConfig = [
                    'ScheduleID' => 0,
                    'MaterialID' => $tmpConfig['MateID'],
                    'TextContent' => $mate['TextContent'],
                    'Comments' => json_decode($mate['Comments'], 1),
                    'MediaType' => (int)$mate['MediaType'],
                    'Position' => $mate['Position'],
                    'Address' => $mate['Address'],
                    'AddressCustom' => $mate['AddressCustom'],
                    'AddressID' => $mate['AddressID'],
                    'AddressName' => $mate['AddressName'],
                    'MediaContent' => $mate['MediaContent']
                ];
                // todo: insert and delete mate
                $taskModel->addCommonTask(TASK_CODE_WEIXIN_GROUP, $tmpConfig['WxID'], json_encode($taskConfig), $this->getLoginUserId(), $nextRunTime);
            }
            $taskModel->getAdapter()->commit();
        } catch (\Exception $e) {
            $taskModel->getAdapter()->rollBack();
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功');

    }
}