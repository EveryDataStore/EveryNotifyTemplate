<?php

namespace EveryNotifyTemplate\Model;

use EveryDataStore\Helper\EveryDataStoreHelper;
use EveryDataStore\Model\DataStore;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;

class EveryNotifyTemplate extends DataObject implements PermissionProvider {

    private static $table_name = 'EveryNotifyTemplate';
    private static $singular_name = 'Every Notify Template';
    private static $plural_name = 'Every Notify Templates';
    private static $db = [
        'Slug' => 'Varchar(110)',
        'Active' => 'Boolean',
        'Title' => 'Varchar(100)',
        'Description' => 'Varchar(200)',
        'Type' => 'Enum("Email,PDF")',
        'Content' => 'HTMLText',
    ];
    private static $default_sort = "\"Title\"";
    private static $has_one = [
        'DataStore' => DataStore::class,
    ];
    
    private static $has_many = [];
    private static $belongs_to = [];
    private static $summary_fields = [
        'Active',
        'Title',
        'Type'
    ];
 
   
   public function fieldLabels($includerelations = true) {
        $labels = parent::fieldLabels(true);
        if (!empty(self::$summary_fields)) {
            $labels = EveryDataStoreHelper::getNiceFieldLabels($labels, __CLASS__, self::$summary_fields);
        }
        return $labels;
    }
    
    private static $searchable_fields = [
        'Title' => [
            'field' => TextField::class,
            'filter' => 'PartialMatchFilter',
        ],
        'Description' => [
            'field' => TextField::class,
            'filter' => 'PartialMatchFilter',
        ],
        'Type' => [
            'field' => TextField::class,
            'filter' => 'PartialMatchFilter',
        ]
    ];

    public function getCMSFields() {
        $fields = parent::getCMSFields();
        
        $fields->addFieldToTab('Root.Main', CheckboxField::create('Active', _t(__Class__ .'.ACTIVE', 'Active')));
        $fields->addFieldToTab('Root.Main', TextField::create('Title', _t(__Class__ .'.TITLE', 'Title')));
        $fields->addFieldToTab('Root.Main', TextField::create('Description', _t(__Class__ .'.DESCRIPTION', 'Description')));
        $fields->addFieldToTab('Root.Main', DropdownField::create('Type', _t(__Class__ .'.TYPE', 'Type'), singleton(__CLASS__)->dbObject('Type')->enumValues() )->setEmptyString(_t('Global.SELECTONE', 'Select one'))
       );
        
        $fields->addFieldToTab('Root.Main', DropdownField::create('RecordSet', _t(__Class__ .'.RECORDPLACEHOLDER', 'Record placeholder'), EveryDataStoreHelper::getCurrentDataStore()->Records()->filter(['Active' => true])->Map('Slug', 'Title')->toArray())->setEmptyString(_t('Global.SELECTONE', 'Select one'))
        );
         $fields->addFieldToTab('Root.Main', DropdownField::create('Field', _t(__Class__ .'.FIELDPLACEHOLDER', 'Field placeholder'), array())->setEmptyString(_t('Global.SELECTONE', 'Select one'))
        );
        $fields->addFieldToTab('Root.Main', HTMLEditorField::create('Content', _t(__Class__ .'.Content', 'Content')));

       

        $fields->removeFieldFromTab('Root.Main', 'DataStoreID');
        return $fields;
    }

    protected function onBeforeWrite() {
        parent::onBeforeWrite();
        $member = Security::getCurrentUser();
        
        if (!$this->owner->Slug && $member) {
            $this->owner->Slug = EveryDataStoreHelper::getAvailableSlug(__CLASS__);
        }
        
        if (!$this->owner->DataStoreID && $member) {
            $this->owner->DataStoreID = $member->CurrentDataStoreID;
        }
    }

    protected function onAfterWrite() {
        parent::onAfterWrite();
    }

    protected function onBeforeDelete() {
        parent::onBeforeDelete();
    }

    protected function onAfterDelete() {
        parent::onAfterDelete();
    }

    /**
     * This function should return true if the current user can view an object
     * @see Permission code VIEW_CLASSSHORTNAME e.g. VIEW_MEMBER
     * @param Member $member The member whose permissions need checking. Defaults to the currently logged in user.
     * @return bool True if the the member is allowed to do the given action
     */
    public function canView($member = null) {
        $member = Security::getCurrentUser();
        if ($member) {
            if ($this->Controller == 'record' && $this->Action != null && $this->ActionID != null) {
                $recordSetPermission = Permission::get()->filter(array('Code' => $this->ActionID))->first();
                if ($recordSetPermission) {
                    if (Permission::checkMember($member, $this->ActionID)) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
        }
        return EveryDataStoreHelper::checkPermission(EveryDataStoreHelper::getNicePermissionCode("VIEW", $this));
    }

    /**
     * This function should return true if the current user can edit an object
     * @see Permission code VIEW_CLASSSHORTNAME e.g. EDIT_MEMBER
     * @param Member $member The member whose permissions need checking. Defaults to the currently logged in user.
     * @return bool True if the the member is allowed to do the given action
     */
    public function canEdit($member = null) {
        return EveryDataStoreHelper::checkPermission(EveryDataStoreHelper::getNicePermissionCode("EDIT", $this));
    }

    /**
     * This function should return true if the current user can delete an object
     * @see Permission code VIEW_CLASSSHORTNAME e.g. DELTETE_MEMBER
     * @param Member $member The member whose permissions need checking. Defaults to the currently logged in user.
     * @return bool True if the the member is allowed to do the given action
     */
    public function canDelete($member = null) {
        return EveryDataStoreHelper::checkPermission(EveryDataStoreHelper::getNicePermissionCode("DELETE", $this));
    }

    /**
     * This function should return true if the current user can create new object of this class.
     * @see Permission code VIEW_CLASSSHORTNAME e.g. CREATE_MEMBER
     * @param Member $member The member whose permissions need checking. Defaults to the currently logged in user.
     * @param array $context Context argument for canCreate()
     * @return bool True if the the member is allowed to do this action
     */
    public function canCreate($member = null, $context = []) {
        return EveryDataStoreHelper::checkPermission(EveryDataStoreHelper::getNicePermissionCode("CREATE", $this));
    }

    /**
     * Return a map of permission codes for the Dataobject and they can be mapped with Members, Groups or Roles
     * @return array 
     */
    public function providePermissions() {
        return array(
            EveryDataStoreHelper::getNicePermissionCode("CREATE", $this) => [
                'name' => _t('SilverStripe\Security\Permission.CREATE', "CREATEs"),
                'category' => ClassInfo::shortname($this),
                'sort' => 1
            ],
            EveryDataStoreHelper::getNicePermissionCode("EDIT", $this) => [
                'name' => _t('SilverStripe\Security\Permission.EDIT', "EDIT"),
                'category' => ClassInfo::shortname($this),
                'sort' => 1
            ],
            EveryDataStoreHelper::getNicePermissionCode("VIEW", $this) => [
                'name' => _t('SilverStripe\Security\Permission.VIEW', "VIEW"),
                'category' => ClassInfo::shortname($this),
                'sort' => 1
            ],
            EveryDataStoreHelper::getNicePermissionCode("DELETE", $this) => [
                'name' => _t('SilverStripe\Security\Permission.DELETE', "DELETE"),
                'category' => ClassInfo::shortname($this),
                'sort' => 1
        ]);
    }

}
