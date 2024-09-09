<?php

namespace EveryNotifyTemplateExtension\Extension;

use SilverStripe\ORM\DataExtension;
use EveryNotifyTemplate\Model\EveryNotifyTemplate;

class EveryNotifyTemplateDataStoreExtension extends DataExtension {

    private static $db = [];
    private static $has_one = [];
    private static $has_many = [
        'EveryNotifyTemplates' => EveryNotifyTemplate::class
    ];
    private static $default_sort = "";
    private static $defaults = [];

    public function onBeforeWrite() {
        parent::onBeforeWrite();
    }

    public function onAfterWrite() {
        parent::onAfterWrite();
    }

    public function onBeforeDelete() {
        parent::onBeforeDelete();
    }

    public function onAfterDelete() {
        parent::onAfterDelete();
    }

}
