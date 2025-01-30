<?php
namespace Heyday\SilverStripeSlices\Forms;

<<<<<<< HEAD:code/Form/SliceDetailsForm.php
use Heyday\VersionedDataObjects\VersionedDataObjectDetailsForm;
use Heyday\VersionedDataObjects\VersionedDataObjectDetailsForm_ItemRequest;

class SliceDetailsForm extends VersionedDataObjectDetailsForm
=======
use Heyday\SilverStripeSlices\DataObjects\Slice;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;

class SliceDetailsForm extends GridFieldDetailForm
>>>>>>> 7db8c58 (working my way through namespaces etc):code/Forms/SliceDetailsForm.php
{
}

/**
 * Slice GridField Details Form
 *
 * This handles the template->className mapping that is possible with slices. SilverStripe's
 * default details form only handles class names changing on submit when the field ClassName
 * exists in the form submission and differs from the target record's class name in the database.
 *
 * Record instances need to be consistent with their ClassName field at save, and particularly
 * when re-rendering the CMS fields, so that the instance code and the config returned Object::config()
 * are compatible. Object::config() uses get_called_class(), which makes it return the config for
 * the old class if the instance is not recreated, causing non-sense looking errors.
 */
class SliceDetailsForm_ItemRequest extends GridFieldDetailForm_ItemRequest
{
    public function __construct($gridField, $component, $record, $requestHandler, $popupFormName)
    {
        parent::__construct($gridField, $component, $record, $requestHandler, $popupFormName);

        if (!$this->record instanceof Slice) {
            throw new \RuntimeException('SliceDetailsForm expects to work with instances of Slice. Was given a ' . get_class($this->record));
        }
    }

    public function publish($data, $form)
    {
        if (isset($data['Template'])) {
            $this->updateRecordClass($data['Template']);
        }

        return parent::publish($data, $form);
    }

    public function save($data, $form)
    {
        if (isset($data['Template'])) {
            $this->updateRecordClass($data['Template']);
        }

        return parent::save($data, $form);
    }

    /**
     * Create a new instance of the form record using the class configured for a template name
     *
     * @param string $templateName
     */
    protected function updateRecordClass($templateName)
    {
        $this->record->setClassNameByTemplate($templateName);

        if ($this->hasRecordChangedClass()) {
            $newClass = $this->record->ClassName;
            $this->record = $newClass::create((array) $this->record);
        }
    }

    /**
     * Check if the target record's ClassName field is in sync with its record instance
     *
     * @return bool
     */
    protected function hasRecordChangedClass()
    {
        return get_class($this->record) !== $this->record->ClassName;
    }
}
