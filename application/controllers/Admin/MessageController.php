<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_MessageController extends AdminBase
{
    public function indexAction()
    {
        $this->showJson(1, 'ok', ['controller' => 'admin_message/index']);
    }

    /**
     * 发送聊天消息
     */
    public function chatSendAction()
    {

    }

    /**
     * 左侧聊天列表
     */
    public function chatLeftListAction()
    {
        // 参数:昵称
        // 头像 / 昵称 / 最新消息时间 / 未读条数 / 最后一条消息
    }

    /**
     * 右侧消息列表(上拉刷新)
     */
    public function chatRightMsgsAction()
    {

    }

    /**
     * 模板列表
     */
    public function templateListAction()
    {
        /**
         * 查询参数: 规则名称/关键词
        返回参数: 模板id/名称/关键词(第一个)/关键词数组/回复内容(第一条)/回复内容数组/标签名称
         */
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 100);

        $name = trim($this->_getParam('Name', ''));
        $keyword = trim($this->_getParam('Keyword', ''));
        $content = trim($this->_getParam('Content', ''));
        $type = trim($this->_getParam('Type'));
        $weixin = trim($this->_getParam('Weixin'));
        if (!in_array($type, ['QUICK', 'AUTO', 'WELCOME'])) {
            $this->showJson(self::STATUS_FAIL, '模板类型必填');
        }
        $tagIds = trim($this->_getParam('WxTagIDs', ''));

        $model = new Model_MessageTemplate();
        $select = $model->fromSlaveDB()->select()->where('Type = ?', $type);
        if ($name !== '') {
            $select->where('Name like ?', '%'.$name.'%');
        }
        if ($keyword !== '') {
            $select->where('Keywords like ?', '%'.$keyword.'%');
        }
        if ($content !== '') {
            $select->where('ReplyContents like ?', '%'.$content.'%');
        }
        if ($weixin !== '') {
            // 获取微信所对应的标签
            $wx = (new Model_Weixin())->fetchRow(['Weixin = ?' => $weixin]);
            if (!$wx) {
                $this->showJson(self::STATUS_FAIL, '微信不存在');
            }
            $conditions = ["WxTagIDs = ''"];
            if ($wx['CategoryIds']) {
                $categoryIds = explode(',', $wx['CategoryIds']);
                foreach ($categoryIds as $categoryId) {
                    $categoryId = intval($categoryId);
                    if ($categoryId > 0) {
                        $conditions[] = 'find_in_set(' . $categoryId . ', WxTagIDs)';
                    }
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }

        if ($tagIds !== '') {
            $tagIds = explode(',', $tagIds);
            $conditions = [];
            foreach ($tagIds as $tagId) {
                $tagId = (int)$tagId;
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', WxTagIDs)';
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }

        $adminModel = new Model_Role_Admin();
        if($this->admin["IsSuper"] == "Y"){
            $DepartmentIDs = (new Model_Department())->getParentID($this->admin["CompanyId"]);
            if(!count($DepartmentIDs)){
                $DepartmentIDs[] = -1;
            }
            $select->where("DepartmentID in (?)",$DepartmentIDs);
        }else{
            $DepartmentID = $adminModel->getDependentParentID($this->admin["AdminID"]);
            $DepartmentID = empty($DepartmentID)?-1:$DepartmentID;
            $select->where("DepartmentID = ?",$DepartmentID);
        }

        $select->order('TemplateID desc');

        $res = $model->getResult($select, $page, $pagesize);

        $wxtModel = new Model_Category();

        foreach ($res['Results'] as &$d) {
            if ($type == 'AUTO') {
                $keywords = json_decode($d['Keywords'], 1);
                $d['FirstKeyword'] = $keywords[0]['Keyword'];
                $contents = json_decode($d['ReplyContents'], 1);
                $d['FirstContent'] = $contents[0]['Content'];
                $d['Keywords'] = $keywords;
                $d['ReplyContents'] = $contents;
            }
            if ($d['WxTagIDs'] != '') {
                $tagIds = explode(',', $d['WxTagIDs']);
                $wxTags = $wxtModel->fetchAll(['Type = ?' => CATEGORY_TYPE_WEIXIN, 'CategoryID in (?)' => $tagIds]);
                $tmpTagNames = [];
                foreach ($wxTags as $wxTag) {
                    $tmpTagNames[] = $wxTag['Name'];
                }
                $d['WxTagNames'] = implode(',', $tmpTagNames);
            } else {
                $d['WxTagNames'] = '';
            }
        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    /**
     * 模板详情
     */
    public function templateDetailAction()
    {
        $templateId = (int)$this->_getParam('TemplateID');
        if ($templateId < 1) {
            $this->showJson(self::STATUS_FAIL, '参数非法');
        }

        $model = new Model_MessageTemplate();
        $template = $model->fromSlaveDB()->fetchRow(['TemplateID = ?' => $templateId]);
        if (!$template) {
            $this->showJson(self::STATUS_FAIL, '模板id非法');
        }

        $template = $template->toArray();
        if ($template['Type'] == 'AUTO') {
            $template['Keywords'] = json_decode($template['Keywords'], 1);
            $template['ReplyContents'] = json_decode($template['ReplyContents'], 1);
        } elseif ($template['Type'] == 'WELCOME') {
            $template['Keywords'] = json_decode($template['Keywords'], 1);
            $template['ReplyContents'] = json_decode($template['ReplyContents'], 1);
        }

        $this->showJson(self::STATUS_OK, '操作成功', $template);
    }

    /**
     * 快捷回复模板编辑
     */
    public function tempQuickEditAction()
    {
        $contents = trim($this->_getParam('ReplyContents', ''));
        if ($contents === '') {
            $this->showJson(self::STATUS_FAIL, '回复内容非法');
        }
        $wxTagIds = trim($this->_getParam('WxTagIDs', ''));
        $tagIds = '';
        if ($wxTagIds !== '') {
            $wxTagIds = explode(',', $wxTagIds);
            $tmpWxTagIds = [];
            foreach ($wxTagIds as $wxTagId) {
                $wxTagId = (int)$wxTagId;
                if ($wxTagId > 0) {
                    $tmpWxTagIds[] = $wxTagId;
                }
            }
            if ($tmpWxTagIds) {
                $wxTags = (new Model_Category())->fetchAll(['Type = ?' => CATEGORY_TYPE_WEIXIN, 'CategoryID in (?)' => $tmpWxTagIds]);
                if (count($wxTags) != count($tmpWxTagIds)) {
                    $this->showJson(self::STATUS_FAIL, '存在非法标签');
                }
                $tagIds = implode(',', $tmpWxTagIds);
            }
        }
        $templateId = (int)$this->_getParam('TemplateID');

        $model = new Model_MessageTemplate();

        try {
            // 名称/关键词/回复内容/回复方式/标签(默认全部)
            $data = [
                'Type' => 'QUICK',
                'Name' => '',
                'Keywords' => '',
                'ReplyContents' => $contents,
                'ReplyType' => '',
                'WxTagIDs' => $tagIds,
            ];
            if ($templateId > 0) {
                $template = $model->fetchRow(['TemplateID = ?' => $templateId]);
                if (!$template) {
                    throw new \Exception('非法的模板id');
                }
                $model->update($data, ['TemplateID = ?' => $templateId]);
            } else {
                $data['CreateTime'] = date('Y-m-d H:i:s');
                $templateId = $model->insert($data);
            }
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功', ['TemplateID' => $templateId]);
    }

    /**
     * 自动回复模板编辑
     */
    public function tempAutoEditAction()
    {
        $name = trim($this->_getParam('Name', ''));
        // [{"Type":"REFER","Keyword":"aaa"},{"Type":"REG", "Keyword":"bbb"}]
        $keywords = trim($this->_getParam('Keywords', ''));
        // [{"Type":"TEXT","Content":"xxx"}, {"Type":"IMG","Content":"http://img..."},{"Type":"LINK","Content":"http://..."},{"Type":"VIDEO","Content":"http://..."}]
        $contents = trim($this->_getParam('ReplyContents', ''));
        $replyType = trim($this->_getParam('ReplyType', ''));
        $wxTagIds = trim($this->_getParam('WxTagIDs', ''));
        $isEnable = trim($this->_getParam('IsEnable', ''));

        $StartDate = trim($this->_getParam("StartDate"));
        $EndDate = trim($this->_getParam("EndDate"));
        $TimeQuantum = trim($this->_getParam("TimeQuantum"));
        $Delay = trim($this->_getParam("Delay"));
        $Platform = 0;
        $DepartmentID = (int)$this->_getParam("DepartmentID",0);
        if($DepartmentID == 0){
            $this->showJson(0,"请选择部门");
        }
        if($this->isOpenPlatform()){
            $Platform = 1;
            if(empty($StartDate)){
                $this->showJson(0,"请选择开始日期");
            }
            if(empty($EndDate)){
                $this->showJson(0,"请选择结束日期");
            }
            if(empty($TimeQuantum)){
                $this->showJson(0,"请选择添加时间段");
            }
        }
        if (!in_array($isEnable, ['Y', 'N'])) {
            $this->showJson(self::STATUS_FAIL, '是否自动开启非法');
        }

        $templateId = (int)$this->_getParam('TemplateID');

        if ($name === '') {
            $this->showJson(self::STATUS_FAIL, '规则名称非法');
        }
        if ($keywords === '') {
            $this->showJson(self::STATUS_FAIL, '关键词非法');
        }
        if ($contents === '') {
            $this->showJson(self::STATUS_FAIL, '回复内容非法');
        }
        if (!in_array($replyType, ['ALL', 'RAND'])) {
            $this->showJson(self::STATUS_FAIL, '回复方式非法');
        }
        $keywordsArr = json_decode($keywords, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(self::STATUS_FAIL, '关键词非法');
        }
        $contentsArr = json_decode($contents, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(self::STATUS_FAIL, '回复内容非法');
        }
        $tmpKeywordsArr = $tmpContentsArr = [];
        foreach ($keywordsArr as $k) {
            if (!isset($k['Type']) || !isset($k['Keyword']) || !in_array($k['Type'], ['REFER', 'REG']) || trim($k['Keyword']) === '') {
                $this->showJson(self::STATUS_FAIL, '关键词格式非法');
            }
            $tmpKeywordsArr[] = [
                'Type' => $k['Type'],
                'Keyword' => trim($k['Keyword'])
            ];
        }
        foreach ($contentsArr as $c) {
            if (!isset($c['Type']) || !isset($c['Content']) || !in_array($c['Type'], ['TEXT', 'IMG', 'LINK','AUDIO' ,'VIDEO']) || trim($c['Content']) === '') {
                $this->showJson(self::STATUS_FAIL, '回复内容格式非法');
            }
            $tmpContentsArr[] = [
                'Type' => $c['Type'],
                'Content' => $c['Content']
            ];
        }
        $tagIds = '';
        if ($wxTagIds !== '') {
            $wxTagIds = explode(',', $wxTagIds);
            $tmpWxTagIds = [];
            foreach ($wxTagIds as $wxTagId) {
                $wxTagId = (int)$wxTagId;
                if ($wxTagId > 0) {
                    $tmpWxTagIds[] = $wxTagId;
                }
            }
            if ($tmpWxTagIds) {
                $wxTags = (new Model_Category())->fetchAll(['Type = ?' => CATEGORY_TYPE_WEIXIN, 'CategoryID in (?)' => $tmpWxTagIds]);
                if (count($wxTags) != count($tmpWxTagIds)) {
                    $this->showJson(self::STATUS_FAIL, '存在非法标签');
                }
                $tagIds = implode(',', $tmpWxTagIds);
            }
        }

        $model = new Model_MessageTemplate();

        try {
            // 名称/关键词/回复内容/回复方式/标签(默认全部)
            $data = [
                'Type' => 'AUTO',
                'Name' => $name,
                'Keywords' => json_encode($tmpKeywordsArr),
                'ReplyContents' => json_encode($tmpContentsArr),
                'ReplyType' => $replyType,
                'WxTagIDs' => $tagIds,
                'IsEnable' => $isEnable,
                "StartDate" => $StartDate,
                "EndDate" => $EndDate,
                "DepartmentID" => $DepartmentID,
                "Platform" => $Platform,
                "Delay" => $Delay,
                "TimeQuantum" => $TimeQuantum
            ];
            if ($templateId > 0) {
                $template = $model->fetchRow(['TemplateID = ?' => $templateId]);
                if (!$template) {
                    throw new \Exception('非法的模板id');
                }
                $model->update($data, ['TemplateID = ?' => $templateId]);
            } else {
                $data['CreateTime'] = date('Y-m-d H:i:s');
                $templateId = $model->insert($data);
            }
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功', ['TemplateID' => $templateId]);
    }

    /**
     * 欢迎语编辑
     */
    public function tempWelcomeEditAction()
    {
        // 规则名称, 回复内容, 回复方式, 标签
        $name = trim($this->_getParam('Name', ''));
        // [{"Type":"TEXT","Content":"xxx"}, {"Type":"IMG","Content":"http://img..."},{"Type":"LINK","Content":"http://..."},{"Type":"VIDEO","Content":"http://..."}]
        $contents = trim($this->_getParam('ReplyContents', ''));
        $replyType = trim($this->_getParam('ReplyType', ''));
        $wxTagIds = trim($this->_getParam('WxTagIDs', ''));
        $isEnable = trim($this->_getParam('IsEnable', ''));
        if (!in_array($isEnable, ['Y', 'N'])) {
            $this->showJson(self::STATUS_FAIL, '是否自动开启非法');
        }

        $templateId = (int)$this->_getParam('TemplateID');

        if ($name === '') {
            $this->showJson(self::STATUS_FAIL, '规则名称非法');
        }
        if ($contents === '') {
            $this->showJson(self::STATUS_FAIL, '回复内容非法');
        }
        if (!in_array($replyType, ['ALL', 'RAND'])) {
            $this->showJson(self::STATUS_FAIL, '回复方式非法');
        }
        $contentsArr = json_decode($contents, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(self::STATUS_FAIL, '回复内容非法');
        }
        $tmpKeywordsArr = $tmpContentsArr = [];
        foreach ($contentsArr as $c) {
            if (!isset($c['Type']) || !isset($c['Content']) || !in_array($c['Type'], ['TEXT', 'IMG', 'LINK', 'VIDEO']) || trim($c['Content']) === '') {
                $this->showJson(self::STATUS_FAIL, '回复内容格式非法');
            }
            $tmpContentsArr[] = [
                'Type' => $c['Type'],
                'Content' => $c['Content']
            ];
        }
        $tagIds = '';
        if ($wxTagIds !== '') {
            $wxTagIds = explode(',', $wxTagIds);
            $tmpWxTagIds = [];
            foreach ($wxTagIds as $wxTagId) {
                $wxTagId = (int)$wxTagId;
                if ($wxTagId > 0) {
                    $tmpWxTagIds[] = $wxTagId;
                }
            }
            if ($tmpWxTagIds) {
                $wxTags = (new Model_Category())->fetchAll(['Type = ?' => CATEGORY_TYPE_WEIXIN, 'CategoryID in (?)' => $tmpWxTagIds]);
                if (count($wxTags) != count($tmpWxTagIds)) {
                    $this->showJson(self::STATUS_FAIL, '存在非法标签');
                }
                $tagIds = implode(',', $tmpWxTagIds);
            }
        }

        $model = new Model_MessageTemplate();

        try {
            // 名称/关键词/回复内容/回复方式/标签(默认全部)
            $data = [
                'Type' => 'WELCOME',
                'Name' => $name,
                'Keywords' => '',
                'ReplyContents' => json_encode($tmpContentsArr),
                'ReplyType' => $replyType,
                'WxTagIDs' => $tagIds,
                'IsEnable' => $isEnable
            ];
            if ($templateId > 0) {
                $template = $model->fetchRow(['TemplateID = ?' => $templateId]);
                if (!$template) {
                    throw new \Exception('非法的模板id');
                }
                $model->update($data, ['TemplateID = ?' => $templateId]);
            } else {
                $data['CreateTime'] = date('Y-m-d H:i:s');
                $templateId = $model->insert($data);
            }
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功', ['TemplateID' => $templateId]);
    }

    /**
     * 模板删除
     */
    public function templateDelAction()
    {
        $templateId = (int)$this->_getParam('TemplateID');
        if ($templateId < 1) {
            $this->showJson(self::STATUS_FAIL, '参数非法');
        }

        $model = new Model_MessageTemplate();
        $template = $model->fetchRow(['TemplateID = ?' => $templateId]);
        if (!$template) {
            $this->showJson(self::STATUS_FAIL, '模板id非法');
        }

        try {
            $template->delete();
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '删除失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功');
    }

    /**
     * 模板开关
     */
    public function templateEnableAction()
    {
        $templateId = (int)$this->_getParam('TemplateID');
        if ($templateId < 1) {
            $this->showJson(self::STATUS_FAIL, '参数非法');
        }
        $enable = trim($this->_getParam('IsEnable'));
        if (!in_array($enable, ['Y', 'N'])) {
            $this->showJson(self::STATUS_FAIL, '开关参数非法');
        }

        $model = new Model_MessageTemplate();
        $template = $model->fetchRow(['TemplateID = ?' => $templateId]);
        if (!$template) {
            $this->showJson(self::STATUS_FAIL, '模板id非法');
        }

        try {
            $template->IsEnable = $enable;
            $template->save();
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功');
    }

    /**
     * 模板标签
     */
    public function templateTagAction()
    {
        $templateId = (int)$this->_getParam('TemplateID');
        if ($templateId < 1) {
            $this->showJson(self::STATUS_FAIL, '参数非法');
        }
        $model = new Model_MessageTemplate();
        $template = $model->fetchRow(['TemplateID = ?' => $templateId]);
        if (!$template) {
            $this->showJson(self::STATUS_FAIL, '模板id非法');
        }

        $wxTagIds = trim($this->_getParam('WxTagIDs', ''));
        $tagIds = '';
        if ($wxTagIds !== '') {
            $wxTagIds = explode(',', $wxTagIds);
            $tmpWxTagIds = [];
            foreach ($wxTagIds as $wxTagId) {
                $wxTagId = (int)$wxTagId;
                if ($wxTagId > 0) {
                    $tmpWxTagIds[] = $wxTagId;
                }
            }
            if ($tmpWxTagIds) {
                $wxTags = (new Model_Category())->fetchAll(['Type = ?' => CATEGORY_TYPE_WEIXIN, 'CategoryID in (?)' => $tmpWxTagIds]);
                if (count($wxTags) != count($tmpWxTagIds)) {
                    $this->showJson(self::STATUS_FAIL, '存在非法标签');
                }
                $tagIds = implode(',', $tmpWxTagIds);
            }
        }

        try {
            $template->WxTagIDs = $tagIds;
            $template->save();
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功');
    }
}