<?php

namespace Heyday\Slices\Extensions;




use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\DataExtension;



/**
 * Apply the SiteTree edit/view/delete permissions/roles to any DataObject
 *
 * By default DataObjects are only writable by admin users, which isn't all that useful.
 */

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD:  extends DataExtension (ignore case)
  * NEW:  extends DataExtension (COMPLEX)
  * EXP: Check for use of $this->anyVar and replace with $this->anyVar[$this->owner->ID] or consider turning the class into a trait
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class SiteTreePermissionsExtension extends DataExtension
{
    public function canView($member = null, $context = [])
    {
        if(!$member instanceof Member) {
            $member = Member::currentUser();
        }

        if (Permission::checkMember($member, 'SITETREE_VIEW_ALL')) {
            return true;
        }

        return $this->owner->canView($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->owner->canEdit($member);
    }

    public function canEdit($member = null, $context = [])
    {
        if(!$member instanceof Member) {
            $member = Member::currentUser();
        }

        if (Permission::checkMember($member, 'SITETREE_EDIT_ALL')) {
            return true;
        }

        return $this->owner->canEdit($member);
    }

    public function canDelete($member = null, $context = [])
    {
        if(!$member instanceof Member) {
            $member = Member::currentUser();
        }

        if (Permission::checkMember($member, 'SITETREE_EDIT_ALL')) {
            return true;
        }

        return $this->owner->canEdit($member);
    }
}
