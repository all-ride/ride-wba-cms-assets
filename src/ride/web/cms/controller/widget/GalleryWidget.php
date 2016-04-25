<?php

namespace ride\web\cms\controller\widget;

use ride\library\cms\node\NodeProperty;
use ride\library\orm\OrmManager;
use ride\library\router\Route;
use ride\library\validation\exception\ValidationException;

/**
 * Widget to show assets
 */
class GalleryWidget extends AbstractWidget implements StyleWidget {

	/**
	 * Machine name of this widget
	 * @var string
	 */
    const NAME = 'gallery';

    /**
     * Path to the icon of this widget
     * @var string
     */
    const ICON = 'img/cms/widget/assets.png';

    /**
     * Property for the selected folder
     * @var string
     */
    const PROPERTY_FOLDER = 'folder';

    /**
     * Property to see if unlocalized assets should be resolved
     * @var string
     */
    const PROPERTY_UNLOCALIZED = 'unlocalized';

    /**
     * Namespace for the templates of this widget
     * @var string
     */
    const TEMPLATE_NAMESPACE = 'cms/widget/assets-gallery';

    /**
     * Gets the additional sub routes for this widget
     * @return array|null Array with a route path as key and the action method
     * as value
     */
    public function getRoutes() {
        return array(
            new Route('/%folder%', array($this, 'indexAction'), 'gallery.folder.detail', array('head', 'get')),
        );
    }

    /**
     * Sets the selected assets to the response
     * @return null
     */
    public function indexAction(OrmManager $orm, $folder = null) {
        $rootFolder = $this->resolveFolder($orm);
        $title = $this->properties->getWidgetProperty(self::PROPERTY_TITLE . '.' . $this->locale);

        // resolve requested folder, check if child of root folder
        if ($folder) {
            $folder = $this->resolveFolder($orm, $folder);
            if (!$folder || !$folder->hasParentFolder($rootFolder)) {
                $this->response->setNotFound();

                return;
            }

            $title = $folder->getName();
        } else {
            $folder = $rootFolder;
        }

        if ($title) {
            $this->setPageTitle($title);
            $title = null;
        }

        $folderModel = $orm->getAssetFolderModel();
        $fetchUnlocalized = $this->willFetchUnlocalized();

        // add breadcrumbs till the root folder
        if ($folder->getId() != $rootFolder->getId()) {
            $breadcrumbs = array();

            $parent = $folder;
            do {
                $breadcrumbs[$this->getUrl('gallery.folder.detail', array('folder' => $parent->getSlug()))] = $parent->getName();

                $parent = $folderModel->getFolder($parent->getParentFolderId(), $this->locale, $fetchUnlocalized);
            } while ($parent && $parent->getId() != $rootFolder->getId());

            $breadcrumbs = array_reverse($breadcrumbs, true);

            foreach ($breadcrumbs as $url => $label) {
                $this->addBreadcrumb($url, $label);
            }
        }

        // fetch children and assets
        $assets = $folder->getAssets();
        $children = array();
        $folders = $folderModel->getFolders($folder, $this->locale, $fetchUnlocalized);
        foreach ($folders as $child) {
            $children[$this->getUrl('gallery.folder.detail', array('folder' => $child->getSlug()))] = $child;
        }

        // assign to view
        $this->setTemplateView($this->getTemplate(static::TEMPLATE_NAMESPACE . '/default'), array(
            'title' => $title,
            'parent' => $folder,
            'children' => $children,
            'assets' => $assets,
        ));
    }

    public function getPropertiesPreview() {
        $translator = $this->getTranslator();
        $preview = '';

        $folder = $this->resolveFolder($this->dependencyInjector->get('ride\\library\\orm\\OrmManager'));

        if ($folder) {
            $preview .= '<strong>' . $translator->translate('label.folder') . '</strong>: ' . $folder->getName() . '<br>';
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
    public function propertiesAction(OrmManager $orm) {
        $folderModel = $orm->getAssetFolderModel();

        $translator = $this->getTranslator();

        $folder = $this->properties->getWidgetProperty(self::PROPERTY_FOLDER);

        $data = array(
            self::PROPERTY_FOLDER => $folder,
            self::PROPERTY_UNLOCALIZED => $this->properties->getWidgetProperty(self::PROPERTY_UNLOCALIZED),
            self::PROPERTY_TEMPLATE => $this->getTemplate(static::TEMPLATE_NAMESPACE . '/default'),
            self::PROPERTY_TITLE => $this->properties->getWidgetProperty(self::PROPERTY_TITLE . '.' . $this->locale),
        );

        $folders = array();

        $form = $this->createFormBuilder($data);
        $form->addRow(self::PROPERTY_FOLDER, 'select', array(
            'label' => $translator->translate('label.folder'),
            'options' => array('' => '') + $folderModel->getOptionList($this->locale, true),
            'validators' => array(
                'required' => array(),
            ),
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

                $this->properties->setWidgetProperty(self::PROPERTY_FOLDER, $data[self::PROPERTY_FOLDER]);
                $this->properties->setWidgetProperty(self::PROPERTY_UNLOCALIZED, $data[self::PROPERTY_UNLOCALIZED] ? 1 : null);
                $this->properties->setWidgetProperty(self::PROPERTY_TITLE . '.' . $this->locale, $data[self::PROPERTY_TITLE] ? $data[self::PROPERTY_TITLE] : null);
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
     * Resolves the folder set in the properties
     * @param \ride\library\orm\OrmManager $orm
     * @return AssetFolderEntry
     */
    protected function resolveFolder(OrmManager $orm, $folderId = null) {
        if ($folderId === null) {
            $folderId = $this->properties->getWidgetProperty(self::PROPERTY_FOLDER);
        }

        $folderModel = $orm->getAssetFolderModel();

        return $folderModel->getFolder($folderId, $this->locale, $this->willFetchUnlocalized());
    }

    private function willFetchUnlocalized() {
        return $this->properties->getWidgetProperty(self::PROPERTY_UNLOCALIZED) ? true : false;
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
