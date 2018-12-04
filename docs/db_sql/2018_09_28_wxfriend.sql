CREATE TABLE `weixin_friend_groupsend` (
  `GroupSendID` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '群发ID',
  `AdminID` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '管理员ID',
  `WeixinTags` varchar(500) NOT NULL DEFAULT '' COMMENT '微信号标签串',
  `FriendTags` varchar(500) NOT NULL DEFAULT '' COMMENT '微信好友标签串',
  `DelWeixinIDs` varchar(500) NOT NULL DEFAULT '' COMMENT '排除的微信号串',
  `Content` text CHARACTER SET utf8mb4 NOT NULL COMMENT '消息内容json,{Type:1,Content:}',
  `Status` tinyint(3) DEFAULT '1' COMMENT '发送状态:1等待执行,2已完成,3已删除',
  `TaskIDs` text COMMENT '关联任务ID串',
  `SendTime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '发送时间',
  `CreateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`GroupSendID`),
  KEY `SendTime` (`SendTime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='微信好友群发任务表';