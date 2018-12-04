ALTER TABLE `weixin_friend_apply`
ADD COLUMN `ApplyTime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '申请时间',

-- 更新之前的申请数据中的申请时间为UpdateTime 虽然不是很准确
UPDATE `weixin_friend_apply`
set ApplyTime = UpdateTime;