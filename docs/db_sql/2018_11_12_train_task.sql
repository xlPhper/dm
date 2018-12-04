CREATE TABLE `train_tasks` (
  `TrainTaskID` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '养号任务ID',
  `WeixinTags` varchar(2000) NOT NULL DEFAULT '' COMMENT '微信标签ID串',
  `ViewMessageEnable` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '点未读消息:0关闭,1开启',
  `ViewNewEnable` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '看新闻:0关闭,1开启',
  `AddFriendConfig` varchar(300) NOT NULL DEFAULT '' COMMENT '添加好友配置,{"Enable":"1","DayNum":"10","TotalNum":"50"}',
  `ChatConfig` text COMMENT '好友聊天配置,{"Enable":"1","Time":[{"Start":"10:00","End":"12:00"},{"Start":"16:00","End":"18:00"}]}',
  `SendAlbumConfig` varchar(500) NOT NULL DEFAULT '' COMMENT '发朋友圈配置,{"Enable":"1","MateTagIDs":"1,2","Start":"16:00","End":"18:00","DayNum":"5"}',
  `AlbumInteractConfig` varchar(300) NOT NULL DEFAULT '' COMMENT '朋友圈互动配置,{"Enable":"1","Start":"16:00","End":"18:00","DayNum":"5","LikeNum":"2"}',
  `Status` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '状态:1开始,2暂停',
  `AdminID` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '管理员ID',
  `StartDate` date NOT NULL DEFAULT '0000-00-00' COMMENT '执行开始时间',
  `EndDate` date NOT NULL DEFAULT '0000-00-00' COMMENT '执行结束时间',
  `UpdateTime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '更新时间',
  `CreateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`TrainTaskID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='养号任务表';