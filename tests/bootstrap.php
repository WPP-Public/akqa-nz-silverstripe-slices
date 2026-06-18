<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;

if (!class_exists('Page', false)) {
    class Page extends SiteTree
    {
        private static $table_name = 'Page';
    }
}

if (!class_exists('PageController', false)) {
    class PageController extends ContentController
    {
        private static $allowed_actions = [];
    }
}

require_once dirname(__DIR__) . '/vendor/silverstripe/framework/tests/bootstrap.php';
