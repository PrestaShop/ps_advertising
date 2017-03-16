<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Advertising extends Module implements WidgetInterface
{
    /* Title associated to the image */
    public $adv_title;

    /* Link associated to the image */
    public $adv_link;

    /* Name of the image without extension */
    public $adv_imgname;

    /* Image path with extension */
    public $adv_img;

    public function __construct()
    {
        $this->name = 'ps_advertising';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.2';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->getTranslator()->trans('Advertising block', array(), 'Modules.Advertising.Admin');
        $this->description = $this->getTranslator()->trans('Adds an advertisement block to selected sections of your e-commerce website.', array(), 'Modules.Advertising.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);

        $this->initialize();
    }

    /*
     * Set the properties of the module, like the link to the image and the title (contextual to the current shop context)
     */
    protected function initialize()
    {
        $this->adv_imgname = 'advertising';
        if ((Shop::getContext() == Shop::CONTEXT_GROUP || Shop::getContext() == Shop::CONTEXT_SHOP)
            && file_exists(_PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'-g'.$this->context->shop->getContextShopGroupID().'.'.Configuration::get('BLOCKADVERT_IMG_EXT'))
        ) {
            $this->adv_imgname .= '-g'.$this->context->shop->getContextShopGroupID();
        }
        if (Shop::getContext() == Shop::CONTEXT_SHOP
            && file_exists(_PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'-s'.$this->context->shop->getContextShopID().'.'.Configuration::get('BLOCKADVERT_IMG_EXT'))
        ) {
            $this->adv_imgname .= '-s'.$this->context->shop->getContextShopID();
        }

        // If none of them available go default
        if ($this->adv_imgname == 'advertising') {
            $this->adv_img = Tools::getMediaServer($this->name)._MODULE_DIR_.$this->name.'/img/fixtures/'.$this->adv_imgname.'.jpg';
        } else {
            $this->adv_img = Tools::getMediaServer($this->name)._MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'.'.Configuration::get('BLOCKADVERT_IMG_EXT');
        }
        $this->adv_link = htmlentities(Configuration::get('BLOCKADVERT_LINK'), ENT_QUOTES, 'UTF-8');
        $this->adv_title = htmlentities(Configuration::get('BLOCKADVERT_TITLE'), ENT_QUOTES, 'UTF-8');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        $displayLeftColumn = $this->registerHook('displayLeftColumn');
        $displayRightColumn = $this->registerHook('displayRightColumn');

        if ($displayLeftColumn || $displayRightColumn) {
            Configuration::updateGlobalValue('BLOCKADVERT_LINK', 'http://www.prestashop.com/');
            Configuration::updateGlobalValue('BLOCKADVERT_TITLE', 'PrestaShop');
            Configuration::updateGlobalValue('BLOCKADVERT_LEFT_COLUMN', true);
            Configuration::updateGlobalValue('BLOCKADVERT_RIGHT_COLUMN', true);
            // Try to update with the extension of the image that exists in the module directory
            foreach (scandir(_PS_MODULE_DIR_.$this->name) as $file) {
                if (in_array($file, array('advertising.jpg', 'advertising.gif', 'advertising.png'))) {
                    Configuration::updateGlobalValue('BLOCKADVERT_IMG_EXT', substr($file, strrpos($file, '.') + 1));
                }
            }

            return true;
        }

        $this->_errors[] = $this->getTranslator()->trans('This module needs to be hooked to a column, but your theme does not implement one', array(), 'Modules.Advertising.Admin');
        parent::uninstall();

        return false;
    }

    public function uninstall()
    {
        Configuration::deleteByName('BLOCKADVERT_LINK');
        Configuration::deleteByName('BLOCKADVERT_TITLE');
        Configuration::deleteByName('BLOCKADVERT_IMG_EXT');

        return (parent::uninstall());
    }

    /**
     * delete the contextual image (it is not allowed to delete the default image)
     *
     * @return void
     */
    private function _deleteCurrentImg()
    {
        // Delete the image file
        if ($this->adv_imgname != 'advertising' && file_exists(_PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'.'.Configuration::get('BLOCKADVERT_IMG_EXT'))) {
            unlink(_PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'.'.Configuration::get('BLOCKADVERT_IMG_EXT'));
        }

        // Update the extension to the global value or the shop group value if available
        Configuration::deleteFromContext('BLOCKADVERT_IMG_EXT');
        Configuration::updateValue('BLOCKADVERT_IMG_EXT', Configuration::get('BLOCKADVERT_IMG_EXT'));

        // Reset the properties of the module
        $this->initialize();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitDeleteImgConf')) {
            $this->_deleteCurrentImg();
        }

        $errors = '';
        if (Tools::isSubmit('submitAdvConf')) {
            if (isset($_FILES['adv_img']) && isset($_FILES['adv_img']['tmp_name']) && !empty($_FILES['adv_img']['tmp_name'])) {
                if ($error = ImageManager::validateUpload($_FILES['adv_img'], Tools::convertBytes(ini_get('upload_max_filesize')))) {
                    $errors .= $error;
                } else {
                    Configuration::updateValue('BLOCKADVERT_IMG_EXT', substr($_FILES['adv_img']['name'], strrpos($_FILES['adv_img']['name'], '.') + 1));

                    // Set the image name with a name contextual to the shop context
                    $this->adv_imgname = 'advertising';
                    if (Shop::getContext() == Shop::CONTEXT_GROUP) {
                        $this->adv_imgname = 'advertising-g'.(int)$this->context->shop->getContextShopGroupID();
                    } elseif (Shop::getContext() == Shop::CONTEXT_SHOP) {
                        $this->adv_imgname = 'advertising-s'.(int)$this->context->shop->getContextShopID();
                    }

                    // Copy the image in the module directory with its new name
                    if (!move_uploaded_file($_FILES['adv_img']['tmp_name'], _PS_MODULE_DIR_.$this->name.'/img/'.$this->adv_imgname.'.'.Configuration::get('BLOCKADVERT_IMG_EXT'))) {
                        $errors .= $this->getTranslator()->trans('File upload error.', array(), 'Modules.Advertising.Admin');
                    }
                }
            }

            // If the link is not set, then delete it in order to use the next default value (either the global value or the group value)
            if ($link = Tools::getValue('adv_link')) {
                Configuration::updateValue('BLOCKADVERT_LINK', $link);
            } elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP) {
                Configuration::deleteFromContext('BLOCKADVERT_LINK');
            }

            // If the title is not set, then delete it in order to use the next default value (either the global value or the group value)
            if ($title = Tools::getValue('adv_title')) {
                Configuration::updateValue('BLOCKADVERT_TITLE', $title);
            } elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP) {
                Configuration::deleteFromContext('BLOCKADVERT_TITLE');
            }

            if ($val = Tools::getValue('left_column')) {
                Configuration::updateValue('BLOCKADVERT_LEFT_COLUMN', $val);
            } elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP) {
                Configuration::deleteFromContext('BLOCKADVERT_LEFT_COLUMN');
            }

            if ($val = Tools::getValue('right_column')) {
                Configuration::updateValue('BLOCKADVERT_RIGHT_COLUMN', $val);
            } elseif (Shop::getContext() == Shop::CONTEXT_SHOP || Shop::getContext() == Shop::CONTEXT_GROUP) {
                Configuration::deleteFromContext('BLOCKADVERT_RIGHT_COLUMN');
            }

            // Reset the module properties
            $this->initialize();
            $this->_clearCache('ps_advertising');

            if (!$errors) {
                Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&conf=6');
            }
            echo $this->displayError($errors);
        }
    }

    /**
     * getContent used to display admin module form
     *
     * @return string content
     */
    public function getContent()
    {
        $this->postProcess();

        return $this->renderForm();
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->getTranslator()->trans('Configuration', array(), 'Admin.Global'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'file',
                        'label' => $this->getTranslator()->trans('Image for the advertisement', array(), 'Modules.Advertising.Admin'),
                        'name' => 'adv_img',
                        'desc' => $this->getTranslator()->trans('By default the image will appear in the left column. The recommended dimensions are 155 x 163px.', array(), 'Modules.Advertising.Admin'),
                        'thumb' => $this->context->link->protocol_content.$this->adv_img,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->getTranslator()->trans('Target link for the image', array(), 'Modules.Advertising.Admin'),
                        'name' => 'adv_link',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->getTranslator()->trans('Title of the target link', array(), 'Modules.Advertising.Admin'),
                        'name' => 'adv_title',
                        'desc' => $this->getTranslator()->trans('This title will be displayed when you mouse over the advertisement block in your shop.', array(), 'Modules.Advertising.Admin')
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->getTranslator()->trans('Display in the left column', array(), 'Modules.Advertising.Admin'),
                        'name' => 'left_column',
                        'is_bool' => true,
                        'values' => array(
                                array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->getTranslator()->trans('Enabled', array(), 'Admin.Global')
                                ),
                                array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->getTranslator()->trans('Disabled', array(), 'Admin.Global')
                                )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->getTranslator()->trans('Display in the right column', array(), 'Modules.Advertising.Admin'),
                        'name' => 'right_column',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->getTranslator()->trans('Enabled', array(), 'Admin.Global')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->getTranslator()->trans('Disabled', array(), 'Admin.Global')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->getTranslator()->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitAdvConf';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'adv_link' => Tools::getValue('adv_link', Configuration::get('BLOCKADVERT_LINK')),
            'adv_title' => Tools::getValue('adv_title', Configuration::get('BLOCKADVERT_TITLE')),
            'left_column' => Tools::getValue('left_column', Configuration::get('BLOCKADVERT_LEFT_COLUMN')),
            'right_column' => Tools::getValue('right_column', Configuration::get('BLOCKADVERT_RIGHT_COLUMN')),
        );
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return array(
            'image' => $this->context->link->protocol_content.$this->adv_img,
            'adv_link' => $this->adv_link,
            'adv_title' => $this->adv_title,
        );
    }

    public function renderWidget($hookName, array $configuration)
    {
        if (('displayLeftColumn' === $hookName && !Configuration::get('BLOCKADVERT_LEFT_COLUMN')) ||
            ('displayRightColumn' === $hookName && !Configuration::get('BLOCKADVERT_RIGHT_COLUMN'))) {
            return false;
        }

        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->fetch(
            'module:ps_advertising/ps_advertising.tpl',
            $this->getCacheId('ps_advertising')
        );
    }
}
