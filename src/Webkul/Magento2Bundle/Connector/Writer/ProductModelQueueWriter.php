<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;

/**
 * Add products to magento2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class ProductModelQueueWriter extends ProductQueueWriter
{
    const AKENEO_ENTITY_NAME = 'product';
    
    /**
     * write products to magento2 rabbitMQ
     */
    public function write(array $items)
    {
        $jobExecution = $this->stepExecution->getJobExecution();
        if (!$this->oauthClient) {
            $this->stepExecution->addWarning('invalid oauth client', [], new \DataInvalidItem());
            return;
        }

        foreach ($items as $item) {
            if (count($item) == 2) {
                $url = $item[0];
                $data = json_decode($item[1], true);

                if ($data[self::AKENEO_ENTITY_NAME]['type_id'] == 'configurable') {
                    preg_match("#(/rest/)(.*?)(/async/)#", $url, $match);
                    if (isset($match[2])) {
                        $storeView = $match[2];
                    } else {
                        $storeView = '';
                    }
                    $data = $this->checkProductAndModifyData($data, $storeView);
                    
                    $sku = $data[self::AKENEO_ENTITY_NAME]['sku'];
                    $data[self::AKENEO_ENTITY_NAME]['extension_attributes']['configurable_product_links'] = array_merge(
                        $data[self::AKENEO_ENTITY_NAME]['extension_attributes']['configurable_product_links'] ?? [],
                        $jobExecution->productsModelLinks[$sku]
                    );

                    $this->oauthClient->fetch($url, is_array($data) ? json_encode($data) : $data, 'POST', $this->jsonHeaders);
                    $this->modifyProductUpdatedOnMagentoField($sku);
                }
            }
        }
    }

    protected function modifyProductUpdatedOnMagentoField($sku)
    {
        $model = $this->connectorService->findModelByIdentifier($sku);
        $attribute = $this->connectorService->findAttributeByIdentifier('updatedOnMagento');
        if ($model && $attribute) {
            $setter = $this->connectorService->getSetterByAttributeCode('updatedOnMagento');
            if (null === $setter) {
                return;
            }

            if ($setter instanceof \AttributeSetterInterface) {
                $setter->setAttributeData($model, $attribute, date("Y-m-d H:i:s"), ['locale' => null, 'scope' => null ]);
            }
            $this->connectorService->saveProductModel($model);
        }
    }
}
