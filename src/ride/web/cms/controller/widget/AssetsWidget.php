<?php

namespace ride\web\cms\controller\widget;

use ride\library\cms\node\NodeProperty;
use ride\library\orm\OrmManager;
use ride\library\validation\exception\ValidationException;

use ride\web\base\form\AssetSelectComponent;

/**
 * Widget to show assets
 */
class AssetsWidget extends AbstractWidget implements StyleWidget {

	/**
	 * Machine name of this widget
	 * @var string
	 */
    const NAME = 'assets';

    /**
     * Path to the icon of this widget
     * @var string
     */
    const ICON = 'img/cms/widget/assets.png';

    /**
     * Property for the title
     * @var string
     */
    const PROPERTY_TITLE = 'title';

    /**
     * Property for the selected folder
     * @var string
     */
    const PROPERTY_FOLDER = 'folder';

    /**
     * Property for the selected assets
     * @var string
     */
    const PROPERTY_ASSETS = 'assets';

    /**
     * Property to see if unlocalized assets should be resolved
     * @var string
     */
    const PROPERTY_UNLOCALIZED = 'unlocalized';

    /**
     * Namespace for the templates of this widget
     * @var string
     */
    const TEMPLATE_NAMESPACE = 'cms/widget/assets';

    /**
     * Sets the selected assets to the response
     * @return null
     */
    public function indexAction(OrmManager $orm) {
        $assets = array();
        $folder = null;
        $this->resolveAssets($orm, $assets, $folder);

        $this->setTemplateView($this->getTemplate(static::TEMPLATE_NAMESPACE . '/default'), array(
            'title' => $this->properties->getWidgetProperty(self::PROPERTY_TITLE . '.' . $this->locale),
            'folder' => $folder,
            'assets' => $assets,
        ));

    	if ($this->properties->isAutoCache()) {
    	    $this->properties->setCache(true);
    	}
    }

    public function getPropertiesPreview() {
        $translator = $this->getTranslator();
        $preview = '';

        $assets = array();
        $folder = null;
        $this->resolveAssets($this->dependencyInjector->get('ride\\library\\orm\\OrmManager'), $assets, $folder);

        if ($folder && $this->properties->getWidgetProperty(self::PROPERTY_FOLDER)) {
            $preview .= '<strong>' . $translator->translate('label.folder') . '</strong>: ' . $folder->getName() . '<br>';
        } elseif ($assets && $this->properties->getWidgetProperty(self::PROPERTY_ASSETS)) {
            foreach ($assets as $index => $asset) {
                $assets[$index] = $asset->getName();
            }

            $preview .= '<strong>' . $translator->translate('label.assets') . '</strong>: ' . implode(', ', $assets) . '<br>';
        }

        if ($this->getSecurityManager()->isPermissionGranted('cms.advanced')) {
            $template = $this->getTemplate(static::TEMPLATE_NAMESPACE . '/default');
        } else {
            $template = $this->getTemplateName($this->getTemplate(static::TEMPLATE_NAMESPACE . '/default'));
        }
        $preview .= '<strong>' . $translator->translate('label.template') . '</strong>: ' . $template . '<br>';

        return $preview;
    }

    /**
     * Action to handle and show the properties of this widget
     * @return null
     */
    public function propertiesAction(OrmManager $orm, AssetSelectComponent $component) {
        $assetModel = $orm->getAssetModel();
        $folderModel = $orm->getAssetFolderModel();

        $translator = $this->getTranslator();

        $folder = $this->properties->getWidgetProperty(self::PROPERTY_FOLDER);
        $assets = $this->properties->getWidgetProperty(self::PROPERTY_ASSETS);
        if ($assets) {
            $display = self::PROPERTY_ASSETS;

            $assets = explode(NodeProperty::LIST_SEPARATOR, $assets);
            foreach ($assets as $index => $assetId) {
                $assets[$index] = $assetModel->createProxy($assetId);
            }
        } else {
            $display = self::PROPERTY_FOLDER;
        }

        $data = array(
            'display' => $display,
            self::PROPERTY_FOLDER => $folder,
            self::PROPERTY_ASSETS => $assets,
            self::PROPERTY_UNLOCALIZED => $this->properties->getWidgetProperty(self::PROPERTY_UNLOCALIZED),
            self::PROPERTY_TEMPLATE => $this->getTemplate(static::TEMPLATE_NAMESPACE . '/default'),
            self::PROPERTY_TITLE => $this->properties->getWidgetProperty(self::PROPERTY_TITLE . '.' . $this->locale),
        );

        $folders = array();
        $component->setLocale($this->locale);

        $form = $this->createFormBuilder($data);
        $form->addRow('display', 'option', array(
            'label' => $translator->translate('label.display'),
            'attributes' => array(
                'data-toggle-dependant' => 'option-display',
            ),
            'options' => array(
                'folder' => $translator->translate('label.folder'),
                'assets' => $translator->translate('label.assets'),
            ),
        ));
        $form->addRow(self::PROPERTY_FOLDER, 'select', array(
            'label' => $translator->translate('label.folder'),
            'attributes' => array(
                'class' => 'option-display option-display-folder',
            ),
            'options' => array('' => '') + $folderModel->getOptionList($this->locale, true),
        ));
        $form->addRow(self::PROPERTY_ASSETS, 'assets', array(
            'label' => $translator->translate('label.assets'),
            'attributes' => array(
                'class' => 'option-display option-display-assets',
            ),
            'multiple' => true,
            'order' => true,
        ));
        $form->addRow(self::PROPERTY_UNLOCALIZED, 'boolean', array(
            'label' => $translator->translate('label.unlocalized'),
            'description' => $translator->translate('label.unlocalized.description'),
        ));
        $form->addRow(self::PROPERTY_TEMPLATE, 'select', array(
            'label' => $translator->translate('label.template'),
            'description' => $translator->translate('label.template.widget.description'),
            'options' => $this->getAvailableTemplates(static::TEMPLATE_NAMESPACE),
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow(self::PROPERTY_TITLE, 'string', array(
            'label' => $translator->translate('label.title'),
        ));

        $form = $form->build();
        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                return false;
            }

            try {
                $form->validate();

                $data = $form->getData();

                if ($data['display'] == self::PROPERTY_ASSETS) {
                    $assets = array();
                    foreach ($data[self::PROPERTY_ASSETS] as $asset) {
                        $assets[] = $asset->getId();
                    }

                    $this->properties->setWidgetProperty(self::PROPERTY_ASSETS, implode(NodeProperty::LIST_SEPARATOR, $assets));
                    $this->properties->setWidgetProperty(self::PROPERTY_FOLDER, null);
                } else {
                    $this->properties->setWidgetProperty(self::PROPERTY_ASSETS, null);
                    $this->properties->setWidgetProperty(self::PROPERTY_FOLDER, $data[self::PROPERTY_FOLDER]);
                }

                $this->properties->setWidgetProperty(self::PROPERTY_UNLOCALIZED, $data[self::PROPERTY_UNLOCALIZED]);
                $this->properties->setWidgetProperty(self::PROPERTY_TITLE . '.' . $this->locale, $data[self::PROPERTY_TITLE]);
                $this->setTemplate($data[self::PROPERTY_TEMPLATE]);

                return true;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView(static::TEMPLATE_NAMESPACE . '/properties', array(
            'form' => $form->getView(),
        ));

        return false;
    }

    /**
     * Resolves the assets and folder set in the properties
     * @param \ride\library\orm\OrmManager $orm
     * @param array $assets
     * @param AssetFolderEntry $folder
     * @return null
     */
    protected function resolveAssets(OrmManager $orm, array &$assets, &$folder) {
        $assetModel = $orm->getAssetModel();

        $fetchUnlocalized = $this->properties->getWidgetProperty(self::PROPERTY_UNLOCALIZED) ? true : false;

        $folder = $this->properties->getWidgetProperty(self::PROPERTY_FOLDER);
        $assets = $this->properties->getWidgetProperty(self::PROPERTY_ASSETS);
        if ($assets) {
            $assets = explode(NodeProperty::LIST_SEPARATOR, $assets);
            foreach ($assets as $index => $assetId) {
                $asset = $assetModel->getById($assetId, $this->locale, $fetchUnlocalized);
                if ($asset && ($fetchUnlocalized || (!$fetchUnlocalized && $asset->isLocalized()))) {
                    $assets[$index] = $asset;
                } else {
                    unset($assets[$index]);
                }
            }
        } elseif ($folder) {
            $folderModel = $orm->getAssetFolderModel();

            $folder = $folderModel->getFolder($folder, $this->locale, $fetchUnlocalized);

            $assets = $assetModel->getByFolder($folder, $this->locale, $fetchUnlocalized);
        }
    }

    /**
     * Gets the options for the styles
     * @return array Array with the name of the option as key and the
     * translation key as value
     */
    public function getWidgetStyleOptions() {
        return array(
            'container' => 'label.style.container',
            'title' => 'label.style.title',
        );
    }

}
