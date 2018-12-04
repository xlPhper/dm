<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_NoteController extends OpenBase
{

    /**
     * 便签列表
     */
    public function listAction()
    {
        try {

            $adminId = $this->getLoginUserId();

            if (empty($adminId)) {
                $this->showJson(self::STATUS_FAIL, '无法获取管理员信息');
            }

            $noteModel = Model_Note::getInstance();

            $notes = $noteModel->getNoteList($adminId);

            if ($notes){
                $this->showJson(self::STATUS_OK, '管理员便签列表', $notes);

            }else{
                $this->showJson(self::STATUS_OK, '管理员便签列表', []);
            }


        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '抛出异常' . $e->getMessage());
        }

    }

    /**
     * [新增/编辑]便签
     */
    public function saveAction()
    {
        $noteId = $this->_getParam('NoteID',null);
        $content = $this->_getParam('Content',null);
        $status = $this->_getParam('Status',null);

        $noteModel = Model_Note::getInstance();

        if ($noteId){
            if (!empty($content)){

                if (strlen($content)>=255){
                    $this->showJson(self::STATUS_FAIL, '内容控制在255个字节');
                }

                $data['Content'] = $content;
            }
            if (!empty($status)){
                $data['Status'] = $status;
            }
            if (empty($data)){
                $this->showJson(self::STATUS_FAIL, '请进行有效的编辑');
            }
            $noteModel->update($data,['NoteID = ?'=>$noteId]);
            $id = $noteId;

        }else{
            $adminId = $this->getLoginUserId();

            if (empty($adminId)) {
                $this->showJson(self::STATUS_FAIL, '无法获取管理员信息');
            }

            // 查询当前管理员已经还有几条未执行的
            $notes = $noteModel->getNoteList($adminId);

            if (count($notes) >= 10){
                $this->showJson(self::STATUS_FAIL, '当前任务超负荷~建议先完成一部分喔');
            }

            $id = $noteModel->insert(['Content'=>$content,'CreateTime'=>date('Y-m-d H:i:s'),'AdminID'=>$adminId]);
        }

        $this->showJson(self::STATUS_OK, '管理员便签列表',$id);
    }

}