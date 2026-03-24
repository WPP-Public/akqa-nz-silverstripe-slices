<?php

namespace Heyday\SilverStripeSlices\Extensions;

use Heyday\SilverStripeSlices\DataObjects\Slice;
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

    private static $owns = [
        'Slices'
    ];

    private static $cascade_deletes = [
        'Slices'
    ];

    private static $cascade_duplicates = [
        'Slices'
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

        // Change columns displayed
        /** @var GridFieldDataColumns $dataColumns */
        $dataColumns = $gridConfig->getComponentByType(GridFieldDataColumns::class);

        if ($dataColumns) {
            // SS5 summaryFields() falls back to ID-only when nothing else is configured; Slice has no Title in
            // core schema, so we must set columns explicitly. Include Title when the relation model defines it.
            $dataColumns->setDisplayFields($this->modifyDisplayFields(
                $this->buildSliceGridDisplayFields($grid)
            ));

            $dataColumns->setFieldFormatting(array_merge(
                $dataColumns->getFieldFormatting() ?: [],
                [
                    'Template' => static function ($value, $record) {
                        if (!$record instanceof Slice) {
                            return $value;
                        }
                        $map = $record->getTemplateNames();

                        return $map[$value] ?? $value;
                    },
                ]
            ));
        }
    }

    /**
     * Default columns for the Slices grid: Title (if present on the slice model) and template type.
     *
     * @return array<string, string>
     */
    protected function buildSliceGridDisplayFields(GridField $grid): array
    {
        $modelClass = $grid->getModelClass();
        $singleton = singleton($modelClass);

        $fields = [
            'Template' => 'Types',
        ];

        if ($singleton->hasField('Title')) {
            $fields = array_merge(
                ['Title' => 'Title'],
                $fields
            );
        }

        return $fields;
    }

    /**
     * Override in project extensions to add, remove, or relabel grid columns.
     *
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    protected function modifyDisplayFields(array $fields)
    {
        return $fields;
    }
}
