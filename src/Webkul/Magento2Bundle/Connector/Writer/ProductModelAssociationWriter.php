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
class ProductModelAssociationWriter extends BaseWriter implements \ItemWriterInterface
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
                    $alreadyLinkedAssociation = [];
                    if ($this->stepExecution->getJobParameters()->get('deleteExtraAssociation')) {
                        $alreadyLinkedAssociation = $this->getLinkAssociationSkuFromMagento($item['sku'], $association);
                    }
                    foreach ($this->associationTypes as $type) {
                        if (!empty($item['associations'][$key][$type])) {
                            foreach ($item['associations'][$key][$type] as $product) {
                                if ($item['sku'] !== $product) {
                                    if (!in_array($product, $alreadyLinkedAssociation)) {
                                        $data[] =  array(
                                            "sku" => $item['sku'],
                                            "link_type" => $association,
                                            "linked_product_sku" => $product,
                                            "linked_product_type" => ($type === "products") ? "simple" : 'configurable'
                                        );
                                        $pos = array_search($product, $alreadyLinkedAssociation);
                                        unset($alreadyLinkedAssociation[$pos]);
                                    } else {
                                        $pos = array_search($product, $alreadyLinkedAssociation);
                                        unset($alreadyLinkedAssociation[$pos]);
                                    }
                                }
                                $counterAssociation++;
                            }
                        }
                        if ($alreadyLinkedAssociation) {
                            foreach ($alreadyLinkedAssociation as $value) {
                                $resp = $this->deleteExtraPresentAssociations($value, $item['sku'], $association);
                                $this->stepExecution->incrementSummaryInfo('deleted');
                            }
                        }
                    }
                }
                if (!empty($notExist)) {
                    $this->stepExecution->addWarning(
                        "Some Association Product not linked due to Products does not exported to magento",
                        ['error' => true ],
                        new \DataInvalidItem(["Skipped Product " => json_encode($notExist), "Linked Product" => $data])
                    );
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

    public function getLinkAssociationSkuFromMagento($sku, $associationType)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getLinks');
        $url = str_replace('{sku}', urlencode($sku), $url);
        $url = str_replace('{type}', urlencode($associationType), $url);
        $method = 'GET';
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            $availableSku = [];
            if (!empty($results)) {
                foreach ($results as $value) {
                    $availableSku[] = $value['linked_product_sku'];
                }

                return $availableSku;
            }
            return $results;
        } catch (\Exception $e) {
            return false;
        }
    }
    public function deleteExtraPresentAssociations($association, $sku, $type)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('deleteLinks');
        $url = str_replace('{sku}', urlencode($sku), $url);
        $url = str_replace('{type}', urlencode($type), $url);
        $url = str_replace('{linkedProductSku}', urlencode($association), $url);
        $method = 'DELETE';
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            return $results;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function linkAsociation($item)
    {
        $linkData = array(
            "items" => $item,
        );
        $url = $this->oauthClient->getApiUrlByEndpoint('addLinks');
        $url = str_replace('{sku}', $item[0]['sku'], $url);
        $method = 'POST';
        try {
            $this->oauthClient->fetch($url, json_encode($linkData), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            
            return $results;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    protected $associationTypes = ["products", "group", "product_models"];
}
