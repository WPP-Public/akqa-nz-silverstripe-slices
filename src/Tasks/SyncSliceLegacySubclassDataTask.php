<?php

namespace Heyday\SilverStripeSlices\Tasks;

use Heyday\SilverStripeSlices\DataObjects\Slice;
use SilverStripe\Dev\BuildTask;

/**
 * One-off backfill: copies empty base-table fields from a configured subclass table for every slice row.
 * Use when legacy data lived on e.g. ContentSlice while the ORM reads from Slice. Same logic as
 * {@link Slice::run_legacy_subclass_sync()}, which also runs when a slice is opened or saved in the CMS.
 */
class SyncSliceLegacySubclassDataTask extends BuildTask
{
    /**
     * @config
     */
    private static $segment = 'sync-slice-legacy-subclass-data';

    protected $title = 'Sync slice legacy subclass data into base table';

    protected $description = 'Copies configured fields from subclass rows (see Slice.legacy_subclass_fallback_fields) '
        . 'into the base Slice table when the base value is empty. Updates draft and live tables. Safe to re-run.';

    public function run($request)
    {
        $rows = Slice::run_legacy_subclass_sync(null);
        echo "Approximate rows affected (MySQL): {$rows}\n";
        echo "Done. Re-open the Slices grid or flush to confirm.\n";
    }
}
