<?php

/**
 * Apply the SiteTree edit/view/delete permissions/roles to any DataObject
 *
 * By default DataObjects are only writable by admin users, which isn't all that useful.
 */
class SiteTreePermissionsExtension extends DataExtension
{
    public function canView($member = null)
    {
        if(!$member instanceof Member) {
            $member = Member::currentUser();
        }

        if (Permission::checkMember($member, 'SITETREE_VIEW_ALL')) {
            return true;
        }

        return $this->owner->canView($member);
    }

    public function canCreate($member = null)
    {
        return $this->owner->canEdit($member);
    }

    public function canEdit($member = null)
    {
        if(!$member instanceof Member) {
            $member = Member::currentUser();
        }

        if (Permission::checkMember($member, 'SITETREE_EDIT_ALL')) {
            return true;
        }

        return $this->owner->canEdit($member);
    }

    public function canDelete($member = null)
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
