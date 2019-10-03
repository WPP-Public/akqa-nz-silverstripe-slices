<?php

/**
 * Extension to add slice management to Page
 */

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD:  extends DataExtension (ignore case)
  * NEW:  extends DataExtension (COMPLEX)
  * EXP: Check for use of $this->anyVar and replace with $this->anyVar[$this->owner->ID] or consider turning the class into a trait
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class PageSlicesExtension extends DataExtension
{
    private static $dependencies = array(
        'previewer' => '%$DataObjectPreviewer'
    );

    private static $has_many = array(
        'Slices' => 'ContentSlice.Parent'
    );

    /**
     * @var DataObjectPreviewer
     */
    public $previewer;

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

        $gridConfig->addComponent(new GridFieldDataObjectPreview($this->previewer));
        //@TODO: add new sorter!!!
        // $gridConfig->addComponent(new GridFieldVersionedOrderableRows('Sort'));
        $gridConfig->removeComponentsByType('GridFieldDeleteAction');
        $gridConfig->removeComponentsByType('GridFieldDetailForm');
        $gridConfig->addComponent(new SliceDetailsForm());

        // Change columns displayed
        $dataColumns = $gridConfig->getComponentByType('GridFieldDataColumns');
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
