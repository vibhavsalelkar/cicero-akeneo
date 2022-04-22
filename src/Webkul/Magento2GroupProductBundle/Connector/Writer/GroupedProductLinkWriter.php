<?php

namespace Webkul\Magento2GroupProductBundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;

/**
 * Add Association products to magento2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class GroupedProductLinkWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = 'grouped_product';
    
    protected $writeCheck;

    public function __construct(\Doctrine\ORM\EntityManager $em, Magento2Connector $connectorService)
    {
        $this->em = $em;
        $this->connectorService = $connectorService;
    }

    /**
     * write products to magento2 Api
     */
    public function write(array $items)
    {
        $type = 'webkul_magento2_groupped_product';
        foreach ($items as $item) {
            $this->writeCheck = false;
            $counterAssociation = 0;
            if (!empty($item['associations'][$type]) && !empty($item['sku'])) {
                $counterAssociation = 1;
                $data = [];
                $notExist = [];
                
                foreach ($item['associations'][$type]['products'] as $product) {
                    if ($item['sku'] !== $product) {
                        $data[] =  array(
                            "sku" => $item['sku'],
                            "link_type" => "associated",
                            "linked_product_sku" => $product,
                            "linked_product_type" => "simple"
                        );
                    }
                    $counterAssociation++;
                }
                
                if (!empty($data)) {
                    foreach ($data as $association) {
                        $results = $this->linkAsociation($association);
                        if ($results === true) {
                            $this->writeCheck = true;
                        } else {
                            $notExist[] = $association;
                        }
                    }
                    
                    if (!empty($notExist)) {
                        $this->stepExecution->addWarning(
                            "SKU : " .
                            $notExist[0]['sku'] . " Some Grouped Products not linked due to Products does not exported to magento",
                            ['error' => true ],
                            new \DataInvalidItem(["Skipped Product " => json_encode($notExist)])
                        );
                    }
                } else {
                    $this->stepExecution->incrementSummaryInfo('read', -1);
                }
            }
            if ($this->writeCheck) {
                $this->stepExecution->incrementSummaryInfo('write');
            }
            if ($counterAssociation === 0) {
                $this->stepExecution->incrementSummaryInfo('read', -1);
            }
        }
    }


    protected function linkAsociation($item)
    {
        $linkData = array(
            "entity" => $item,
        );
        $url = $this->oauthClient->getApiUrlByEndpoint('addLinks');
        $url = str_replace('{sku}', urlencode($item['sku']), $url);
        $method = 'PUT';
        try {
            $this->oauthClient->fetch($url, json_encode($linkData), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            
            return $results;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    protected $associationTypes = ["products"];
}
