CREATE TABLE `departments` (
  `DepartmentID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '部门ID',
  `Name` varchar(255) NOT NULL DEFAULT '' COMMENT '部门名称',
  `ParentID` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '上级部门',
  `AdminID` int(10) NOT NULL DEFAULT '0' COMMENT '创建者管理员ID',
  PRIMARY KEY (`DepartmentID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='部门表';

ALTER TABLE `admins`
ADD COLUMN `DepartmentID` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '部门ID';