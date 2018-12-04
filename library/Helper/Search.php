<?php

class Helper_Search
{
    /**
     * @return \Elasticsearch\Client
     */
    public static $client = null;

    /**
     * @return \Elasticsearch\Client
     */
    public static function getClient()
    {
        if (!static::$client || !static::$client instanceof \Elasticsearch\Client) {
            $config = Zend_Registry::get("config");
            $searchConfig = $config['search']['api'];

            static::$client = \Elasticsearch\ClientBuilder::create()->setHosts($searchConfig['host'])->build();
        }

        return static::$client;
    }

    const INDEX_WXFRIENDS = 'wxfriends';
    const INDEX_MESSAGES = 'messages';

    /**
     * 全量更新时间
     */
    const FULL_UPDATE_HOUR = 4;

    /**
     * 获取昨天的索引名
     */
    public static function getYesterdayIndexName($index)
    {
        return $index . '_' . date('Ymd', strtotime('-1 days'));
    }

    /**
     * 获取今天的索引名
     */
    public static function getTodayIndexName($index)
    {
        return $index . '_' . date('Ymd');
    }

    /**
     * 获取当前索引名称(根据更新时间获取)
     */
    public static function getCurrentIndexName($index)
    {
        $h = date('G');
        if ($h >= 0 && $h < self::FULL_UPDATE_HOUR) {
            return self::getYesterdayIndexName($index);
        } else {
            return self::getTodayIndexName($index);
        }
    }

    /**
     * reindex
     */
    public static function reindexByAlias($index)
    {
        if (self::getClient()->indices()->exists(['index' => self::getTodayIndexName($index)])) {
            $results = self::getClient()->indices()->putAlias(['index' => self::getTodayIndexName($index), 'name' => $index]);
            // 如果索引更新成功, 则删除昨天的索引别名, 并删除昨天的索引
            // todo: 判断是否更新成功
            if ($results && self::getClient()->indices()->exists(['index' => self::getYesterdayIndexName($index)])) {
                $results = self::getClient()->indices()->deleteAlias(['index' => self::getYesterdayIndexName($index), 'name' => $index]);
                self::getClient()->indices()->delete(['index' => self::getYesterdayIndexName($index)]);
            }
        }
    }

    /**
     * 微信好友
     */
    public static function mappingWxfriends($indexName)
    {
        $wxMapping = array(
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 3,
                    'number_of_replicas' => 0
                ],
                'mappings' => [
                    'friend' => [
                        'properties' => [
                            'FriendID' => ['type' => 'integer'],
                            'WeixinID' => ['type' => 'integer'],
                            'Weixin' => ['type' => 'text'],
                            'WeixinAlias' => ['type' => 'text'],
                            'WeixinNick' => ['type' => 'text'],
                            'WeixinAvatar' => ['type' => 'text',],
                            'CategoryIDs' => ['type' => 'text'],
                            'FriendAccount' => ['type' => 'text'],
                            'FriendAlias' => ['type' => 'text'],
                            'FriendNick' => ['type' => 'text'],
                            'FriendAvatar' => ['type' => 'text',],
                            'Customer' => ['type' => 'text'],
                            'AddDate' => ['type' => 'integer'],
                            'UnreadNum' => ['type' => 'integer'],
                            'LastMsgID' => ['type' => 'integer'],
                            'LastAlbumID' => ['type' => 'integer'],
                            'IsDeleted' => ['type' => 'integer'],
                            'ChatroomID' => ['type' => 'integer'],
                            'ChatRate' => ['type' => 'integer'],
                            'DisplayOrder' => ['type' => 'integer']
                        ]
                    ]
                ]
            ]
        );

        self::getClient()->indices()->create($wxMapping);
    }


    public static function bulkWxfriends($startId = 0, $limit = 3000)
    {
        $s = Model_Weixin_Friend::getInstance()->fromSlaveDB()->select()
            ->where('FriendID > ?', $startId)
            ->order('FriendID')
            ->limit($limit);
        $wxFriends = Model_Weixin_Friend::getInstance()->fromSlaveDB()->fetchAll($s)->toArray();
        if (empty($wxFriends)) {
            return 0;
        }

        $tmpWeixins = [];
        $maxFriendId = 0;
        $bulk = ['index' => self::getTodayIndexName(self::INDEX_WXFRIENDS), 'type' => 'friend'];
        foreach ($wxFriends as $wxFriend) {
            $bulk['body'][] = [
                'index' => ['_id' => $wxFriend['FriendID']]
            ];

            $data = [
                'FriendID' => (int)$wxFriend['FriendID'],
                'WeixinID' => (int)$wxFriend['WeixinID'],
                'CategoryIDs' => $wxFriend['CategoryIDs'],
                'FriendAccount' => $wxFriend['Account'],
                'FriendAlias' => $wxFriend['Alias'],
                'FriendNick' => $wxFriend['NickName'],
                'FriendAvatar' => $wxFriend['Avatar'],
                'Customer' => $wxFriend['Customer'],
                'AddDate' => $wxFriend['AddDate'] == '0000-00-00 00:00:00' ? 0 : strtotime($wxFriend['AddDate']),
                'LastMsgID' => $wxFriend['LastMsgID'],
                'UnreadNum' => $wxFriend['UnreadNum'],
                'LastAlbumID' => $wxFriend['LastAlbumID'],
                'IsDeleted' => $wxFriend['IsDeleted'],
                'ChatroomID' => $wxFriend['ChatroomID'],
                'ChatRate' => $wxFriend['ChatRate'],
                'DisplayOrder' => $wxFriend['DisplayOrder']
            ];
            // 微信查询
            $wxId = $wxFriend['WeixinID'];
            if (!isset($tmpWeixins[$wxId])) {
                $wx = Model_Weixin::getInstance()->fromSlaveDB()->getByPrimaryId($wxFriend['WeixinID']);
                $tmpWeixins[$wxId] = $wx;
            }
            $data['Weixin'] = $tmpWeixins[$wxId]['Weixin'];
            $data['WeixinAlias'] = $tmpWeixins[$wxId]['Alias'];
            $data['WeixinNick'] = $tmpWeixins[$wxId]['Nickname'];
            $data['WeixinAvatar'] = $tmpWeixins[$wxId]['AvatarUrl'];

            $bulk['body'][] = $data;

            $maxFriendId = (int)$wxFriend['FriendID'];
        }

        $result = self::getClient()->bulk($bulk);

        return $maxFriendId;
    }

    /**
     * 消息
     */
    public static function mappingMessages($indexName)
    {
        $wxMapping = array(
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 3,
                    'number_of_replicas' => 0
                ],
                'mappings' => [
                    'message' => [
                        'properties' => [
                            'MessageID' => ['type' => 'integer'],
                            'ReceiverWx' => ['type' => 'text'],
                            'SenderWx' => ['type' => 'text'],
                            'MsgType' => ['type' => 'integer'],
                            'Content' => ['type' => 'text'],
                            'WxMsgSvrId' => ['type' => 'keyword'],
                            'WxCreateTime' => ['type' => 'long'],
                            'AddDate' => ['type' => 'integer'],
                            'SyncTime' => ['type' => 'integer'],
                            'ReadStatus' => ['type' => 'keyword'],
                            'ReadTime' => ['type' => 'integer'],
                            'SendStatus' => ['type' => 'keyword'],
                            'SendTime' => ['type' => 'integer'],
                            'FromClient' => ['type' => 'keyword'],
                            'IsBigImg' => ['type' => 'keyword'],
                            'TranStatus' => ['type' => 'keyword'],
                            'TranTime' => ['type' => 'integer'],
                            'AudioMp3' => ['type' => 'text'],
                            'AudioStatus' => ['type' => 'integer'],
                            'AudioText' => ['type' => 'text']
                        ]
                    ]
                ]
            ]
        );

        self::getClient()->indices()->create($wxMapping);
    }

    public static function bulkMessages($startId = 0, $limit = 3000)
    {
        $s = Model_Message::getInstance()->fromSlaveDB()->select()
            ->where('MessageID > ?', $startId)
            ->order('MessageID')
            ->limit($limit);
        $messages = Model_Message::getInstance()->fromSlaveDB()->fetchAll($s)->toArray();
        if (empty($messages)) {
            return 0;
        }

        $maxMsgId = 0;
        $bulk = ['index' => self::getTodayIndexName(self::INDEX_MESSAGES), 'type' => 'message'];
        foreach ($messages as $m) {
            $bulk['body'][] = [
                'index' => ['_id' => $m['MessageID']]
            ];

            $data = [
                'MessageID' => (int)$m['MessageID'],
                'ReceiverWx' => $m['ReceiverWx'],
                'SenderWx' => $m['SenderWx'],
                'MsgType' => $m['MsgType'],
                'Content' => $m['Content'],
                'WxMsgSvrId' => $m['WxMsgSvrId'],
                'WxCreateTime' => $m['WxCreateTime'],
                'AddDate' => $m['AddDate'] == '0000-00-00 00:00:00' ? 0 : strtotime($m['AddDate']),
                'SyncTime' => $m['SyncTime'] == '0000-00-00 00:00:00' ? 0 : strtotime($m['SyncTime']),
                'ReadStatus' => $m['ReadStatus'],
                'ReadTime' => $m['ReadTime'] == '0000-00-00 00:00:00' ? 0 : strtotime($m['ReadTime']),
                'SendStatus' => $m['SendStatus'],
                'SendTime' => $m['SendTime'] == '0000-00-00 00:00:00' ? 0 : strtotime($m['SendTime']),
                'FromClient' => $m['FromClient'],
                'IsBigImg' => $m['IsBigImg'],
                'TranStatus' => $m['TranStatus'],
                'TranTime' => $m['TranTime'] == '0000-00-00 00:00:00' ? 0 : strtotime($m['TranTime']),
                'AudioMp3' => $m['AudioMp3'],
                'AudioStatus' => $m['AudioStatus'],
                'AudioText' => $m['AudioText'],
            ];

            $bulk['body'][] = $data;

            $maxMsgId = (int)$m['MessageID'];
        }

        $result = self::getClient()->bulk($bulk);

        DM_Log::create('searchIndex')->add(json_encode($result));

        return $maxMsgId;
    }

}