<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/9/28
 * Time: 16:42
 * 微信好友群发任务表
 */
class Model_Weixin_FriendGroupsend extends DM_Model
{
    public static $table_name = "weixin_friend_groupsend";
    protected $_name = "weixin_friend_groupsend";
    protected $_primary = "GroupSendID";

    const STATUS_PENDING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_DELETED = 3;
}