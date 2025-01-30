<?php

namespace Heyday\SilverStripeSlices\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Apply the SiteTree edit/view/delete permissions/roles to any DataObject
 *
 * By default DataObjects are only writable by admin users, which isn't all that useful.
 */
class SiteTreePermissionsExtension extends DataExtension
{
    /**
     * @param null $member
     * @return bool
     */
    public function canView($member = null)
    {
        if (!$member instanceof Member) {
            $member = Security::getCurrentUser();
        }

        if (Permission::checkMember($member, 'SITETREE_VIEW_ALL')) {
            return true;
        }

        return $this->owner->canView($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canCreate($member = null)
    {
        return $this->owner->canEdit($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canEdit($member = null)
    {
        if (!$member instanceof Member) {
            $member = Security::getCurrentUser();
        }

        if (Permission::checkMember($member, 'SITETREE_EDIT_ALL')) {
            return true;
        }

        return $this->owner->canEdit($member);
    }

    /**
     * @param null $member
     * @return bool
     */
    public function canDelete($member = null)
    {
        if (!$member instanceof Member) {
            $member = Security::getCurrentUser();
        }

        if (Permission::checkMember($member, 'SITETREE_EDIT_ALL')) {
            return true;
        }

        return $this->owner->canEdit($member);
    }
}
