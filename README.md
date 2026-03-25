# Content Slices for SilverStripe

Content management in "slices" configured via YAML: separate content components, each with their own template, fields, and visual settings that can be created and arranged in the CMS.

- [Requirements](#requirements)
- [Usage](#usage)
    - [Templates](#templates)
    - [Adding Slices to Page](#adding-slices-to-page)
    - [Subclassing Slice](#subclassing-slice)
    - [Using Slices on a sub class of a Page/SiteTree](#using-slices-on-a-sub-class-of-a-pagesitetree)
    - [Customising CMS fields](#customising-cms-fields)
    - [SilverStripe 5: CMS grid, titles, and template labels](#silverstripe-5-cms-grid-titles-and-template-labels)
    - [Upgrading: legacy data on subclass tables](#upgrading-legacy-data-on-subclass-tables)
- [Example config](#example-config)

## Requirements

- **SilverStripe 5** (`silverstripe/cms` ^5), **PHP 8.1+**

For older SilverStripe versions, use an earlier major release of this module.

## Usage

Define **shared fields** on your base slice class (e.g. `ContentSlice`) in `$db`, then reuse them across templates in YAML. That way each template only configures labels, help text, and which fields appear—without a separate PHP class per slice type (unless you use a `className` for special cases).

### Templates

Each slice type/template has its own template file named `[BaseSliceClass]_[TemplateName]`. Put these under `themes/[theme]/templates/[MyProject]/DataObjects/Slices` so they stay separate from the rest of the site.

### Adding Slices to Page

The module ships with an extension that wires a **Slices** tab and GridField onto `Page`:

```yaml
Page:
  extensions:
    - Heyday\SilverStripeSlices\Extensions\PageSlicesExtension
```

In templates, render slices like this:

```html
<% if $Slices %>
    <% loop $Slices %>
        $forTemplate
    <% end_loop %>
<% end_if %>
```

**Prefer extending this extension** in your project (see [Subclassing Slice](#subclassing-slice)) instead of copying its logic into a new extension. Duplicating the GridField setup can cause duplicate relations, conflicting column config, or removed **Title** columns if old code still calls `unset($fields['Title'])` on display fields.

### Subclassing Slice

Subclassing `Slice` is normal. Override **`getBaseSliceClass()`** on your **project base** slice class (the one you use in the page `has_many`) so the module knows which class to fall back to when a template has no `className`, and so **YAML `templates` config** is resolved from that base class (not from concrete subclasses such as `VideoSlice`).

```php
<?php

namespace MyProject\DataObjects\Slices;

use Heyday\SilverStripeSlices\DataObjects\Slice;

class ContentSlice extends Slice
{
    protected function getBaseSliceClass()
    {
        return __CLASS__;
    }
}

class VideoSlice extends ContentSlice
{
    // Subclasses of your base subclass do not need anything special
}
```

Extend **`PageSlicesExtension`** and point `Slices` at your base slice class:

```php
<?php

namespace MyProject\Extensions;

use MyProject\DataObjects\Slices\ContentSlice;
use Heyday\SilverStripeSlices\Extensions\PageSlicesExtension as BasePageSlicesExtension;

class PageSlicesExtension extends BasePageSlicesExtension
{
    private static $has_many = [
        'Slices' => ContentSlice::class,
    ];
}
```

```yaml
Page:
  extensions:
    - MyProject\Extensions\PageSlicesExtension
```

### Using Slices on a sub class of a Page/SiteTree

To attach slices to a custom page type, override the slice’s **`Parent`** `has_one` to that type.

```php
<?php

namespace MyProject\Pages;

use SilverStripe\CMS\Model\SiteTree;

class GenericPage extends SiteTree
{
    // ...
}
```

```php
<?php

namespace MyProject\Extensions;

use MyProject\Pages\GenericPage;
use SilverStripe\ORM\DataExtension;

class SliceParentExtension extends DataExtension
{
    private static $has_one = [
        'Parent' => GenericPage::class,
    ];
}
```

```yaml
Heyday\SilverStripeSlices\DataObjects\Slice:
  extensions:
    - MyProject\Extensions\SliceParentExtension
```

### Customising CMS fields

Adding or changing fields in `YourBaseSlice::getCMSFields()` is expected. YAML-driven configuration may be overridden if you replace a field; you can re-apply slice config at the end of `getCMSFields()`:

```php
$config = $this->getCurrentTemplateConfig();
$this->configureFieldsFromConfig($fields, $config);
```

To change **GridField** columns, override **`modifyDisplayFields()`** on your **`PageSlicesExtension`** subclass (see `Heyday\SilverStripeSlices\Extensions\PageSlicesExtension`).

### SilverStripe 5: CMS grid, titles, and template labels

- **Grid columns** — The Slices grid is configured to show **Title** and **Types** (human-readable template name from YAML), not only **ID**. SilverStripe’s default `summaryFields()` scaffolding otherwise falls back to **ID** when nothing suitable is defined.
- **Base `Title` field** — `Slice` includes a **`Title`** database field for list labels. **`Title`** is always kept on the edit form even if it is omitted from a template’s `fields:` block (other fields are still driven by YAML).
- **Template / type labels** — Template options and labels are read using **`getBaseSliceClass()`**, so YAML on e.g. `ContentSlice` applies to all polymorphic subclasses. You can still amend the raw `templates` array from an extension with **`updateTemplateNames(&$templates)`** on `Slice` (called by reference).

### Upgrading: legacy data on subclass tables

If common columns (e.g. **Title**) used to exist only on a **subclass** table (e.g. `ContentSlice`) but the ORM now reads them from the **base** `Slice` row, values can appear missing in the CMS until they are copied onto `Slice` / `Slice_Live`.

1. Map each column to the subclass that still holds legacy values:

```yaml
Heyday\SilverStripeSlices\DataObjects\Slice:
  legacy_subclass_fallback_fields:
    Title: MyProject\DataObjects\ContentSlice
    # Add other columns that exist on BOTH tables with the same ID
```

2. Opening or saving a slice runs a **per-row** copy when the base value is empty. For a **one-off backfill** of every row (e.g. to fix the grid without opening each record), run:

```bash
vendor/bin/sake dev/tasks/sync-slice-legacy-subclass-data
```

If this config is unset, no extra queries run.

## Example config

Apply slice-specific settings to your **base** slice class in YAML (here `Heyday\SilverStripeSlices\DataObjects\Slice`; in a real project often your `ContentSlice` FQCN after you subclass).

```yaml
Heyday\SilverStripeSlices\DataObjects\Slice:

  previewStylesheets:
    - /themes/base/css/styles.css

  uploadFolderFields:
    - LeadImage
    - SecondaryImage
    - LeadFile

  defaultTemplate: TwoColumnImage

  templates:
    Quote:
      fields:
        Title: Quotee
        Content:
          label: Quote
          fieldClass: TextareaField
          casting: Text
          help: ~20 - 30 words
          exampleValue: Dolor exercitation sint ad minim et deserunt nisi aliquip cillum laboris ipsum esse nulla commodo cupidatat ipsum proident exercitation veniam

      visualOptions:
        no-icon: No icon
        bold-quote: Bold quoted text

    TwoColumnImage:
      name: Two column with image
      className: MyProject\DataObjects\Slices\TwoColumnImageSlice
      fields:
        Title: ~
        Content: Text column
        LeadImage:
          name: Image
          exampleValue: themes/base/images/examples/random-guy.jpg

      visualOptions:
        image-right: Image in right column
        title-centered: Center title above columns
```
