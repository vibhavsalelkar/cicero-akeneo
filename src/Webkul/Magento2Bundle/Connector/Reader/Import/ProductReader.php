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
class ProductReader extends BaseProductReader
{
    const PRODUCT_TYPE = 'simple';
    
    const AKENEO_ENTITY_NAME = 'product';

    protected $magentoProductTypes = ['simple', 'virtual'];

    protected $product_attributes;

    protected $parent;

    protected function getProducts($currentPage)
    {
        /* not store-view wise */
        $url = $this->oauthClient->getApiUrlByEndpoint('product', 'all');
        $url = str_replace('{sku}', '', $url);
        $url = str_replace('?searchCriteria=', '?fields=items[sku],total_count&searchCriteria[filterGroups][0][filters][0][field]=type_id&searchCriteria[filterGroups][0][filters][0][value]=' . implode(',', $this->magentoProductTypes) . '&searchCriteria[filterGroups][0][filters][0][condition_type]=in' . '&searchCriteria[pageSize]='.self::PAGE_SIZE.'&searchCriteria[currentPage]=' . $currentPage, $url);
        
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
        $product = $this->getMagentoProduct($productSKU, $this->storeCode);
        
        return $this->getFormatedProduct($product);
    }

    protected function getMagentoProduct($productSKU, $storeCode)
    {
        //Check if only new products import setting is enabled the check products is exist in Akeneo or Not
        if ($this->onlyNewProducts) {
            if ($this->connectorService->checkExistProduct($productSKU, self::PRODUCT_TYPE)) {
                $this->stepExecution->incrementSummaryInfo('read');
                $this->stepExecution->incrementSummaryInfo('already_exist');
                return;
            }
        }
            
        $product = $this->getProductBySKU($productSKU, $storeCode);
        
        if (isset($product['error'])) {
            $this->stepExecution->addWarning("Error: to fetch product from API,  SKU :  $productSKU ", [], new \DataInvalidItem(['SKU' => $productSKU, "error" => $product]));
            $product = [];
        }

        return $product;
    }

    protected function getFormatedProduct($product)
    {
        $result = [];
        if (!empty($product)) {
            if (empty($product['sku'])) {
                return;
            }

            $productSKU = $product['sku'];
            
            if (!empty($product['type_id']) && !in_array($product['type_id'], $this->magentoProductTypes)) {
                $this->stepExecution->addWarning("Error: The product type should be either Virtual or Simple,  SKU :  $productSKU ", [], new \DataInvalidItem(['SKU' => $productSKU, "error" => $product['type_id']]));
                return;
            }
            if (isset($product['attribute_set_id'])) {
                $this->family  = $this->connectorService->findCodeByExternalId($product['attribute_set_id'], 'family');
                if (empty($this->family)) {
                    $this->family = $this->fetchFamily($product['attribute_set_id']);
                }
            }

            if (empty($this->family)) {
                $this->stepExecution->incrementSummaryInfo('skipped');
                $this->stepExecution->addWarning('family not found', [], new \DataInvalidItem(['family' => $this->family ]));
                return;
            }

            $externalId = $product['id'] ?? null;
            $mapping = $this->getMappingByExternalId($externalId);
            
            $result = [
                'categories'     => !empty($product['custom_attributes']) ? $this->getCategories($product['custom_attributes']) : [],
                'enabled'        => !empty($product['status']) ? $this->getStatus($product['status']) : false ,
                'family'         => $this->family,
                'groups'         => [],
                'identifier'     => !empty($mapping) ? $mapping->getCode() : $productSKU,
            ];

            $this->parent = null;
            $this->parent = $this->findParentCode($product['id']);

            if (!empty($this->parent)) {
                $result['parent'] =  $this->parent;
            }
            $result['values'] = $this->getValues($product);
            // if sku not present
            
            $result['values']['sku'] = [
                        array(
                            'locale' => null,
                            'scope' => null,
                            'data' => $result['identifier']
                        )
                    ];

            // Add to Mapping in Database
            $externalId = !empty($product['id']) ? $product['id'] : null;
            $relatedId  = $this->family;
            if ($productSKU && $externalId) {
                $mapping =  $this->addMappingByExternalId($productSKU, $externalId, $relatedId, $this::AKENEO_ENTITY_NAME, $productSKU);
                if ($product['type_id'] !== 'simple') {
                    $this->connectorService->createProductMapping($productSKU, $product['type_id']);
                }
            }
        }
        
        return $result;
    }

    protected function getValues($product)
    {
        $result = [];

        if (empty($this->parent)) {
            //simple product
            $result = parent::getValues($product);
        } else {
            $productLocalesData = [];

            //Import for all storeview locales data
            $jobLocales = $this->stepExecution->getJobParameters()->get('filters')['structure']['locales'];
            foreach ($this->storeMapping as $storeCode => $storeData) {
                if (is_array($this->locales) && in_array($storeData['locale'], $this->locales)
                    && $storeData['locale'] !== $this->defaultLocale
                    && !array_key_exists($storeData['locale'], array_keys($productLocalesData))) {
                    $this->locale = $storeData['locale'];
                    if (in_array($storeData['locale'], $jobLocales)) {
                        $productLocalesData[$storeData['locale']] = $this->getProductBySKU($product['sku'], $storeCode);
                    }
                }
            }

            //variant product
            $attributeMappings = $this->connectorService->getSettings('magento2_child_attribute_mapping');
            
            if (isset($this->product_attributes)) {
                foreach ($this->product_attributes as $product_attr) {
                    if (!in_array($product_attr, array_keys($attributeMappings))) {
                        $attributeMappings[$product_attr] = $this->connectorService->matchAttributeCodeInDb($product_attr) ? : $product_attr;
                    }
                }
            }
            
            foreach ($attributeMappings as $magentoField => $akeneoField) {
                $value = [];

                if ($magentoField === 'quantity') {
                    $magentoField = 'qty';
                }
                if ($magentoField === 'quantity_and_stock_status') {
                    $magentoField = 'is_in_stock';
                }

                //Default Store Value Formate
                $this->locale = $this->defaultLocale;
                $formateValue = $this->getFormateValue($product, $magentoField, $akeneoField);

                if (!empty($formateValue)) {
                    $value[] = $formateValue;
                }

                $results = $this->connectorService->getAttributeTypeLocaleAndScope($akeneoField);
                $localizable = isset($results[0]['localizable']) ? $results[0]['localizable'] : null;
                $scopable = isset($results[0]['scopable']) ? $results[0]['scopable'] : null ;

                //All Store View Data
                if (!empty($localizable) && !empty($scopable)) {
                    foreach ($productLocalesData as $locale => $data) {
                        $this->locale = $locale;
                        $formateValue = $this->getFormateValue($data, $magentoField, $akeneoField);
    
                        if (!empty($formateValue)) {
                            $value[] = $formateValue;
                        }
                    }
                    
                    if (!empty($value)) {
                        $result[$akeneoField] = $value;
                    }
                } else {
                    if (!empty($value)) {
                        $result[$akeneoField] = $value;
                    }
                }
            }
            
            $mapping = $this->getMappingByCode($this->parent, 'product');
            if ($mapping) {
                $familyCode = $mapping->getRelatedId();
                $familyVariant = $this->connectorService->getFamilyVariantByIdentifier($familyCode);
                if (!empty($familyVariant)) {
                    $counter = 0;
                    $imageArrCodes = [];
                    foreach ($familyVariant->getAttributes() as $attribute) {
                        if (in_array($attribute->getType(), ['pim_catalog_image'])) {
                            $code = $attribute->getCode();
                            if (!empty($product['media_gallery_entries'][$counter])) {
                                $imageArr = $product['media_gallery_entries'][$counter++];
                                
                                if ($imageArr['media_type'] === 'image') {
                                    $imgUrl = $this->credentials['hostName'] . self::MAGENTO_PRODUCT_MEDIA_DIR . $imageArr['file'];
                                    $results = $this->connectorService->getAttributeTypeLocaleAndScope($code);
                                    $localizable = isset($results[0]['localizable']) ? $results[0]['localizable'] : null;
                                    $scopable = isset($results[0]['scopable']) ? $results[0]['scopable'] : null ;
                                    $check = $this->connectorService->checkExistProduct($product['sku'], $product['type_id']);
                                    $imgUrlData = $this->imageStorer($imgUrl);
                                    if (!empty($imgUrlData)) {
                                        $result[$code] = [
                                            array(
                                                'locale' => $localizable ? $this->defaultLocale : null,
                                                'scope' => $scopable ? $this->scope : null,
                                                'data' => $imgUrlData, //image url
                                            )
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $result;
    }
       
    protected function getStatus($status)
    {
        if ($status == 1) {
            return true;
        } else {
            return false;
        }
    }

    protected function findParentCode($productId)
    {
        $mappingResults = $this->connectorService->getMappingByEntity('product_links', $this->stepExecution->getJobExecution()->getId());
        $this->product_attributes = null;
        if ($mappingResults) {
            foreach ($mappingResults as $mappingResult) {
                if (!empty($mappingResult['extras'])) {
                    $relatedIds = json_decode($mappingResult['extras'], true);
                    $product_links = !empty($relatedIds['configurable_product_links']) ? $relatedIds['configurable_product_links'] : [] ;
                    $this->product_attributes = !empty($relatedIds['configurable_product_options']) ? $relatedIds['configurable_product_options'] : [] ;
                    if (in_array($productId, $product_links)) {
                        return $mappingResult['code'];
                    }
                }
            }
        }
        
        return null;
    }

    protected function fetchFamily($attributeSetId)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('updateAttributeSet', $this->storeCode);
        $url = str_replace('{attributeSetId}', $attributeSetId, $url);
        $method = 'GET';
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
        
            if ($results) {
                return !empty($results['attribute_set_name']) ? $results['attribute_set_name'] : null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
