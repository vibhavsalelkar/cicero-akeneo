<?php

declare(strict_types=1);

namespace Webkul\Magento2GroupProductBundle\Normalizer;

/**
 * Product model normalizer for datagrid
 */
class DataGridProductModelNormalizer extends \ProductModelNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize($productModel, $format = null, array $context = [])
    {
        $data = parent::normalize($productModel, $format, $context);
      
        //Added
        $data['complete_group_product'] = null;
        $data['complete_bundle_product'] = null;

        return $data;
    }
}
