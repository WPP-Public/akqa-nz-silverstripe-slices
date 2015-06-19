# Content Slices for SilverStripe

Content management in "slices"; separate content components, each with their own template, fields and visual settings that can be created and arranged by a user in the CMS.

* Slice previews in the CMS
* Slice CMS configured via YAML

## Usage

It's best to use silverstripe-slices with lots of generic fields on the base slice class, since this allows templates to share fields and be configured in YAML without creating a class for each slice. [silverstripe-adaptivecontent](https://github.com/heyday/silverstripe-adaptivecontent) works well for this (and used to be integrated with this module).

```yaml
# Use the generic fields in silverstripe-adaptivecontent
Slice:
  extensions:
    - 'AdaptiveContent'
    - "AdaptiveContentRelated('Page')"
```

## Subclassing Slice

Subclassing `Slice` is a normal use case, however note that when subclassing it, you'll need to override the method `getBaseSliceClass` method in your "base" slice subclass (the one you point to in a has_many from Page) for the slice to save correctly:

```php
class ContentSlice extends Slice
{
    protected function getBaseSliceClass()
    {
        return __CLASS__;
    }
}

class VideoSlice extends ContentSlice
{
    // Subclasses of your 'base' subclass don't need anything special
}
```

This is due to the the module needing a "default" class to fall back to when the `className` key has not been set in a template config.

### Example config

```yaml
Slice:
  templates:
    Quote:
      fields:
        Title: Quotee
        Content:
          # Set the CMS "title" of the field
          label: Quote

          # Change the form component used for this field
          fieldClass: TextareaField

          # Cast the field to something when rendering it in the template
          casting: Text

          # Value to use for the field's "right title"
          help: ~20 - 30 words

          # Value to use when previewing what the slice will look like
          exampleValue: Dolor exercitation sint ad minim et deserunt nisi aliquip cillum laboris ipsum esse nulla commodo cupidatat ipsum proident exercitation veniam

      # Options exposed in the CMS for configuring the slice template
      # The key is accessible in templates, and the value is used as the CMS title
      visualOptions:
        no-icon: No icon
        bold-quote: Bold quoted text


    TwoColumnImage:
      # Name to show for the template in the CMS
      name: Two column with image

      # Class to change to when using this template
      # This allows complex slices to have extra fields and code
      className: TwoColumnImageSlice

      # Fields that only need a label configured can be defined using a shortcut:
      # (The order fields are defined here also controls the order they show in the CMS)
      fields:
        Title: ~
        Content: Text column
        LeadImage:
          name: Image
          # Images can be specified as file names in example content
          exampleValue: themes/base/images/examples/random-guy.jpg

      visualOptions:
        image-right: Image in right column
        title-centered: Center title above columns
```