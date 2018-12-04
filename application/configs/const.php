<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/7/10
 * Time: 23:29
 */
//状态
define("STATUS_ABNORMAL", 0);   //异常
define("STATUS_NORMAL", 1);     //正常

//任务状态
define("TASK_STATUS_NOTSTART", 1);     //未开始
define("TASK_STATUS_SEND", 2);        //已发送
define("TASK_STATUS_START", 3);        //运行中
define("TASK_STATUS_FINISHED", 4);     //任务完成
define("TASK_STATUS_UNUSUAL", 5);    //非正常完成，比如切换账号导致
define("TASK_STATUS_PAUSE", 6);        //任务暂停
define("TASK_STATUS_FAILURE", 9);      //任务失败
define("TASK_STATUS_DELETE", 44);      //任务被删除

//任务执行主体
define("TASK_BODY_DEVICE", 1);  //微信号
define("TASK_BODY_WEIXIN", 2);  //微信号
define("TASK_BODY_GROUP", 3);  //群

//任务优先级
define("TASK_LEVAL_LOW", 1);
define("TASK_LEVAL_MEDIUM", 2);
define("TASK_LEVAL_HIGH", 3);
define("TASK_LEVEL_SYSTEM", 9);

//设备工作状态
define("DEVICE_REST", 0);
define("DEVICE_WORK", 1);
define("DEVICE_SWITCH", 2);

//设备在线状态
define("DEVICE_ONLINE", 1);
define("DEVICE_OFFLINE", 0);

//微信在线状态
define("WEIXIN_ONLINE", 1);
define("WEIXIN_OFFLINE", 0);

//群
define("GROUP_SELF", 1);
define("GROUP_OTHER", 2);

//创建群的方式
define("GROUP_CREATE_FACE", 1); //面对面建群
define("GROUP_CREATE_FIREND", 2);   //拉好友建群

// ============== 分类类型 ====================
const CATEGORY_TYPE_PHONE = 'PHONE';                     //手机分类
const CATEGORY_TYPE_WEIXIN = 'WEIXIN';                   //微信标签
const CATEGORY_TYPE_WEIXINFRIEND = 'WX_FRIEND';          //微信好友标签
const CATEGORY_TYPE_WXGROUP = 'WX_GROUP';                //群标签
const CATEGORY_TYPE_CHANNEL = 'CHANNEL';                 //渠道分类
const CATEGORY_TYPE_SENDWEIXIN = 'SENDWEIXIN';           //可发送的微信分类
const CATEGORY_TYPE_POSITION = 'POSITION';           //位置标签

const CATEGORY_TYPE_MATERIAL_GOODS = 'M_GOODS';          //商品素材分类
const CATEGORY_TYPE_MATERIAL_CHARACTER = 'M_CHARACTER';  //人设素材分类
const CATEGORY_TYPE_MATE_TAG = 'MATE_TAG'; // 素材标签
const CATEGORY_TYPE_MATE_PRODUCT_CATE = 'MATE_PRODUCT_CATE'; // 素材商品分类


const CHARACTER_TYPE_GOODS = 'C_GOODS';          //商品素材人设
const CHARACTER_TYPE_CHARACTER = 'C_CHARACTER';  //人设素材人设

const CATEGORY_TYPE_DISTRIBUTION_ARTICLE = 'DIS_ARTICLE';   //派单文案类型

const HEAD_TYPE_GOODS = 'H_GOODS';          //商品素材负责人
const HEAD_TYPE_CHARACTER = 'H_CHARACTER';  //人设素材负责人
const HEAD_TYPE_CONVERT = 'H_CONVERT';      //商品素材负责人
const CATEGORY_TYPE_PRIVILEGE  = "PRIVILEGE";//权限标识
const CATEGORY_TYPES = [
    CATEGORY_TYPE_PHONE,
    CATEGORY_TYPE_SENDWEIXIN,
    CATEGORY_TYPE_WEIXIN,
    CATEGORY_TYPE_WEIXINFRIEND,
    CATEGORY_TYPE_WXGROUP,
    CATEGORY_TYPE_CHANNEL,
    CATEGORY_TYPE_MATERIAL_GOODS,
    CATEGORY_TYPE_MATERIAL_CHARACTER,
    CATEGORY_TYPE_MATE_TAG,
    CATEGORY_TYPE_MATE_PRODUCT_CATE,
    CHARACTER_TYPE_GOODS,
    CHARACTER_TYPE_CHARACTER,
    HEAD_TYPE_GOODS,
    HEAD_TYPE_CHARACTER,
    HEAD_TYPE_CONVERT,
    CATEGORY_TYPE_POSITION,
    CATEGORY_TYPE_DISTRIBUTION_ARTICLE,
    CATEGORY_TYPE_PRIVILEGE
];




// ============== 任务代码 ====================
const TASK_CODE_WEIXIN_ACCEPT_MSG = 'WeixinAcceptMsg';        // 微信接收消息
const TASK_CODE_PHONE_ADD_WX      = 'PhoneAddWx';             // 手机添加微信
const TASK_CODE_WEIXIN_ADD_WX     = 'WeixinAddWx';            // 手机添加微信
const TASK_CODE_FRIEND_JOIN       = 'FriendJoin';             // 添加好友任务【是PhoneAddWx子任务】
const TASK_CODE_WXFRIEND_JOIN     = 'WxFriendJoin';           // 添加好友任务【是WeixinAddWx子任务】
const TASK_CODE_UPDATE_WXINFO     = 'UpdateWxInfo';           // 更新微信信息
const TASK_CODE_UPDATE_WXPOSITION = 'UpdateWxPosition';       // 更新微信位置
const TASK_CODE_REPORT_WXFRIENDS  = 'ReportWxFriends';        // 下发需要回传好友信息的微信号
const TASK_CODE_WEIXIN_FRIEND     = 'WeixinFriend';           //【是ReportWxFriends子任务】
const TASK_CODE_WEIXIN_GROUP      = 'WeixinGroup';            // 发送朋友圈
const TASK_CODE_SEND_CHAT_MSG     = 'SendChatMsg';            // 发送聊天消息, 这个任务通过 socket 直接下发, 不写入任务表(tasks表)
const TASK_CODE_REPORT_WXGROUPS   = 'ReportWxGroups';         // 更新微信群信息
const TASK_CODE_GROUP_ADD_MEMBER  = 'GroupAddMembers';        // 拉人进微信群
const TASK_CODE_GROUP_CREATE      = 'GroupCreate';            // 创建群
const TASK_CODE_GROUP_JOIN        = 'GroupJoin';              // 加入群
const TASK_CODE_GROUP_QUIT        = 'GroupQuit';              // 退出群
const TASK_CODE_GROUP_TRANSFER    = 'GroupTransfer';          // 转移群
const TASK_CODE_GROUP_QRIMG       = 'GroupQrimg';             // 更新群二维码
const TASK_CODE_ALBUM_LIKE        = 'AlbumLike';              // 朋友圈点赞
const TASK_CODE_ALBUM_UNLIKE      = 'AlbumUnlike';            // 朋友圈取消点赞
const TASK_CODE_ALBUM_COMMENT     = 'AlbumComment';           // 朋友圈评论
const TASK_CODE_ALBUM_COMMENT_DEL = 'AlbumCommentDel';        // 朋友圈删除评论
const TASK_CODE_ALBUM_DELETE      = 'AlbumDelete';            // 删除朋友圈
const TASK_CODE_ALBUM_SYNC        = 'AlbumSync';              // 同步朋友圈
const TASK_CODE_ALBUM_RESOURCE    = 'AlbumResource';          // 同步朋友圈资源(图片视频等)
const TASK_CODE_DETECTION_PHONE   = 'DetectionPhone';         // 检测手机是否是微信号
const TASK_CODE_MSG_BIG_IMG       = 'MsgBigImg';              // 消息大图
const TASK_CODE_RANDOM_POSITION   = 'RandomPosition';         // 随机定位
const TASK_CODE_RESETTING         = 'Resetting';              // 设备初始化
const TASK_CODE_FRIEND_GROUP_SEND = 'WxFriendGroupSend';      // 微信好友群发
const TASK_CODE_GET_GZHURL_VIEWNUM = 'GetGZHUrlViewNum';      // 获取公众号文章链接中的阅读数
const TASK_CODE_DETECTION_URL     = 'DetectionUrl';           // 获取URL中的地址信息
const TASK_CODE_VIEW_NEWS         = 'ViewNews';               // 养号_查看新闻
const TASK_CODE_VIEW_MESSAGE      = 'ViewMessage';            // 养号_查看消息
const TASK_CODE_SWITCHES          = 'WxSwitches';             // 功能开关
const TASK_CODE_FRIEND_PASS       = 'WxFriendPass';           // 好友申请自动通过
const TASK_CODE_AD_CLICK          = 'AdClick';                // 广告点击
const TASK_CODE_DEVICE_NETWORK    = 'DeviceNetwork';          // 设置设备网络
const TASK_CODE_PRIVACY_SETTINGS  = 'WxPrivacySettings';      // 微信隐私设置
const TASK_CODE_NEAR_PEOPLE       = 'WxNearPeople';           // 微信附近的人
const TASK_CODE_FRIEND_APPLY_DEAL = 'WxFriendApplyDeal';      // 微信好友申请处理
const TASK_CODE_RESOURCE_SYNC     = 'ResourceSync';           // 同步资源
const TASK_CODE_TRAIN_ALBUM_DEAL  = 'TrainAlbumDeal';         // 养号模拟朋友圈操作:浏览朋友圈随机点赞
const TASK_CODE_WEIXIN_QRCODE     = 'WeixinQrcode';           // 更新微信二维码
const TASK_CODE_ACTION_POWER      = 'ActionPower';            // 手机充电/断电任务
const TASK_CODE_GROUP_MEMBERS     = 'GroupMembers';           // 同步群成员


// 任务标识所对于的任务
const TASK_CODE = [
    TASK_CODE_PHONE_ADD_WX        => '微信添加手机号为好友',
    TASK_CODE_WEIXIN_ADD_WX       => '微信添加微信号为好友',
    TASK_CODE_UPDATE_WXINFO       => '更新微信信息',
    TASK_CODE_UPDATE_WXPOSITION   => '更新微信位置',
    TASK_CODE_REPORT_WXFRIENDS    => '传好友信息',
    TASK_CODE_WEIXIN_GROUP        => '发送朋友圈',
    TASK_CODE_FRIEND_JOIN         => '添加好友子任务',
    TASK_CODE_WXFRIEND_JOIN       => '微信添加好友子任务',
    TASK_CODE_WEIXIN_FRIEND       => '返回微信好友信息子任务',
    TASK_CODE_REPORT_WXGROUPS     => '更新微信群信息',
    TASK_CODE_GROUP_ADD_MEMBER    => '拉人进微信群',
    TASK_CODE_GROUP_CREATE        => '创建群',
    TASK_CODE_GROUP_JOIN          => '加入群',
    TASK_CODE_GROUP_QUIT          => '退出群',
    TASK_CODE_GROUP_TRANSFER      => '转移群',
    TASK_CODE_GROUP_QRIMG         => '更新群二维码',
    TASK_CODE_ALBUM_LIKE          => '朋友圈点赞',
    TASK_CODE_ALBUM_UNLIKE        => '朋友圈取消点赞',
    TASK_CODE_ALBUM_COMMENT       => '朋友圈评论',
    TASK_CODE_ALBUM_COMMENT_DEL   => '朋友圈删除评论',
    TASK_CODE_ALBUM_DELETE        => '删除朋友圈',
    TASK_CODE_ALBUM_SYNC          => '同步朋友圈',
    TASK_CODE_ALBUM_RESOURCE      => '同步朋友圈资源',
    TASK_CODE_FRIEND_GROUP_SEND   => '微信好友群发',
    TASK_CODE_GET_GZHURL_VIEWNUM  => '获取公众号文章阅读数',
    TASK_CODE_DETECTION_URL       => '获取URL中的地址信息',
    TASK_CODE_VIEW_NEWS           => '养号_查看新闻',
    TASK_CODE_VIEW_MESSAGE        => '养号_查看消息',
    TASK_CODE_SWITCHES            => '功能开关',
    TASK_CODE_FRIEND_PASS         => '好友申请自动通过',
    TASK_CODE_AD_CLICK            => '广告点击',
    TASK_CODE_DEVICE_NETWORK      => '设置设备网络',
    TASK_CODE_PRIVACY_SETTINGS    => '微信隐私设置',
    TASK_CODE_NEAR_PEOPLE         => '设置微信附近的人',
    TASK_CODE_FRIEND_APPLY_DEAL   => '微信好友申请处理',
    TASK_CODE_SEND_CHAT_MSG       => '发送消息',
    TASK_CODE_RESOURCE_SYNC       => '同步资源',
    TASK_CODE_TRAIN_ALBUM_DEAL    => '养号模拟朋友圈随机点赞',
    TASK_CODE_WEIXIN_QRCODE       => '更新微信二维码',
    TASK_CODE_ACTION_POWER        => '手机充电/断电任务',
    TASK_CODE_GROUP_MEMBERS       => '同步群成员'

];

// 任务状态所对于的描述
const TASK_STATUS = [
    TASK_STATUS_NOTSTART   => '未开始',
    TASK_STATUS_SEND       => '已发送-未执行',
    TASK_STATUS_START      => '运行中',
    TASK_STATUS_FINISHED   => '任务完成',
    TASK_STATUS_UNUSUAL    => '非正常完成',
    TASK_STATUS_PAUSE      => '任务暂停',
    TASK_STATUS_FAILURE    => '任务失败',
    TASK_STATUS_DELETE     => '任务删除'
];

const Order_Status_WaitShip  = 0;
const Order_Status_WaitShip4 = 4;
const Order_Status_Ship      = 1;
const Order_Status_Success   = 2;
const Order_Status_Fail      = 3;
const Order_Status = [
    Order_Status_WaitShip  => "未推单待发货",
    Order_Status_Ship      => "已发货",
    Order_Status_Success   => "交易成功",
    Order_Status_WaitShip4 => "已推单待发货",
    Order_Status_Fail      => "交易失败"
];
const Order_PaymentMethod = [
    1 => "现金付款",
    2 => "货到付款"
];

const detectionPhoneErrorCode = [
     0=>   '发送成功',
    -1=>   '微信搜索接口数据回调错误',
    -2=>   'Socket没有连接上',
    -4=>   '用户不存在',
    -24=>  '搜索帐号异常,无法显示',
    -25=>  '搜索过于频繁,稍后再试',
    -100=> '登录过期,重新登录'
];

const Resource_Content = 1;
const Resource_Image = 2;
const Resource_Audio = 4;
const Resource_Video = 8;

const PLATFORM_GROUP = 'GROUP';
const PLATFORM_OPEN = 'OPEN';