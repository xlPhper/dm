-- nick >
ALTER TABLE `message_templates` ADD COLUMN `DepartmentID`  int(11) NOT NULL DEFAULT 0 COMMENT '部门' AFTER `CreateTime`;
ALTER TABLE `message_templates` ADD COLUMN `StartDate`  date NOT NULL DEFAULT '0000-00-00' COMMENT '开始时间' AFTER `DepartmentID`;
ALTER TABLE `message_templates` ADD COLUMN `EndDate`  date NOT NULL DEFAULT '0000-00-00' COMMENT '结束时间' AFTER `StartDate`;
ALTER TABLE `message_templates` ADD COLUMN `TimeQuantum`  varchar(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '时间 段' AFTER `EndDate`;
ALTER TABLE `message_templates` ADD COLUMN `Delay`  int(11) NOT NULL DEFAULT 0 COMMENT '延时 分钟' AFTER `TimeQuantum`;
ALTER TABLE `message_templates` ADD COLUMN `Platform`  tinyint(4) NOT NULL DEFAULT 0 COMMENT '1 开放平台' AFTER `Delay`;
ALTER TABLE `message_templates` MODIFY COLUMN `Keywords`  text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '关键词json' AFTER `Type`;
ALTER TABLE `message_templates` MODIFY COLUMN `ReplyContents`  text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '回复内容json' AFTER `Keywords`;
CREATE INDEX `Type_IsEnable_StartDate_EndDate` ON `message_templates`(`Type`, `IsEnable`, `StartDate`, `EndDate`) USING BTREE ;
CREATE TABLE `speech_craft` (
`SpeechCraftID`  int(11) NOT NULL AUTO_INCREMENT ,
`DepartmentID`  int(11) NOT NULL DEFAULT 0 COMMENT '部门' ,
`CategoryID`  int(11) NOT NULL COMMENT '分类' ,
`Type`  tinyint(4) NOT NULL DEFAULT 0 COMMENT '1 文本 2 图片 4 音频 8 视频' ,
`Remark`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`Content`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '内容' ,
`Images`  text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '图片 多张' ,
`Audio`  varchar(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '音频' ,
`Video`  varchar(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '视频' ,
`AdminID`  int(11) NOT NULL DEFAULT 0 COMMENT '管理员' ,
`CreateTime`  timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ,
`UpdateTime`  timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ,
PRIMARY KEY (`SpeechCraftID`)
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
ROW_FORMAT=Compact
;

CREATE TABLE `speech_craft_category` (
`CategoryID`  int(11) NOT NULL AUTO_INCREMENT ,
`DepartmentID`  int(11) NOT NULL ,
`Name`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`ParentID`  int(11) NOT NULL DEFAULT 0 ,
PRIMARY KEY (`CategoryID`)
)
ENGINE=InnoDB
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
ROW_FORMAT=Compact
;
-- < nick