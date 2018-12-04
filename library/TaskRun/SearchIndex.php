<?php

/**
 * schedule
 */
class TaskRun_SearchIndex extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE = 'searchIndex';

    /**
     * 执行Daemon任务
     *
     */
    protected function run()
    {
        $this->done();
    }

    protected function done()
    {

        // 如果redis中取到停止指令, 则停止更新
//        if ($redis->get(Helper_Redis::searchIndexQueueStop())) {
//            return;
//        }
        $redis = Helper_Redis::getInstance();
//        var_dump($redis->get(Helper_Redis::searchIndexFinish(Helper_Search::INDEX_WXFRIENDS)));
//        echo $redis->get(Helper_Redis::searchIndexId(Helper_Search::INDEX_WXFRIENDS));
//        $redis->delete(Helper_Redis::searchIndexId(Helper_Search::INDEX_MESSAGES));
//        $redis->delete(Helper_Redis::searchIndexFinish(Helper_Search::INDEX_MESSAGES));exit;
//        var_dump($redis->get(Helper_Redis::searchIndexFinish(Helper_Search::INDEX_MESSAGES)));
//        var_dump($redis->get(Helper_Redis::searchIndexId(Helper_Search::INDEX_MESSAGES)));
//        $redis->delete(Helper_Redis::searchIndexFinish(Helper_Search::INDEX_MESSAGES));
//        $redis->delete(Helper_Redis::searchIndexId(Helper_Search::INDEX_MESSAGES));
//        exit;


        $waitUpdateIndexNames = [
            Helper_Search::INDEX_WXFRIENDS => false,
            Helper_Search::INDEX_MESSAGES => false
        ];
        foreach ($waitUpdateIndexNames as $indexName => $isFinish) {
            if (!$redis->get(Helper_Redis::searchIndexFinish($indexName))) {
                $this->updateIndex($indexName);
            } else {
                $waitUpdateIndexNames[$indexName] = true;
            }
        }

        // 如果都是完成
        if (!in_array(false, $waitUpdateIndexNames)) {
            var_dump($redis->get(Helper_Redis::searchIndexFinish(Helper_Search::INDEX_WXFRIENDS)));
            var_dump($redis->get(Helper_Redis::searchIndexFinish(Helper_Search::INDEX_MESSAGES)));
            var_dump($waitUpdateIndexNames);
            exit;
        }
    }

    protected function updateIndex($indexName)
    {
        $redis = Helper_Redis::getInstance();

        $indexId = (int)$redis->get(Helper_Redis::searchIndexId($indexName));

        $searchClient = Helper_Search::getClient();
        $todayIndexName = Helper_Search::getTodayIndexName($indexName);
        if (!$searchClient->indices()->exists(['index' => $todayIndexName])) {
            $mappingMethod = 'mapping' . ucfirst($indexName);
            Helper_Search::$mappingMethod($todayIndexName);
        }

        $bulkMethod = 'bulk' . ucfirst($indexName);
        $indexId = Helper_Search::$bulkMethod($indexId);
        $this->getLog()->add($indexId);
        $this->getLog()->flush();
        if ($indexId > 0) {
            $redis->setex(Helper_Redis::searchIndexId($indexName), 3600 * 3, $indexId);
        } else {
            Helper_Search::reindexByAlias($indexName);
            $redis->setex(Helper_Redis::searchIndexFinish($indexName), 3600 * 3, 1);
        }
    }

    /**
     * 更新好友索引(此方法被合并到updateIndex)
     */
    protected function updateWxFriendsIndex()
    {
        $redis = Helper_Redis::getInstance();
        $indexName = Helper_Search::INDEX_WXFRIENDS;

        $indexId = (int)$redis->get(Helper_Redis::searchIndexId($indexName));

        $searchClient = Helper_Search::getClient();
        $todayIndexName = Helper_Search::getTodayIndexName($indexName);
        if (!$searchClient->indices()->exists(['index' => $todayIndexName])) {
            Helper_Search::mappingWxfriends($todayIndexName);
        }

        $indexId = Helper_Search::bulkWxfriends($indexId);
        $this->getLog()->add($indexId);
        $this->getLog()->flush();
        if ($indexId > 0) {
            $redis->setex(Helper_Redis::searchIndexId($indexName), 3600, $indexId);
        } else {
            Helper_Search::reindexByAlias($indexName);
            $redis->setex(Helper_Redis::searchIndexFinish($indexName), 3600, 1);
        }
    }

    /**
     * 更新消息索引(此方法被合并到updateIndex)
     */
    protected function updateMessagesIndex()
    {
        $redis = Helper_Redis::getInstance();
        $indexName = Helper_Search::INDEX_MESSAGES;

        $indexId = (int)$redis->get(Helper_Redis::searchIndexId($indexName));

        $searchClient = Helper_Search::getClient();
        $todayIndexName = Helper_Search::getTodayIndexName($indexName);
        if (!$searchClient->indices()->exists(['index' => $todayIndexName])) {
            Helper_Search::mappingMessages($todayIndexName);
        }

        $indexId = Helper_Search::bulkMessages($indexId);
        $this->getLog()->add($indexId);
        $this->getLog()->flush();
        if ($indexId > 0) {
            $redis->setex(Helper_Redis::searchIndexId($indexName), 3600, $indexId);
        } else {
            Helper_Search::reindexByAlias($indexName);
            $redis->setex(Helper_Redis::searchIndexFinish($indexName), 3600, 1);
        }
    }

    protected function init()
    {
        parent::init();
        self::getLog()->add("\n\n**********************定时更新**************************");
        self::getLog()->flush();
    }

    /**
     * 发现新版本的事件
     */
    protected function onNewReleaseFind()
    {
        self::getLog()->add('Found new release: ' . $this->getReleaseCheck()->getRelease() . ', will quit for update.');
        die();
    }

    /**
     * 系统运行过程检测到内存不够的事件
     */
    protected function onOutOfMemory()
    {
        self::getLog()->add('System find that daemon will be out of memory, will quit for restart.');
        die();
    }

}
