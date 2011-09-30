<?php
/*
* 2007-2011 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
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
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 7507 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class CompareControllerCore extends FrontController
{
	public $php_self = 'products-comparison';

	public function setMedia()
	{
		parent::setMedia();
		$this->addCSS(_THEME_CSS_DIR_.'/comparator.css');

		if (Configuration::get('PS_COMPARATOR_MAX_ITEM') > 0)
			$this->addJS(_THEME_JS_DIR_.'products-comparison.js');
	}

	/**
	 * Display ajax content (this function is called instead of classic display, in ajax mode)
	 */
	public function displayAjax()
	{
		//Add or remove product with Ajax
		if (Tools::getValue('id_product') && Tools::getValue('action'))
		{
			if (Tools::getValue('action') == 'add')
			{
				if (isset($this->context->customer->id))
				{
					if (CompareProduct::getCustomerNumberProducts($this->context->customer->id) < Configuration::get('PS_COMPARATOR_MAX_ITEM'))
						CompareProduct::addCustomerCompareProduct((int)$this->context->customer->id, (int)Tools::getValue('id_product'));
					else
						die('0');
				}
				else
				{
					if ((isset($this->context->customer->id_guest) && CompareProduct::getGuestNumberProducts($this->context->customer->id_guest) < Configuration::get('PS_COMPARATOR_MAX_ITEM')))
						CompareProduct::addGuestCompareProduct((int)$this->context->customer->id_guest, (int)Tools::getValue('id_product'));
					else
						die('0');
				}
			}
			else if (Tools::getValue('action') == 'remove')
			{
				if (isset($this->context->customer->id))
					CompareProduct::removeCustomerCompareProduct((int)$this->context->customer->id, (int)Tools::getValue('id_product'));
				else if (isset($this->context->customer->id_guest))
					CompareProduct::removeGuestCompareProduct((int)$this->context->customer->id_guest, (int)Tools::getValue('id_product'));
				else
					die('0');
			}
			else
				die('0');

			die('1');
		}
	}

	/**
	 * Assign template vars related to page content
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		//Clean compare product table
		CompareProduct::cleanCompareProducts('week');

		$hasProduct = false;

		if (!Configuration::get('PS_COMPARATOR_MAX_ITEM'))
				return Tools::redirect('index.php?controller=404');

		if ($product_list = Tools::getValue('compare_product_list') && ($postProducts = (isset($product_list) ? rtrim($product_list, '|') : '')))
			$ids = array_unique(explode('|', $postProducts));
		else if (isset($this->context->customer->id))
			$ids = CompareProduct::getCustomerCompareProducts($this->context->customer->id);
		else if (isset($this->context->customer->id_guest))
			$ids = CompareProduct::getGuestCompareProducts($this->context->customer->id_guest);
		else
			$ids = null;

		if ($ids)
		{
			if (count($ids) > 0)
			{
				if (count($ids) > Configuration::get('PS_COMPARATOR_MAX_ITEM'))
					$ids = array_slice($ids, 0, Configuration::get('PS_COMPARATOR_MAX_ITEM'));

				$listProducts = array();
				$listFeatures = array();

				foreach ($ids as $k => &$id)
				{
					$curProduct = new Product((int)$id, true, $this->context->language->id);
					if (!$curProduct->active || !$curProduct->isAssociatedToShop())
					{
						unset($ids[$k]);
						continue;
					}

					if (!$curProduct->active || !$curProduct->isAssociatedToShop())
					{
						unset($ids[$k]);
						continue;
					}

					if (!Validate::isLoadedObject($curProduct))
						continue;

					if (!$curProduct->active)
					{
						unset($ids[$k]);
						continue;
					}

					foreach ($curProduct->getFrontFeatures($this->context->language->id) as $feature)
						$listFeatures[$curProduct->id][$feature['id_feature']] = $feature['value'];

					$cover = Product::getCover((int)$id);

					$curProduct->id_image = Tools::htmlentitiesUTF8(Product::defineProductImage(array('id_image' => $cover['id_image'], 'id_product' => $id), $this->context->language->id));
					$curProduct->allow_oosp = Product::isAvailableWhenOutOfStock($curProduct->out_of_stock);
					$listProducts[] = $curProduct;
				}

				if (count($listProducts) > 0)
				{
					$width = 80 / count($listProducts);

					$hasProduct = true;
					$ordered_features = Feature::getFeaturesForComparison($ids, $this->context->language->id);
					$this->context->smarty->assign(array(
						'ordered_features' => $ordered_features,
						'product_features' => $listFeatures,
						'products' => $listProducts,
						'width' => $width,
						'homeSize' => Image::getSize('home')
					));
					$this->context->smarty->assign('HOOK_EXTRA_PRODUCT_COMPARISON', Module::hookExec('extraProductComparison', array('list_ids_product' => $ids)));
				}
			}
		}
		$this->context->smarty->assign('hasProduct', $hasProduct);

		$this->setTemplate(_PS_THEME_DIR_.'products-comparison.tpl');
	}
}

