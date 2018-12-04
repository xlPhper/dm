CREATE TABLE `monitor_words` (
  `WordID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Word` varchar(255) NOT NULL DEFAULT '' COMMENT '监控词',
  `AdminID` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '管理员ID',
  `CreateTime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '创建时间',
  PRIMARY KEY (`WordID`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='监控词表';

-- 增加好友表排序
ALTER TABLE `wx_group`.`weixin_friends`
ADD COLUMN `DisplayOrder` int(255) UNSIGNED NOT NULL DEFAULT 0 COMMENT '显示排序,大的排上面' AFTER `ChatRate`;

-- 增加语音字段
ALTER TABLE `wx_group`.`messages`
ADD COLUMN `AudioMp3` varchar(255) NOT NULL DEFAULT '' COMMENT '音频的mp3地址' AFTER `TranTime`,
ADD COLUMN `AudioStatus` tinyint(255) UNSIGNED NOT NULL DEFAULT 1 COMMENT '音频状态:1无需转换2待转换3转换成功' AFTER `AudioMp3`;
ALTER TABLE `wx_group`.`messages`
ADD INDEX `AudioStatus`(`AudioStatus`) USING BTREE;