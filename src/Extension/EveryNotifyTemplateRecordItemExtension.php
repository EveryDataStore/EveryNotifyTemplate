<?php

namespace EveryNotifyTemplateExtension\Extension;

use SilverStripe\ORM\DataExtension;
use EveryDataStore\Model\EveryConfiguration;
use EveryNotifyTemplate\Model\EveryNotifyTemplate;
use EveryNotifyTemplate\Helper\EveryNotifyTemplateHelper;

class EveryNotifyTemplateRecordSetItemExtension extends DataExtension {

    private static $db = [];
    private static $has_one = [];
    private static $has_many = [];
    private static $default_sort = "";
    private static $defaults = [];
  
  
    
    public function PDFTemplateNames() {
        $ret = [];
        $ret[] = array(
                        'Slug' => 'default',
                        'Title' => 'Default'
                    );
        
        $templates = EveryNotifyTemplate::get()->filter(['Type' => 'PDF', 'Active' => true, 'DataStoreID' => EveryNotifyTemplateHelper::getCurrentDataStoreID() ]);
            if ($templates) {
                foreach ($templates as $template) {
                    $ret[] = array(
                        'Slug' => $template->Slug,
                        'Title' => $template->Title
                    );
                }
            }
        return $ret;
    }
}
