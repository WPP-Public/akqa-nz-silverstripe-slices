2019-10-04 10:25

# running php upgrade upgrade see: https://github.com/silverstripe/silverstripe-upgrader
cd /var/www/upgrades/upgradeto4
php /var/www/upgrader/vendor/silverstripe/upgrader/bin/upgrade-code upgrade /var/www/upgrades/upgradeto4/slices  --root-dir=/var/www/upgrades/upgradeto4 --write -vvv --prompt
Writing changes for 6 files
Running upgrades on "/var/www/upgrades/upgradeto4/slices"
[2019-10-04 10:25:07] Applying UpdateConfigClasses to slices.yml...
[2019-10-04 10:25:07] Applying RenameClasses to SiteTreePermissionsExtension.php...
[2019-10-04 10:25:07] Applying ClassToTraitRule to SiteTreePermissionsExtension.php...
[2019-10-04 10:25:07] Applying RenameClasses to PageSlicesExtension.php...
[2019-10-04 10:25:07] Applying ClassToTraitRule to PageSlicesExtension.php...
[2019-10-04 10:25:07] Applying RenameClasses to SliceDetailsForm_ItemRequest.php...
[2019-10-04 10:25:07] Applying ClassToTraitRule to SliceDetailsForm_ItemRequest.php...
[2019-10-04 10:25:07] Applying RenameClasses to SliceDetailsForm.php...
[2019-10-04 10:25:07] Applying ClassToTraitRule to SliceDetailsForm.php...
[2019-10-04 10:25:07] Applying RenameClasses to Slice.php...
[2019-10-04 10:25:07] Applying ClassToTraitRule to Slice.php...
[2019-10-04 10:25:07] Applying RenameClasses to _config.php...
[2019-10-04 10:25:07] Applying ClassToTraitRule to _config.php...
modified:	_config/slices.yml
@@ -1,15 +1,9 @@
 ---
 Name: 'slices'
 ---
+SilverStripe\Core\Injector\Injector: {  }
+Heyday\Slices\DataObjects\Slice:
+  dependencies:
+    previewer: '%$DataObjectPreviewer'
+  previewStylesheets: {  }

-Injector:
-  DataObjectPreviewer:
-    class: DataObjectPreviewer
-
-Slice:
-
-  dependencies:
-    previewer: "%$DataObjectPreviewer"
-
-  previewStylesheets: []
-

modified:	src/Extensions/SiteTreePermissionsExtension.php
@@ -2,9 +2,13 @@

 namespace Heyday\Slices\Extensions;

-use DataExtension;
-use Member;
-use Permission;
+
+
+
+use SilverStripe\Security\Member;
+use SilverStripe\Security\Permission;
+use SilverStripe\ORM\DataExtension;
+


 /**

modified:	src/Extensions/PageSlicesExtension.php
@@ -2,12 +2,21 @@

 namespace Heyday\Slices\Extensions;

-use DataExtension;
-use FieldList;
-use GridField;
-use GridFieldConfig_RecordEditor;
+
+
+
+
 use GridFieldDataObjectPreview;
-use SliceDetailsForm;
+
+use SilverStripe\Forms\FieldList;
+use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
+use SilverStripe\Forms\GridField\GridField;
+use SilverStripe\Forms\GridField\GridFieldDeleteAction;
+use SilverStripe\Forms\GridField\GridFieldDetailForm;
+use Heyday\Slices\Form\SliceDetailsForm;
+use SilverStripe\Forms\GridField\GridFieldDataColumns;
+use SilverStripe\ORM\DataExtension;
+


 /**
@@ -68,12 +77,12 @@
         $gridConfig->addComponent(new GridFieldDataObjectPreview($this->previewer));
         //@TODO: add new sorter!!!
         // $gridConfig->addComponent(new GridFieldVersionedOrderableRows('Sort'));
-        $gridConfig->removeComponentsByType('GridFieldDeleteAction');
-        $gridConfig->removeComponentsByType('GridFieldDetailForm');
+        $gridConfig->removeComponentsByType(GridFieldDeleteAction::class);
+        $gridConfig->removeComponentsByType(GridFieldDetailForm::class);
         $gridConfig->addComponent(new SliceDetailsForm());

         // Change columns displayed
-        $dataColumns = $gridConfig->getComponentByType('GridFieldDataColumns');
+        $dataColumns = $gridConfig->getComponentByType(GridFieldDataColumns::class);
         $dataColumns->setDisplayFields($this->modifyDisplayFields(
             $dataColumns->getDisplayFields($grid)
         ));

modified:	src/Form/SliceDetailsForm_ItemRequest.php
@@ -2,9 +2,12 @@

 namespace Heyday\Slices\Form;

-use GridFieldDetailForm_ItemRequest;
-use Slice;
+
+
 use RuntimeException;
+use Heyday\Slices\DataObjects\Slice;
+use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
+




Warnings for src/Form/SliceDetailsForm_ItemRequest.php:
 - src/Form/SliceDetailsForm_ItemRequest.php:63 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 63

modified:	src/Form/SliceDetailsForm.php
@@ -2,7 +2,9 @@

 namespace Heyday\Slices\Form;

-use GridFieldDetailForm;
+
+use SilverStripe\Forms\GridField\GridFieldDetailForm;
+


 class SliceDetailsForm extends GridFieldDetailForm

modified:	src/DataObjects/Slice.php
@@ -2,22 +2,36 @@

 namespace Heyday\Slices\DataObjects;

-use DataObject;
-use ClassInfo;
+
+
 use RuntimeException;
-use FieldList;
-use FormField;
+
+
 use DataObjectPreviewField;
 use Exception;
-use LiteralField;
-use DropdownField;
-use ListboxField;
-use FileField;
-use Requirements;
-use Config;
-use SSViewer;
+
+
+
+
+
+
+
 use SS_TemplateLoader;
-use Director;
+
+use SilverStripe\Core\ClassInfo;
+use SilverStripe\Forms\FieldList;
+use SilverStripe\Forms\FormField;
+use Heyday\Slices\DataObjects\Slice;
+use SilverStripe\Forms\LiteralField;
+use SilverStripe\Forms\DropdownField;
+use SilverStripe\Forms\ListboxField;
+use SilverStripe\Forms\FileField;
+use SilverStripe\View\Requirements;
+use SilverStripe\Core\Config\Config;
+use SilverStripe\View\SSViewer;
+use SilverStripe\Control\Director;
+use SilverStripe\ORM\DataObject;
+


 class Slice extends DataObject
@@ -236,14 +250,14 @@
             $fields->addFieldToTab(
                 'Root.Main',
                 new DataObjectPreviewField(
-                    'Slice',
+                    Slice::class,
                     $this,
                     $this->previewer
                 ),
                 $firstField
             );
         } catch (Exception $e) {
-            $fields->addFieldToTab('Root.Main', new LiteralField('Slice',
+            $fields->addFieldToTab('Root.Main', new LiteralField(Slice::class,
                 '<div class="message error"><strong>Unable to render slice preview:</strong> '.htmlentities($e->getMessage()).'</div>'
             ), $firstField);
         }
@@ -360,8 +374,8 @@
         }

         // The theme can be disabled when in the context of the CMS, which causes includes to fail
-        $themeEnabled = Config::inst()->get('SSViewer', 'theme_enabled');
-        Config::modify()->update('SSViewer', 'theme_enabled', true);
+        $themeEnabled = Config::inst()->get(SSViewer::class, 'theme_enabled');
+        Config::modify()->update(SSViewer::class, 'theme_enabled', true);

         $result = $this->customise(array(
             'Slice' => $this->forTemplate()
@@ -379,7 +393,7 @@
         Requirements::restore();

         // Restore previous theme_enabled setting
-        Config::modify()->update('SSViewer', 'theme_enabled', $themeEnabled);
+        Config::modify()->update(SSViewer::class, 'theme_enabled', $themeEnabled);

         return $result;
     }
@@ -462,7 +476,7 @@
     protected function getTemplateList()
     {
         $templates = SS_TemplateLoader::instance()->findTemplates(
-            $tryTemplates = $this->getTemplateSearchNames(), Config::inst()->get('SSViewer', 'theme')
+            $tryTemplates = $this->getTemplateSearchNames(), Config::inst()->get(SSViewer::class, 'theme')
         );

         if (!$templates) {

Warnings for src/DataObjects/Slice.php:
 - src/DataObjects/Slice.php:189 PhpParser\Node\Expr\Variable
 - WARNING: New class instantiated by a dynamic value on line 189

Writing changes for 6 files
✔✔✔