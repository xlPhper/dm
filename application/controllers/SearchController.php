<?php

class SearchController extends DM_Controller
{
    public function init()
    {
        parent::init();
    }

    public function buildIndexAction()
    {
        TaskRun_SearchIndex::instance()->daemonRun();
    }
}