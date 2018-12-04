CREATE TABLE `weixin_friend_apply` (
  `FriendApplyID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '申请ID',
  `WeixinID` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '个人号微信ID',
  `Talker` varchar(50) NOT NULL DEFAULT ''  COMMENT '好友微信号',
  `DisplayName` varchar(500) NOT NULL DEFAULT '' COMMENT '好友昵称',
  `Avatar` varchar(200) NOT NULL DEFAULT '' COMMENT '好友头像',
  `FmsgContent` text COMMENT '申请信息,存储后续使用',
  `ContentVerifyContent` varchar(500) NOT NULL DEFAULT '' COMMENT '验证消息',
  `IsNew` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否新申请,0否,1是',
  `State` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '申请状态:0未添加,1已添加',
  `LastModifiedTime` bigint(20) NOT NULL DEFAULT '0' COMMENT '申请最新时间戳',
  `UpdateTime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '更新时间',
  `IsDeleted` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除,0否,1是,2web同意申请假删除',
  PRIMARY KEY (`FriendApplyID`),
  UNIQUE KEY `WeixinID_Talker` (`WeixinID`,`Talker`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='好友申请表';

ALTER TABLE `weixin_friends`
ADD COLUMN `ChatRate` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '互动频率,0无互动,1低频,2中频,3高频';