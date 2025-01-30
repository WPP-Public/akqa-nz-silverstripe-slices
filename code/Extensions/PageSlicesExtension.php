<?php

namespace Heyday\SilverStripeSlices\Extensions;

use Heyday\SilverStripeSlices\DataObjects\Slice;
use Heyday\SilverStripeSlices\Forms\SliceDetailsForm;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\ORM\DataExtension;

/**
 * Extension to add slice management to Page
 */
class PageSlicesExtension extends DataExtension
{
    private static $has_many = [
        'Slices' => Slice::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $this->addSlicesCmsTab($fields);
    }

    /**
     * Add slice management to CMS fields
     *
     * @param FieldList $fields
     */
    public function addSlicesCmsTab(FieldList $fields, $tabName = 'Root.Slices', $dataList = null)
    {
        if (!$dataList) {
            $dataList = $this->owner->Slices();
        }

        $dataList = $dataList->setDataQueryParam(['Versioned.stage' => 'Stage']);

        $fields->addFieldToTab(
            $tabName,
            $grid = new GridField(
                'Slices',
                'Slices',
                $dataList,
                $gridConfig = GridFieldConfig_RecordEditor::create()
            )
        );

        $gridConfig->removeComponentsByType(GridFieldDeleteAction::class);
        $gridConfig->removeComponentsByType(GridFieldDetailForm::class);
        $gridConfig->addComponent(new SliceDetailsForm());

        // Change columns displayed
        $dataColumns = $gridConfig->getComponentByType(GridFieldDataColumns::class);
        $dataColumns->setDisplayFields($this->modifyDisplayFields(
            $dataColumns->getDisplayFields($grid)
        ));
    }

    protected function modifyDisplayFields(array $fields)
    {
        unset($fields['Title']);

        return $fields;
    }
}
