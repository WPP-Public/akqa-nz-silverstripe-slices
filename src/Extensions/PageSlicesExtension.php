<?php

namespace Heyday\SilverStripeSlices\Extensions;

use Heyday\SilverStripeSlices\DataObjects\Slice;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;

/**
 * Extension to add slice management to Page
 */
class PageSlicesExtension extends DataExtension
{
    /**
     * Class used when creating a new row in the Slices GridField. Must extend {@link Slice} and
     * carry the same schema/extensions as your real slice records.
     * If left as {@link Slice}, new rows have no subclass/extension fields until after first save.
     *
     * @var class-string<Slice>
     */
    private static $grid_model_class = Slice::class;

    private static $has_many = [
        'Slices' => Slice::class,
    ];

    private static $owns = [
        'Slices',
    ];

    private static $cascade_deletes = [
        'Slices',
    ];

    private static $cascade_duplicates = [
        'Slices',
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $this->addSlicesCmsTab($fields);
    }

    public function addSlicesCmsTab(FieldList $fields, string $tabName = 'Root.Slices', $dataList = null): void
    {
        if (!$dataList) {
            $dataList = $this->owner->Slices();
        }

        // Match the CMS reading mode (draft vs live). Forcing "Stage" alone can yield zero rows if
        // the session/archive mode does not line up with that assumption.
        $stage = Versioned::get_stage();
        if ($stage) {
            $dataList = $dataList->setDataQueryParam(['Versioned.stage' => $stage]);
        }

        $fields->addFieldToTab(
            $tabName,
            $grid = GridField::create(
                'Slices',
                'Slices',
                $dataList,
                $gridConfig = GridFieldConfig_RecordEditor::create()
            )
        );

        $gridModel = Config::inst()->get(__CLASS__, 'grid_model_class');
        if (is_string($gridModel) && class_exists($gridModel) && is_a($gridModel, Slice::class, true)) {
            $grid->setModelClass($gridModel);
        }

        $gridConfig->removeComponentsByType(GridFieldDeleteAction::class);
        // Stale filter state (from old column keys like ID-only summary) can filter the list to zero.
        $gridConfig->removeComponentsByType(GridFieldFilterHeader::class);

        /** @var GridFieldDataColumns|null $dataColumns */
        $dataColumns = $gridConfig->getComponentByType(GridFieldDataColumns::class);

        if ($dataColumns) {
            $dataColumns->setDisplayFields($this->modifyDisplayFields(
                $dataColumns->getDisplayFields($grid)
            ));
        }

        // Allow sort by ID / Sort even when those columns are not shown (avoids LogicException on
        // stale gridState URLs, e.g. SortColumn=ID from before display columns changed).
        $sortHeader = $gridConfig->getComponentByType(GridFieldSortableHeader::class);
        if ($sortHeader instanceof GridFieldSortableHeader) {
            $sortHeader->setFieldSorting(array_values(array_unique(array_merge(
                array_values((array) $sortHeader->getFieldSorting()),
                ['ID', 'Sort']
            ))));
        }
    }

    /**
     * Keep Title; add a readable template column. (Vendor version incorrectly removed Title.)
     *
     * @param array<string, string|array> $fields
     * @return array<string, string|array>
     */
    protected function modifyDisplayFields(array $fields): array
    {
        return [
            'Title' => 'Title',
            'Template' => 'Slice type',
        ];
    }
}
