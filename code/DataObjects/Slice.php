<?php

class Slice extends DataObject implements DataObjectPreviewInterface
{
    private static $dependencies = array(
        'previewer' => '%$DataObjectPreviewer'
    );

    private static $db = array(
        'Template' => 'Varchar(255)',
        'VisualOptions' => 'Varchar(255)',
        'Sort' => 'Int',
    );

    private static $has_one = array(
        'Parent' => 'Page'
    );

    private static $default_sort = 'Sort ASC';

    /**
     * @var DataObjectPreviewer
     */
    public $previewer;

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Template');
        $fields->removeByName('VisualOptions');

        $config = $this->getCurrentTemplateConfig();

        $this->removeUnconfiguredFields($fields, $config);
        $this->configureFieldTypes($fields, $config);
        $this->configureFieldLabels($fields, $config);
        $this->configureFieldHelp($fields, $config);
        $this->configureUploadFolder($fields);

        // Re-order the fields in the main tab (it would be nice to do this non-destructively)
        if (isset($config['fields']) && count($config['fields'])) {
            $fields->findOrMakeTab('Root.Main')
                ->FieldList()
                ->changeFieldOrder($this->getConfiguredFieldNames($config));

        } 

        $this->addTemplateControlFields($fields, $config);

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Clear visual options when the template changes to prevent them hanging around
        if ($this->isChanged('Template', 2)) {
            $this->VisualOptions = '';
        }

        // Update class name if it needs to change for the selected identifier
        $this->setClassNameByTemplate($this->Template);
    }

    /**
     * Convert fields to a different field class where configured
     *
     * @param FieldList $fields
     * @param array $config
     */
    protected function configureFieldTypes(FieldList $fields, array $config)
    {
        $this->modifyFieldWithSetting($fields, $config, 'fieldClass',
            function(FormField $field, array $config) use ($fields) {
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
        $this->modifyFieldWithSetting($fields, $config, 'help', function(FormField $field, array $config) {
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
        $this->modifyFieldWithSetting($fields, $config, 'label', function(FormField $field, array $config) {
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

        // Add the slice preview at the top of the tab
        $fields->addFieldToTab(
            'Root.Main',
            new DataObjectPreviewField(
                'Slice',
                $this,
                $this->previewer
            ),
            $firstField
        );

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
                    null,
                    true
                ),
                $firstField
            );
        }
    }

    protected function configureUploadFolder(FieldList $fields)
    {
        $fieldNames = (array) $this->config()->uploadFolderFields;

        foreach ($fieldNames as $name) {
            $field = $fields->dataFieldByName($name);

            if ($field instanceof FileField) {
                $field->setFolderName('Uploads/Slices/' . $this->Template);
            }
        }
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
        $templates = $this->config()->templates;
        $map = array();

        foreach ($templates as $name => $config) {
            if (isset($config['name'])) {
                $map[$name] = $config['name'];
            } else {
                $map[$name] = $this->convertCamelCaseToWords($name);
            }
        }

        return $map;
    }

    public function getDefaultTemplate()
    {
        if ($this->config()->defaultTemplate) {
            return $this->config()->defaultTemplate;
        } else {
            $identifiers = $this->getAvailableTemplates();
            return reset($identifiers);
        }
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
            foreach($previewStylesheets as $css) {
                Requirements::css($css);
            }
        }

        $result = $this->customise(array(
            'Slice' => $this->forTemplate()
        ))->renderWith('SliceWrapper');

        Requirements::restore();

        return $result;
    }

    /**
     * Used in templates to get a iframe preview of the slice
     *
     * @return string
     */
    public function getPreview()
    {
        return $this->previewer->preview($this);
    }

    /**
     * Render the slice
     *
     * @return HTMLText
     */
    public function forTemplate()
    {
        if ($arguments = func_get_args()) {
            $data = array();
            $i = 1;
            foreach ($arguments as $argument) {
                $data["Val$i"] = empty($argument) ? false : $argument;
                $i++;
            }
        } else {
            $data = null;
        }
        try {
            return $this->ClassName !== 'Slice' ? $this->renderWith(
                $this->getSSViewer(),
                $data
            ) : false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Finds all available template based on the ClassName
     *
     * @param array $map
     * @return array
     */
    public function getAvailableTemplates($map = null)
    {
        $prefix = strtolower($this->getTemplateClass());
        $currentTheme = Config::inst()->get('SSViewer', 'theme');
        $templates = SS_TemplateLoader::instance()->getManifest()->getTemplates();
        $availableTemplates = array();

        foreach ($templates as $templateName => $template) {
            if (
                fnmatch($prefix . '_*', $templateName)
                && isset($template['themes'])
                && isset($template['themes'][$currentTheme])
            ) {
                // SilverStripe transforms all template names to lowercase.
                // We want the original filename, so this needs to be extracted from the template file path
                $templateName = $this->getFirstLeafNode($template);
                $templateName = substr(basename($templateName), strlen($prefix) + 1, -3);
                $availableTemplates[$templateName] = $templateName;
            }
        }

        $availableTemplates = is_array($availableTemplates) ? $availableTemplates : array();

        if (is_array($map)) {
            foreach ($availableTemplates as $key => $value) {
                $availableTemplates[$key] = isset($map[$value]) ? $map[$value] : $value;
            }
        }

        return $availableTemplates;
    }

    /**
     * Tries to get an SSViewer based on the current configuration
     *
     * @throws Exception
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
     * Change class name based on the config for a template
     */
    protected function setClassNameByTemplate($templateName)
    {
        $config = $this->getTemplateConfig($templateName);

        if (isset($config['className'])) {
            if (!ClassInfo::exists($config['className'])) {
                throw new RuntimeException("Cannot change {$this->getBaseSliceClass()} be the non-existent class '{$config['className']}'");
            }

            $this->setClassName($config['className']);

            // Prevent an error occurring when changing the class of an object that hasn't been saved yet
            if($this->unsavedRelations) {
                foreach($this->unsavedRelations as $name => $list) {
                    if(!$this->hasMethod($name)) {
                        unset($this->unsavedRelations[$name]);
                    }
                }
            }
        } else {
            $this->setClassName($this->getBaseSliceClass());
        }
    }

    /**
     * Return a list of templates to pass to an SSViewer when rendering
     *
     * @return string[]
     * @throws Exception
     */
    protected function getTemplateList()
    {
        $templates = SS_TemplateLoader::instance()->findTemplates(
            $tryTemplates = $this->getTemplateSearchNames(), Config::inst()->get('SSViewer', 'theme')
        );

        if (!$templates) {
            throw new Exception(
                'Can\'t find a template from list: "'.implode('", "', $tryTemplates).'"'
            );
        }

        return reset($templates);
    }

    /**
     * Return a list of template file names that can be used for the slice
     *
     * @return array
     */
    protected function getTemplateSearchNames()
    {
        $templates = array();
        $prefix = $this->getTemplateClass();

        if (!empty($this->Template)) {
            $templates[] = $prefix . '_' . $this->Template;
        }

        return $templates;
    }

    /**
     * Given a set of nested arrays, return the first leaf encountered
     *
     * @param string[] $tree
     * @return mixed
     */
    protected function getFirstLeafNode(array $tree)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($tree),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach($iterator as $value) {
            return $value;
        }
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
            foreach ($unconfigured as $fieldName) {
                $fields->addFieldToTab('Root.UnusedFields', $fields->dataFieldByName($fieldName));
            }
        } else {
            $fields->removeByName($unconfigured);
        }
    }

    /**
     * @param array $templateConfig
     * @return string[]
     */
    protected function getConfiguredFieldNames(array $templateConfig)
    {
        return isset($templateConfig['fields']) ? array_keys($templateConfig['fields']) : array();
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
        return $this->getTemplateConfig($this->Template ?: $this->getDefaultTemplate()) ?: array();
    }

    /**
     * Get the config for a template of this slice
     *
     * @param string $name
     * @return array
     */
    protected function getTemplateConfig($name)
    {
        $config = $this->config()->get('templates') ?: array();

        if (isset($config[$name])) {
            return $this->normaliseTemplateConfig($config[$name]);
        }
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
}
