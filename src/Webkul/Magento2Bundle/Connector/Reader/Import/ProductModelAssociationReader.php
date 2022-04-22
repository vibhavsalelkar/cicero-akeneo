<?php

namespace Webkul\Magento2Bundle\Connector\Reader\Import;

use Symfony\Component\HttpFoundation\Request;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Connector\Reader\Import\BaseProductReader;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;

/**
 * import products_model from Magento 2
 *
 * @author    webkul <support@webkul.com>
 * @copyright 2010-18 Webkul (http://store.webkul.com/license.html)
 */
class ProductModelAssociationReader extends BaseProductReader
{
    const PRODUCT_TYPE = 'simple';
    
    const AKENEO_ENTITY_NAME = 'product';

    protected $product_attributes;

    protected $parent;

    protected function getProducts($currentPage)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('product', $this->storeCode);
        $url = str_replace('{sku}', '', $url);
        $url = strstr($url, '?', true) . '?fields=items[sku],total_count&searchCriteria[filterGroups][0][filters][0][field]=type_id&searchCriteria[filterGroups][0][filters][0][value]=configurable&searchCriteria[pageSize]='.self::PAGE_SIZE.'&searchCriteria[currentPage]=' . $currentPage;
        $method = 'GET';
        
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);

            if (!empty($results['total_count']) || !empty($this->totalProducts)) {
                if (empty($this->totalProducts)) {
                    $this->totalProducts = $results['total_count'];
                }
                if ($currentPage * self::PAGE_SIZE <= $this->totalProducts) {
                    return $results;
                } else {
                    $restPage = (($currentPage * self::PAGE_SIZE) - $this->totalProducts);
                    if ($restPage && $restPage <  self::PAGE_SIZE) {
                        return $results;
                    } else {
                        return [];
                    }
                }
            }
        } catch (\Exception $e) {
            $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
            $responseInfo = $this->oauthClient->getLastResponseInfo();
            foreach (array_keys($responseInfo) as $key) {
                if (trim($key) == 'http_code') {
                    $lastResponse['http_code'] = $responseInfo[$key];
                    break;
                }
            }
            $error = ['error' => $lastResponse ];
            $this->stepExecution->addWarning('Error: to fetch products from API ', [], new \DataInvalidItem(["API request URl" => $url, "error" => $error, "debug_line" => __LINE__]));
            
            return $error;
        }
        
        return [];
    }

    protected function formatData($products, $parentCode = null)
    {
        $results = [];
       
        foreach ($products as $product) {
            if (empty($product['sku'])) {
                continue;
            }
            $formatedData = $this->getFormatedProductBySKU($product['sku'], $parentCode);
            if (empty($formatedData)) {
                continue;
            }

            $results[] = $formatedData;
        }

        return $results;
    }

    protected function getFormatedProductBySKU($productSKU, $parentCode = null)
    {
        $product = $this->getMagentoProduct($productSKU);
        
        return $product;
    }

    protected function getMagentoProduct($productSKU)
    {
        //Check if only new products import setting is enabled the check products is exist in Akeneo or Not
        if ($this->onlyNewProducts) {
            if ($this->connectorService->checkExistProduct($productSKU, self::PRODUCT_TYPE)) {
                $this->stepExecution->incrementSummaryInfo('read');
                $this->stepExecution->incrementSummaryInfo('already_exist');
                return;
            }
        }
            
        $product = $this->getProductAssociationBySKU($productSKU);
        
        if (isset($product['error'])) {
            $this->stepExecution->addWarning("Error: to fetch product from API,  SKU :  $productSKU ", [], new \DataInvalidItem(['SKU' => $productSKU, "error" => $product]));
            $product = [];
        }

        return $product;
    }

    protected function getProductAssociationBySKU($productSKU)
    {
        $fetchedAssociation = [];
        $fetchedAssociation['code'] = $productSKU;
        $fetchedAssociation['values'] = [];
        $fetchedAssociation['associations'] = [];
        $associations = $this->connectorService->getSettings('magento2_association_mapping');
        foreach ($associations as $key => $assocValue) {
            if($assocValue != 'Select Association') {                        
                $url = $this->oauthClient->getApiUrlByEndpoint('getLinks');
                $url = str_replace('{sku}', $productSKU, $url);
                $url = str_replace('{type}', $key, $url);                
                $method = 'GET';
                try {
                    $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
                    $results = json_decode($this->oauthClient->getLastResponse(), true);
                    $productAssociation = [];
                    $productModelAssociation = [];
                    foreach ($results as $value) {
                        $fetchedAssociation['associations'][$assocValue] = [];
                        if($value['linked_product_type'] == 'simple') {
                            $productAssociation[] = $value['linked_product_sku'];
                        }
                        if($value['linked_product_type'] == 'configurable') {
                            $productModelAssociation[] = $value['linked_product_sku'];
                        }
                    }
                    
                    $fetchedAssociation['associations'][$assocValue]['products'] = $productAssociation;
                    $fetchedAssociation['associations'][$assocValue]['product_models'] = $productModelAssociation;
                } catch (\Exception $e) {
                
                }
            }
        }

        return $fetchedAssociation;
    }
}
