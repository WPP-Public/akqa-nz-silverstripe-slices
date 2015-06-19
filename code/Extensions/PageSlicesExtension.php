<?php

/**
 * Extension to add slice management to Page
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
        $fields->addFieldToTab(
            $tabName,
            new GridField(
                'Slices',
                'Slices',
                $dataList ?: $this->owner->Slices(),
                $gridConfig = GridFieldConfig_RecordEditor::create()
            )
        );

        $gridConfig->addComponent(new GridFieldDataObjectPreview($this->previewer));
        $gridConfig->addComponent(new GridFieldVersionedOrderableRows('Sort'));
        $gridConfig->removeComponentsByType('GridFieldDeleteAction');
        $gridConfig->removeComponentsByType('GridFieldDetailForm');
        $gridConfig->addComponent(new VersionedDataObjectDetailsForm());
    }
}