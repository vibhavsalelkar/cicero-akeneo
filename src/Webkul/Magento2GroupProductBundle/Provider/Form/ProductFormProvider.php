<?php

namespace Webkul\Magento2GroupProductBundle\Provider\Form;

use Symfony\Component\Form\Form;
use Webkul\Magento2Bundle\Services\Magento2Connector;

/**
 * Form provider for product
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductFormProvider extends \BaseProductFormProvider implements \FormProviderInterface
{
    /** @var Magento2Connector */
    private $connectorService;


    /**
     * @param Magento2Connector $connectorService
     */
    public function __construct(Magento2Connector $connectorService)
    {
        $this->connectorService = $connectorService;
    }

    /**
     * {@inheritdoc}
     */
    public function getForm($product): string
    {
        $mapping = $this->connectorService->getProductMapping($product->getIdentifier());
        $form = 'pim-product-edit-form';

        foreach ($product->getAssociations() as $associations) {
            if ($associations->getAssociationType()->getCode() === 'webkul_magento2_groupped_product' && !empty(count($associations->getProducts())) || ($mapping && $mapping->getType() === 'grouped')) {
                $form = 'pim-product-group-edit-form';
            }
        }

        if (($mapping && $mapping->getType() === 'bundle')) {
            $form = 'pim-product-bundle-edit-form';
        }

        return $form;
    }
}
