<?php

/**
 * Class Slice
 * 
 */
class Slice extends DataObject implements DataObjectPreviewInterface
{
    /**
     * STATIC PROPERTIES START
     */

    /**
     * @var array
     */
    private static $dependencies = array(
        'previewer' => '%$DataObjectPreviewer'
    );
    /**
     * @var array
     */
    private static $db = array(
        'Sort'            => 'Int',
        'BackgroundColor' => 'Varchar(255)',
        'BlockColor'      => 'Varchar(255)'
    );
    /**
     * @var array
     */
    private static $has_one = array(
        'Parent'         => 'Page',
        'SecondaryImage' => 'Image'        
    );



    /**
     * @var string
     */
    private static $default_sort = 'Sort ASC';
    /**
     * STATIC PROPERTIES END
     */
    
    /**
     * INSTANCE PROPERTIES START
     */
    /**
     * @var DataObjectPreviewer
     */
    public $previewer;

    /**
     * INSTANCE PROPERTIES
     */

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {

        $fields = parent::getCMSFields();
        
        $fields->replaceField('Title', new TextareaField('Title'));

        /** @var Config_ForClass $config */
        $config = $this->config();


        // Make tertiary identifiers map to class names
        // Get uninherited ids so different id's can be made available per class
        $tIdentifiers = $this->getConfigMerged(
            $config,
            'tertiaryIdentifiers',
            Config::UNINHERITED
        );

        if (is_array($tIdentifiers) && $tIdentifiers) {
            $fields->replaceField(
                'TertiaryIdentifier',
                $tIdentifier = new ListboxField(
                    'TertiaryIdentifier',
                    'Visual Options',
                    $tIdentifiers,
                    '',
                    null,
                    true
                )
            );
        } else {
            $fields->removeByName('TertiaryIdentifier');
        }


        
        // Set up slice specific fields

        // Color options for palette fields
        $colorOptions = $config->get('colorOptions');

        // Color palette for bg
        $fields->addFieldToTab(
            'Root.Main',
            new GroupedColorPaletteField(
                'BackgroundColor',
                'Background color',
                $colorOptions
            )
        );

        // Color palette for blocks
        $fields->addFieldToTab(
            'Root.Main',
            new GroupedColorPaletteField(
                'BlockColor',
                'Block color',
                $colorOptions
            )
        );

        // Get the tab to ensure the preview is added at the top
        $tab = $fields->findOrMakeTab('Root.Main');
        $fieldsInTab = $tab->getChildren();

        $tab->insertBefore(
            new DataObjectPreviewField(
                'Slice',
                $this,
                $this->previewer
            ),
            isset($fieldsInTab[0]) ? $fieldsInTab[0]->getName() : null //Display the field at the top
        );

        
        

        
        $this->updateCMSFieldsByConfiguration($config, $fields);

        return $fields;
    }
    /**
     * @param $config
     * @param $fields
     */
    protected function updateCMSFieldsByConfiguration(Config_ForClass $config, $fields)
    {
        // Set up upload folders on file fields
        $uploadFolder = $config->get('uploadFolder');
        foreach ($this->getConfigMerged($config, 'uploadFolderFields') as $fieldName) {
            if ($field = $fields->dataFieldByName($fieldName)) {
                $field->setFolderName($uploadFolder);
            }
        }


        // Hidden fields for this Slice type
        $shownFields = array_diff(
            $this->getConfigMerged($config, 'shownFields'),
            $this->getConfigMerged($config, 'hiddenFields')
        );

        // Clean up tabs show relevant fields in appropriate tabs
        // Add non displayed fields to an "AdditionalFields" tab
        foreach ($fields->saveableFields() as $name => $field) {
            if (!in_array($name, $shownFields)) {
                $fields->removeByName($name);
                if (Director::isDev() || Director::isTest()) {
                    $fields->addFieldToTab('Root.AdditionalFields', $field);
                }
            }
        }

        // Change any field titles that need it
        $fieldLabels = $this->getConfigMerged($config, 'fieldLabels');
        foreach ($fieldLabels as $fieldName => $label) {
            $fields->renameField($fieldName, $label);
        }
    }
    /**
     * DATAOBJECT METHODS END
     */

    /**
     * HELPER METHODS START
     */
    /**
     * Gets an array for a specified config key, using the secondary identifier + default options combined
     * @param Config_ForClass $config
     * @param                 $configKey
     * @param int             $sourceOptions
     * @return array
     */
    public function getConfigMerged(Config_ForClass $config, $configKey, $sourceOptions = 0)
    {
        $fields = $config->get($configKey, $sourceOptions);

        return array_merge(
            isset($fields['Default']) && is_array($fields['Default']) ? $fields['Default'] : array(),
            isset($fields[$this->SecondaryIdentifier]) && is_array($fields[$this->SecondaryIdentifier]) ? $fields[$this->SecondaryIdentifier] : array()
        );
    }
    /**
     * Checks if there is a tertiary identifier matching the requested one
     * 
     * Used from templates like <% if $hasLayoutOption('underline') %><% end_if %>
     * @param $option
     * @return bool
     */
    public function hasLayoutOption($option)
    {
        return isset($this->record['TertiaryIdentifier']) && in_array(
            $option,
            explode(
                ',',
                $this->record['TertiaryIdentifier']
            )
        );
    }
    /**
     * Can be used for classnames
     * 
     * Gets tertiary identifiers in a format like "underline no-margin-bottom"
     * @return mixed
     */
    public function getTertiaryIdentifiers()
    {
        return str_replace(
            ',',
            ' ',
            isset($this->record['TertiaryIdentifier']) ? $this->record['TertiaryIdentifier'] : ''
        );
    }
    /**
     * Get a nice comma separated version of the tertiary identifiers like "No Margin Bottom, Underline"
     * @return string
     */
    public function getTertiaryIdentifiersNice()
    {
        $tIdentifiersNice = array();
        if (isset($this->record['TertiaryIdentifier'])) {
            $config = $this->getConfigMerged(
                $this->config(),
                'tertiaryIdentifiers',
                Config::UNINHERITED
            );
            $tIdentifiers = explode(',', $this->record['TertiaryIdentifier']);
            $tIdentifiersNice = array();
            foreach ($tIdentifiers as $identifier) {
                $tIdentifiersNice[] = $config[$identifier];
            }
        }
        return implode(', ', $tIdentifiersNice);
    }
    /**
     * HELPER METHODS END
     */

    /**
     * VISUAL HELPER METHODS START
     */
    /**
     * This returns a Template for use by the preview grid field and field
     * 
     * If there is no fields specific on the to be rendered object then we default in some
     * fields from the config.
     * @return string
     */
    public function getPreviewHtml()
    {
        $hasOneData = array ();
        // Populate record with default values if needed
        if (!$this->ID || !$this->Identifier) {

            $this->record = array_filter($this->record);
            
            $this->record = array_merge(
                $exampleFields = $this->getConfigMerged($this->config(), 'exampleFields'),
                $this->record
            );


            $injectorCreator = new InjectionCreator();
            $hasOneData = array ();
            foreach ($this->has_one() as $relation => $type) {
                if (!$this->{$relation.'ID'} && isset($exampleFields[$relation])) {
                    switch ($type) {
                        case 'Image' || is_subclass_of($type, "Image"):
                            $hasOneData[$relation] = $injectorCreator->create('Image_Cached', $exampleFields[$relation]);                            
                        break;
                    }
                }
            }         
        }


        Config::inst()->update('SSViewer', 'theme_enabled', 'heyday');

        Requirements::clear();

        foreach($this->config()->previewStylesheets as $css) {
            Requirements::css($css);
        }

        $result = $this->customise(array(
            'Slice' => $this->customise($hasOneData)->forTemplate()
        ))->renderWith('SliceWrapper');

        Requirements::restore();

        return $result;
    }
    /**
     * Used in templates to get a iframe preview of the slice
     * @return string
     */
    public function getPreview()
    {
        return $this->previewer->preview($this);
    }

    /**
     * Use in the actual rendering of the slice. Uses `getSSViewer` (with the Secondary Identifier) for rendering
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
     * VISUAL HELPER METHODS END
     */

    /**
     * MODEL HELPER METHODS START
     */
    /**
     * Gets a list of clients with the list optionally excluding certain clients
     * @return mixed
     */
    public function getClients()
    {
        $clients = WorkSubHubPage::get()->filter('Miscellaneous', false);

        if (isset($this->record['TertiaryIdentifier'])) {
            return $clients->exclude(
                'ID',
                explode(',', trim($this->record['TertiaryIdentifier'], ','))
            );
        }

        return $clients;
    }
    /**
     * Gets a list of clients who are featured
     * @return mixed
     */
    public function getFeaturedClients()
    {
        return WorkSubHubPage::get()
            ->filter('Miscellaneous', false)
            ->filter('Featured', true)
            ->exclude('ID', $this->ParentID);
    }
    /**
     * Gets a list of case studies
     * @return mixed
     */
    public function getCaseStudies()
    {
        $caseStudies = CaseStudyPage::get();

        if (isset($this->record['TertiaryIdentifier'])) {
            return $caseStudies->filter(
                'ID',
                explode(',', trim($this->record['TertiaryIdentifier'], ','))
            );
        }

        return $caseStudies->exclude('ID', $this->ParentID);
    }
    /**
     * MODEL HELPER METHODS END
     */
}