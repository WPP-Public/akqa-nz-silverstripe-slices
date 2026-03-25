<?php

namespace Heyday\SilverStripeSlices\DataObjects;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ThemeResourceLoader;

/**
 * Class Slice
 * @package Heyday\SilverStripeSlices\DataObjects
 */
class Slice extends DataObject
{
    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
        'Template' => 'Varchar(255)',
        'VisualOptions' => 'Varchar(255)',
        'Sort' => 'Int',
    ];

    private static $has_one = [
        'Parent' => SiteTree::class
    ];

    private static $default_sort = 'Sort ASC';

    /**
     * @var array
     */
    private static $extensions = [
        Versioned::class
    ];

    /**
     * @var string
     */
    private static $table_name = 'Slice';

    /**
     * When common fields were historically stored only on a subclass table (e.g. ContentSlice) but are now read
     * from the base Slice row, map each base field name to the subclass FQCN that still holds legacy values
     * (rows share the same ID). Empty base values are copied with SQL; Stage and Live tables are updated when
     * {@link Versioned} applies.
     *
     * This runs when editing or saving a slice. For GridField lists, run {@link \Heyday\SilverStripeSlices\Tasks\SyncSliceLegacySubclassDataTask} once.
     *
     * @config
     * @var array<string, class-string>
     */
    private static $legacy_subclass_fallback_fields = [];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->syncLegacySubclassFallbackData();

        $fields = parent::getCMSFields();
        $fields->removeByName('Template');
        $fields->removeByName('VisualOptions');

        $config = $this->getCurrentTemplateConfig();

        $this->removeUnconfiguredFields($fields, $config);
        $this->configureFieldsFromConfig($fields, $config);

        // Re-order the fields in the main tab (it would be nice to do this non-destructively)
        if (isset($config['fields']) && count($config['fields'])) {
            $tabFields = $fields->findOrMakeTab('Root.Main')->FieldList();

            if ($tabFields->dataFields()) {
                $tabFields->changeFieldOrder($this->getConfiguredFieldNames($config));
            }
        }

        $this->addTemplateControlFields($fields, $config);

        return $fields;
    }

    public function onBeforeWrite()
    {
        $this->syncLegacySubclassFallbackData();

        parent::onBeforeWrite();

        // Clear visual options when the template changes to prevent them hanging around
        if ($this->isChanged('Template', 2)) {
            $this->VisualOptions = '';
        }

        // Update class name if it needs to change for the selected identifier
        $this->setClassNameByTemplate($this->Template);
    }

    /**
     * Change class name based on the config for a template
     */
    public function setClassNameByTemplate($templateName)
    {
        $config = $this->getTemplateConfig($templateName);

        if (isset($config['className'])) {
            if (!ClassInfo::exists($config['className'])) {
                throw new \RuntimeException("Cannot change {$this->getBaseSliceClass()} be the non-existent class '{$config['className']}'");
            }

            $this->setClassName($config['className']);

            // Prevent an error occurring when changing the class of an object that hasn't been saved yet
            if ($this->unsavedRelations) {
                foreach ($this->unsavedRelations as $name => $list) {
                    if (!$this->hasMethod($name)) {
                        unset($this->unsavedRelations[$name]);
                    }
                }
            }
        } else {
            $this->setClassName($this->getBaseSliceClass());
        }
    }

    /**
     * Configure fields using a template's config
     *
     * @param FieldList $fields
     * @param array $config
     */
    protected function configureFieldsFromConfig(FieldList $fields, array $config)
    {
        $this->configureFieldTypes($fields, $config);
        $this->configureFieldLabels($fields, $config);
        $this->configureFieldHelp($fields, $config);
        $this->configureUploadFolder($fields);
    }

    /**
     * Convert fields to a different field class where configured
     *
     * @param FieldList $fields
     * @param array $config
     */
    protected function configureFieldTypes(FieldList $fields, array $config)
    {
        $this->modifyFieldWithSetting(
            $fields,
            $config,
            'fieldClass',
            function (FormField $field, array $config) use ($fields) {
                $className = $config['fieldClass'];
                $fields->replaceField($field->getName(), $className::create($field->getName()));
            }
        );
    }

    /**
     * Apply the 'help' key from a template config to fields
     *
     * @param FieldList $fields
     * @param array $config
     */
    protected function configureFieldHelp(FieldList $fields, array $config)
    {
        $this->modifyFieldWithSetting($fields, $config, 'help', function (FormField $field, array $config) {
            $field->setRightTitle($config['help']);
        });
    }

    /**
     * Apply the 'label' key from a template config as field titles
     *
     * @param FieldList $fields
     * @param array $config
     */
    protected function configureFieldLabels(FieldList $fields, array $config)
    {
        $this->modifyFieldWithSetting($fields, $config, 'label', function (FormField $field, array $config) {
            $field->setTitle($config['label']);
        });
    }

    /**
     * Add built-in controls for preview and changing the template and visual options
     * These are always visible and can't be hidden from the slices config
     *
     * @param FieldList $fields
     * @param array $config
     */
    protected function addTemplateControlFields(FieldList $fields, array $config)
    {
        // Add the slice preview at the top of the fieldset
        $tab = $fields->findOrMakeTab('Root.Main');
        $firstField = $tab->getChildren()->first() ? $tab->getChildren()->first()->getName() : null;

        // Template selection
        $fields->addFieldToTab(
            'Root.Main',
            new DropdownField('Template', 'Template/type', $this->getTemplateNames()),
            $firstField
        );

        // Visual options selection
        if (isset($config['visualOptions'])) {
            $fields->addFieldToTab(
                'Root.Main',
                new ListboxField(
                    'VisualOptions',
                    'Visual Options',
                    $config['visualOptions'],
                    '',
                    null
                ),
                $firstField
            );
        }
    }

    protected function configureUploadFolder(FieldList $fields)
    {
        $fieldNames = (array)$this->config()->uploadFolderFields;

        foreach ($fieldNames as $name) {
            $field = $fields->dataFieldByName($name);

            if ($field instanceof FileField) {
                $field->setFolderName('Uploads/Slices/' . $this->Template);
            }
        }
    }

    /**
     * Templates from the base slice class (where YAML defines them), after {@link updateTemplateNames}.
     *
     * Polymorphic slice records use concrete class names (e.g. VideoSlice) while `templates` are usually
     * configured on the project base slice (e.g. ContentSlice). {@link getBaseSliceClass()} supplies that class.
     *
     * Extensions may replace or amend the `$templates` array by reference via `updateTemplateNames`.
     *
     * @return array<string, array>
     */
    protected function getEffectiveTemplatesConfig(): array
    {
        $templates = Config::forClass($this->getBaseSliceClass())->get('templates');
        if (!is_array($templates)) {
            $templates = [];
        }

        $this->extend('updateTemplateNames', $templates);

        return is_array($templates) ? $templates : [];
    }

    /**
     * Get a map of template types to human-readable names
     *
     * If the `name` key is not configured for a template, the template identifier will be split into words
     *
     * @return string[]
     */
    public function getTemplateNames()
    {
        $templates = $this->getEffectiveTemplatesConfig();

        $map = [];

        foreach ($templates as $name => $config) {
            if (isset($config['name'])) {
                $map[$name] = $config['name'];
            } else {
                $map[$name] = $this->convertCamelCaseToWords($name);
            }
        }

        return $map;
    }

    /**
     * Get the template config name to be selected by default for new slices
     *
     * @return string
     */
    public function getDefaultTemplate()
    {
        $default = Config::forClass($this->getBaseSliceClass())->get('defaultTemplate');
        if ($default) {
            return $default;
        }

        $templates = $this->getEffectiveTemplatesConfig();
        $identifiers = array_keys($templates);

        return reset($identifiers);
    }

    /**
     * Check if there is a visual option matching the name specified
     *
     * Used from templates like <% if $hasLayoutOption('underline') %><% end_if %>
     *
     * @param string $name
     * @return bool
     */
    public function hasLayoutOption($name)
    {
        return isset($this->record['VisualOptions']) && in_array(
            $name,
            explode(
                ',',
                $this->record['VisualOptions']
            )
        );
    }

    /**
     * Returns a rendered state to use with the dataobject preview field
     *
     * @return string
     */
    public function getPreviewHtml()
    {
        Requirements::clear();

        $previewStylesheets = $this->config()->previewStylesheets;

        if (is_array($previewStylesheets)) {
            foreach ($previewStylesheets as $css) {
                Requirements::css($css);
            }
        }

        // The theme can be disabled when in the context of the CMS, which causes includes to fail
        $themeEnabled = Config::inst()->get(SSViewer::class, 'theme_enabled');
        Config::modify()->set(SSViewer::class, 'theme_enabled', true);

        $result = $this->customise(array(
            'Slice' => $this->forTemplate()
        ))->renderWith('SliceWrapper');

        Requirements::restore();

        // Restore previous theme_enabled setting
        Config::modify()->set(SSViewer::class, 'theme_enabled', $themeEnabled);

        return $result;
    }

    /**
     * Render the slice
     *
     * @return DBHTMLText
     */
    public function forTemplate()
    {
        return $this->renderWith(
            $this->getSSViewer(),
            null
        );
    }

    /**
     * Tries to get an SSViewer based on the current configuration
     *
     * @throws \Exception
     * @return SSViewer
     */
    protected function getSSViewer()
    {
        return new SSViewer(
            $this->getTemplateList()
        );
    }

    /**
     * Return the class name to prefix templates with
     *
     * @return string
     */
    protected function getTemplateClass()
    {
        return $this->getBaseSliceClass();
    }

    /**
     * Return the class name to revert to when no 'className' key is set in the template config
     *
     * This is needed to determine which subclass of Slice should be considered the base one, since when the
     * class name is changed for a template (by configuration), there's no way to tell what subclass was the
     * original one that should be reverted to.
     *
     * @return string
     */
    protected function getBaseSliceClass()
    {
        return __CLASS__;
    }

    /**
     * Return a list of templates to pass to an SSViewer when rendering
     *
     * @return string[]
     * @throws \Exception
     */
    protected function getTemplateList()
    {
        $themes = SSViewer::get_themes();
        if (($key = array_search('$default', $themes)) !== false) {
            unset($themes[$key]);
        }
        $tryTemplates = $this->getTemplateSearchNames();
        $template = ThemeResourceLoader::inst()->findTemplate(
            $tryTemplates,
            $themes
        );

        if (!$template) {
            throw new \Exception(
                'Can\'t find a template from list: "' . implode('", "', $tryTemplates) . '"'
            );
        }

        return [$template];
    }

    /**
     * Return a list of template file names that can be used for the slice
     *
     * @return array
     */
    protected function getTemplateSearchNames()
    {
        $templates = [];
        $prefix = $this->getTemplateClass();

        $templates[] = $prefix . '_' . ($this->Template ?: $this->getDefaultTemplate());

        return $templates;
    }

    /**
     * Convert identifiers into words to show to the user
     *
     * @param string $camelCase
     * @return string
     */
    protected function convertCamelCaseToWords($camelCase)
    {
        $words = trim(strtolower(preg_replace('/_?([A-Z])/', ' $1', $camelCase)));

        return ucfirst(strtolower($words));
    }

    /**
     * Remove all fields except those with specified in a template config
     *
     * @param FieldList $fields
     * @param array $templateConfig
     */
    protected function removeUnconfiguredFields(FieldList $fields, array $templateConfig)
    {
        $configured = $this->getConfiguredFieldNames($templateConfig);
        $inFieldList = array_keys($fields->dataFields());
        $unconfigured = array_diff($inFieldList, $configured);

        if (Director::isDev()) {
            $fields->addFieldToTab('Root.UnusedFields', new LiteralField(
                'SlicesUnusedFieldsHelp',
                <<<EOD
<div class="message" style="margin-top: 0">
    <p><strong>Note: this tab is only visible when in dev mode.</strong></p>
    <p>All fields that aren't configured in the selected slice template are dumped in here by <code>Slice::removeUnconfiguredFields</code></p>
</div>
EOD
            ));
            foreach ($unconfigured as $fieldName) {
                $fields->addFieldToTab('Root.UnusedFields', $fields->dataFieldByName($fieldName));
            }
        } else {
            $fields->removeByName($unconfigured);
        }
    }

    /**
     * DB fields that must always stay on the main slice form, even when not listed under a template’s `fields:`.
     * Without this, {@link removeUnconfiguredFields()} strips them in non-dev mode (no CMS field → looks blank;
     * saves can drop the value). The grid still reads `Title` from the record, so it stayed out of sync.
     *
     * @return string[]
     */
    protected function getBuiltInSliceFieldNames(): array
    {
        return ['Title'];
    }

    /**
     * Field names considered “configured” for pruning and tab field order (YAML `fields` plus built-ins).
     *
     * @param array $templateConfig
     * @return string[]
     */
    protected function getConfiguredFieldNames(array $templateConfig)
    {
        $fromYaml = isset($templateConfig['fields']) ? array_keys($templateConfig['fields']) : [];

        return array_values(array_unique(array_merge($this->getBuiltInSliceFieldNames(), $fromYaml)));
    }

    /**
     * Run a callback for each field config that contains a setting key
     *
     * @param FieldList $fields
     * @param array $config
     * @param string $settingKey
     * @param callable $callback
     */
    protected function modifyFieldWithSetting(FieldList $fields, array $config, $settingKey, $callback)
    {
        if (isset($config['fields'])) {
            foreach ($config['fields'] as $key => $fieldConfig) {
                $field = $fields->dataFieldByName($key);

                if ($field && isset($fieldConfig[$settingKey])) {
                    call_user_func($callback, $field, $fieldConfig);
                }
            }
        }
    }

    /**
     * Get the config for the template name currently configured in $this->Template
     *
     * @return array
     */
    protected function getCurrentTemplateConfig()
    {
        return $this->getTemplateConfig($this->Template ?: $this->getDefaultTemplate()) ?: [];
    }

    /**
     * Get the config for a template of this slice
     *
     * @param string $name
     * @return array|null
     */
    protected function getTemplateConfig($name)
    {
        $config = $this->getEffectiveTemplatesConfig();

        if (isset($config[$name])) {
            return $this->normaliseTemplateConfig($config[$name]);
        }

        return null;
    }

    /**
     * @param array $config
     * @return array
     */
    protected function normaliseTemplateConfig(array $config)
    {
        // Transform "FieldName: 'Field title'" into "FieldName.label: 'Field title'" as a config shortcut
        if (isset($config['fields'])) {
            foreach ($config['fields'] as $fieldName => &$fieldConfig) {
                if (!is_array($fieldConfig)) {
                    $fieldConfig = array(
                        'label' => $fieldConfig
                    );
                }
            }
        }

        return $config;
    }

    /**
     * Copy configured empty fields from legacy subclass rows into the base Slice table for this record, then
     * refresh those properties on the instance from the base table (draft).
     */
    protected function syncLegacySubclassFallbackData(): void
    {
        if (!$this->isInDB()) {
            return;
        }

        $fallbacks = $this->config()->get('legacy_subclass_fallback_fields');
        if (empty($fallbacks) || !is_array($fallbacks)) {
            return;
        }

        static::run_legacy_subclass_sync([(int)$this->ID]);
        $this->reloadLegacyFallbackFieldsFromBase();
    }

    /**
     * Batch copy for all slice rows (or a subset). Returns total affected rows from MySQL.
     *
     * @param int[]|null $ids Null = every ID on the base Slice table.
     */
    public static function run_legacy_subclass_sync(?array $ids = null): int
    {
        $fallbacks = Config::forClass(self::class)->get('legacy_subclass_fallback_fields');
        if (empty($fallbacks) || !is_array($fallbacks)) {
            return 0;
        }

        $schema = DataObject::getSchema();
        $baseTable = $schema->tableName(self::class);
        $useLive = static::singleton()->hasExtension(Versioned::class);

        $grouped = [];
        foreach ($fallbacks as $field => $sourceClass) {
            if (!is_string($field) || !static::is_valid_sql_identifier($field)) {
                continue;
            }
            if (!is_string($sourceClass) || !class_exists($sourceClass)) {
                continue;
            }
            if (!is_subclass_of($sourceClass, self::class)) {
                continue;
            }
            $extTable = $schema->tableName($sourceClass);
            if ($extTable === $baseTable) {
                continue;
            }
            if (!static::table_has_column($baseTable, $field) || !static::table_has_column($extTable, $field)) {
                continue;
            }
            $grouped[$sourceClass][] = $field;
        }

        $total = 0;

        foreach ($grouped as $sourceClass => $fields) {
            $extTable = $schema->tableName($sourceClass);
            if ($extTable === $baseTable) {
                continue;
            }

            $pairs = [[$baseTable, $extTable]];
            if ($useLive) {
                $pairs[] = [$baseTable . '_Live', $extTable . '_Live'];
            }

            foreach ($pairs as [$b, $e]) {
                foreach ($fields as $column) {
                    $total += static::run_legacy_subclass_column_sync($b, $e, $column, $ids);
                }
            }
        }

        return $total;
    }

    /**
     * @param int[]|null $ids
     */
    protected static function run_legacy_subclass_column_sync(
        string $baseTable,
        string $extTable,
        string $column,
        ?array $ids
    ): int {
        if (!static::is_valid_sql_identifier($baseTable)
            || !static::is_valid_sql_identifier($extTable)
            || !static::is_valid_sql_identifier($column)
        ) {
            return 0;
        }

        $sql = sprintf(
            'UPDATE `%1$s` s INNER JOIN `%2$s` c ON s.`ID` = c.`ID` '
            . 'SET s.`%3$s` = c.`%3$s` '
            . 'WHERE (s.`%3$s` IS NULL OR s.`%3$s` = \'\') '
            . 'AND c.`%3$s` IS NOT NULL AND TRIM(c.`%3$s`) <> \'\'',
            $baseTable,
            $extTable,
            $column
        );

        if ($ids !== null) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if ($ids === []) {
                return 0;
            }
            $sql .= ' AND s.`ID` IN (' . implode(',', $ids) . ')';
        }

        DB::query($sql);

        return (int)DB::affected_rows();
    }

    /**
     * Reload fallback columns from the draft base table into this instance after a sync.
     */
    protected function reloadLegacyFallbackFieldsFromBase(): void
    {
        $fallbacks = $this->config()->get('legacy_subclass_fallback_fields');
        if (empty($fallbacks) || !is_array($fallbacks)) {
            return;
        }

        $fields = [];
        $baseTable = DataObject::getSchema()->tableName(self::class);
        foreach (array_keys($fallbacks) as $field) {
            if (is_string($field) && static::is_valid_sql_identifier($field) && static::table_has_column($baseTable, $field)) {
                $fields[] = $field;
            }
        }

        if ($fields === []) {
            return;
        }

        $quoted = array_map(static fn ($f) => '`' . $f . '`', $fields);
        $sql = 'SELECT ' . implode(',', $quoted) . ' FROM `' . $baseTable . '` WHERE `ID` = ?';
        $row = DB::prepared_query($sql, [$this->ID])->record();

        if (!$row) {
            return;
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $this->setField($field, $row[$field]);
            }
        }
    }

    protected static function is_valid_sql_identifier(string $name): bool
    {
        return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name);
    }

    protected static function table_has_column(string $table, string $column): bool
    {
        if (!static::is_valid_sql_identifier($table) || !static::is_valid_sql_identifier($column)) {
            return false;
        }

        $list = DB::field_list($table);
        if (!$list) {
            return false;
        }

        foreach (array_keys($list) as $key) {
            if (strcasecmp((string)$key, $column) === 0) {
                return true;
            }
        }

        return false;
    }
}

