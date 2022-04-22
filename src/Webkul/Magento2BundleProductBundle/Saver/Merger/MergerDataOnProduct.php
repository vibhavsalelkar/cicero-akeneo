<?php

namespace Webkul\Magento2BundleProductBundle\Saver\Merger;

use Akeneo\Pim\Permission\Component\Merger\MergeDataOnProduct as ParentMergeDataOnProduct;
use Akeneo\Pim\Enrichment\Component\Product\EntityWithFamilyVariant\AddParent;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyVariantInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Repository\ProductModelRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Value\ScalarValue;
use Akeneo\Pim\Permission\Component\NotGrantedDataMergerInterface;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException;
use Doctrine\Common\Util\ClassUtils;


class MergerDataOnProduct extends ParentMergeDataOnProduct
{
   /**
     * {@inheritdoc}
     */
    public function merge($filteredProduct, $fullProduct = null)
    {
        $fullProduct = parent::merge($filteredProduct, $fullProduct);
      
        $fullProduct->setBundleOptions($filteredProduct->getBundleOptions());

        return $fullProduct;
    }
 }
