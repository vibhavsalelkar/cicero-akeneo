<?php

/*
 * This file is part of the Akeneo PIM Enterprise Edition.
 */
namespace Webkul\Magento2BundleProductBundle\Provider\Form\PimEnterprise;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Webkul\Magento2Bundle\Services\Magento2Connector;
/**
 * Form provider for product
 */
class ProductFormProvider extends \BaseProductFormProviderEE
{
    /** @var AuthorizationCheckerInterface */
    protected $authorizationChecker;

    /** @var Magento2Connector */
    private $connectorService; 

    /**
     * @param AuthorizationCheckerInterface $authorizationChecker
     *
     * @param Magento2Connector $connectorService
     */
    public function __construct(AuthorizationCheckerInterface $authorizationChecker, Magento2Connector $connectorService)
    {
        parent::__construct($authorizationChecker);
        $this->connectorService = $connectorService;
    }

    /**
     * {@inheritdoc}
     */
    public function getForm($product): string
    {   
        $form = parent::getForm($product); 

        $mapping = $this->connectorService->getProductMapping($product->getIdentifier());
        foreach($product->getAssociations() as $associations) {
            if($associations->getAssociationType()->getCode() === 'webkul_magento2_groupped_product' && !empty(count($associations->getProducts())) || ($mapping && $mapping->getType() === 'grouped') ) {
                
                $form = 'pim-product-group-edit-form';
            }
        }

        if(($mapping && $mapping->getType() === 'bundle')) {
                
            $form = 'pim-product-bundle-edit-form';
        }

        return $form;
    }
}
