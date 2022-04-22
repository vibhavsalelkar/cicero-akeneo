<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Add Association products to magento2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class ProductAssociationWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = 'association';
    protected $associations;
    protected $writeCheck;

    public function __construct(\Doctrine\ORM\EntityManager $em, Magento2Connector $connectorService)
    {
        $this->em = $em;
        $this->connectorService = $connectorService;
        $this->associations = $this->connectorService->getSettings('magento2_association_mapping');
    }

    /**
     * write products to magento2 Api
     */
    public function write(array $items)
    {
        foreach ($items as $item) {
            $this->writeCheck = false;
            $counterAssociation = 0;
            
            if (!empty($item['associations']) && !empty($item['sku'])) {
                $counterAssociation = 1;
                $data = [];
                $notExist = [];
                foreach ($this->associations as $association => $key) {
                    foreach ($this->associationTypes as $type) {
                        if (!empty($item['associations'][$key][$type])) {
                            foreach ($item['associations'][$key][$type] as $product) {
                                if ($item['sku'] !== $product) {
                                    $data[] =  array(
                                        "sku" => $item['sku'],
                                        "link_type" => $association,
                                        "linked_product_sku" => $product,
                                        "linked_product_type" => $type
                                    );
                                }
                                $counterAssociation++;
                            }
                        }
                    }
                }
                

                if (!empty($data)) {
                    $results = $this->linkAsociation($data);
                    $linkAssociation = [];
                    if (!$results) {
                        foreach ($data as $association) {
                            if (array_key_exists($association["link_type"], $linkAssociation)) {
                                $linkAssociation[$association["link_type"]][] = $association;
                                $results = $this->linkAsociation($linkAssociation[$association["link_type"]]);
                                end($linkAssociation[$association["link_type"]]);
                                unset($linkAssociation[ $association["link_type"]][ key($linkAssociation[$association["link_type"]]) ]);
                            } else {
                                $results = $this->linkAsociation([$association]);
                            }
                            if ($results === true) {
                                $linkAssociation[$association["link_type"]][] = $association;
                            } else {
                                $notExist[] = $association;
                            }
                        }
                    }
                    if ($results) {
                        $this->writeCheck = true;
                    }
                    if (!empty($notExist)) {
                        $this->stepExecution->addWarning(
                            "SKU : " .
                            $notExist[0]['sku'] . " Some Association Products not linked due to Products does not exported to magento",
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
            "items" => $item,
        );
        $url = $this->oauthClient->getApiUrlByEndpoint('addLinks');
        $url = str_replace('{sku}', urlencode($item[0]['sku']), $url);
        $method = 'POST';
        try {
            $this->oauthClient->fetch($url, json_encode($linkData), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            
            return $results;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    protected $associationTypes = ["products", "groups", "product_models"];
}
