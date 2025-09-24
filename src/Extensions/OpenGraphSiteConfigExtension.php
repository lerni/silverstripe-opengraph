<?php

namespace TractorCow\OpenGraph\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Core\Extension;
use TractorCow\OpenGraph\Interfaces\IOGApplication;
use TractorCow\OpenGraph\OpenGraph;

class OpenGraphSiteConfigExtension extends Extension implements IOGApplication
{
    private static $db = [
        'OGApplicationID' => 'Varchar(255)',
        'OGAdminID' => 'Varchar(255)',
    ];

    public function updateCMSFields(FieldList $fields)
    {

        if (OpenGraph::get_config('application_id') == 'SiteConfig') {
            $fields->addFieldToTab(
                'Root.Facebook',
                TextField::create('OGApplicationID', 'Facebook Application ID', null, 255)
            );
        }

        if (OpenGraph::get_config('admin_id') == 'SiteConfig') {
            $fields->addFieldToTab(
                'Root.Facebook',
                TextField::create('OGAdminID', 'Facebook Admin ID(s)', null, 255)
            );
        }
    }

    protected function getConfigurableField($dbField, $configField)
    {
        $value = OpenGraph::get_config($configField);
        if ($value == 'SiteConfig') {
            return $this->owner->getField($dbField);
        }
        return $value;
    }

    public function getOGAdminID()
    {
        return $this->getConfigurableField('OGAdminID', 'admin_id');
    }

    public function getOGApplicationID()
    {
        return $this->getConfigurableField('OGApplicationID', 'application_id');
    }
}
