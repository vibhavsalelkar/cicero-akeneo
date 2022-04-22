<?php

namespace Webkul\Magento2Bundle\Traits;

use Webkul\Magento2Bundle\Entity\DataMapping;

/**
* step execution trait used for filtering and so
*/
trait DataMappingTrait
{
    protected function getMappingByCode($code, $entityName = self::AKENEO_ENTITY_NAME, $storeViewWise = true, $jobInstanceId = null)
    {
        $params = [
            'code'       => $code,
            'entityType' => $entityName ,
            'apiUrl'     => $this->getApiUrl()
        ];
        if (!empty($jobInstanceId)) {
            $params['jobInstanceId'] = $jobInstanceId;
        }
        if ($storeViewWise && !empty($this->storeViewCode) && !in_array(self::AKENEO_ENTITY_NAME, [ 'option', 'family', 'product', 'category', 'attribute', 'group' ])) {
            $params['storeViewCode'] = $this->storeViewCode;
        }
        
        $mapping = isset($this->mappingRepository) ? $this->mappingRepository->findOneBy($params) : null;

        return $mapping;
    }

    /**
     * return the data mapping By magentoCode like sku,
     *
     * @var string $magentoCode
     * @var string $entityName
     * @var bool $storeViewWise
     * @var $jobInstanceId
     */
    protected function getMappingByMagentoCode($magentoCode, $entityName = self::AKENEO_ENTITY_NAME, $storeViewWise = true, $jobInstanceId = null)
    {
        $params = [
            'magentoCode'=> $magentoCode,
            'entityType' => $entityName ,
            'apiUrl'     => $this->getApiUrl()
        ];
        if (!empty($jobInstanceId)) {
            $params['jobInstanceId'] = $jobInstanceId;
        }
        if ($storeViewWise && !empty($this->storeViewCode) && !in_array(self::AKENEO_ENTITY_NAME, [ 'option', 'family', 'product', 'category', 'attribute', 'group' ])) {
            $params['storeViewCode'] = $this->storeViewCode;
        }
        
        $mapping = isset($this->mappingRepository) ? $this->mappingRepository->findOneBy($params) : null;

        return $mapping;
    }

    protected function getMappingByExternalId($externalId, $entityName = self::AKENEO_ENTITY_NAME, $relatedId = null, $storeViewWise = true)
    {
        $params = [
            'externalId' => $externalId,
            'entityType' => $entityName,
            'apiUrl'     => $this->getApiUrl()
        ];
        if ($relatedId) {
            $params['relatedId'] = $relatedId;
        }
        if ($storeViewWise && !empty($this->storeViewCode) && !in_array(self::AKENEO_ENTITY_NAME, [ 'option', 'family', 'product', 'category', 'attribute', 'group' ])) {
            $params['storeViewCode'] = $this->storeViewCode;
        }
        
        $mapping = isset($this->mappingRepository) ? $this->mappingRepository->findOneBy($params) : null;

        return $mapping;
    }

    protected function addMappingByCode($code, $externalId, $relatedId = null, $entityName = self::AKENEO_ENTITY_NAME, $extras = null, $magentoCode = null)
    {
        if ($code) {
            $mapping = $this->getMappingByCode($code, $entityName);
          
            if (!$mapping) {
                $mapping =  new DataMapping();
            }
            
            $mapping->setCode($code);
            $mapping->setEntityType($entityName);
            $mapping->setExternalId($externalId);
            $mapping->setRelatedId($relatedId);

            $magentoCode = $magentoCode ?? $code;
            $mapping->setMagentoCode($magentoCode);
            
            if (isset($this->stepExecution) && $this->stepExecution->getJobExecution()->getId()) {
                $mapping->setJobInstanceId($this->stepExecution->getJobExecution()->getId());
            }
            if (!empty($this->storeViewCode) && !in_array(self::AKENEO_ENTITY_NAME, ['option'])) {
                $mapping->setStoreViewCode($this->storeViewCode);
            }
            if ($this->getApiUrl()) {
                $mapping->setApiUrl($this->getApiUrl());
            }
            if ($extras) {
                $mapping->setExtras($extras);
            }
            
            $this->em->persist($mapping);
            $this->em->flush();
            return $mapping;
        }
    }

    protected function addMappingByExternalId($code, $externalId, $relatedId = null, $entityName = self::AKENEO_ENTITY_NAME, $magentoCode = null)
    {
        if ($code) {
            $mapping = $this->getMappingByExternalId($externalId, $entityName, $relatedId);
             
            if (!$mapping) {
                $mapping = $this->getMappingByCode($code, $entityName);
                if (!$mapping) {
                    $mapping =  new DataMapping();
                }
            }
            $mapping->setCode($code);
            $mapping->setEntityType($entityName);
            $mapping->setExternalId($externalId);
            $mapping->setRelatedId($relatedId);

            if ($magentoCode) {
                $mapping->setMagentoCode($magentoCode);
            }
            if (isset($this->stepExecution) && $this->stepExecution->getJobExecution()->getId()) {
                $mapping->setJobInstanceId($this->stepExecution->getJobExecution()->getId());
            }
            if (!empty($this->storeViewCode) && !in_array(self::AKENEO_ENTITY_NAME, ['option'])) {
                $mapping->setStoreViewCode($this->storeViewCode);
            }
            if ($this->getApiUrl()) {
                $mapping->setApiUrl($this->getApiUrl());
            }
            
            $this->em->persist($mapping);
            $this->em->flush();

            return $mapping;
        }
    }

    protected function updateMappingByCode($code, $externalId, $relatedId, $entityName = self::AKENEO_ENTITY_NAME)
    {
        if ($code) {
            $mapping = $this->getMappingByCode($code, $entityName);
            if (!$mapping) {
                $mapping =  new DataMapping();
            }
            $mapping->setCode($code);
            $mapping->setEntityType($entityName);
            if (isset($this->stepExecution) && $this->stepExecution->getJobExecution()->getId()) {
                $mapping->setJobInstanceId($this->stepExecution->getJobExecution()->getId());
            }
            $mapping->setExternalId($externalId);
            $mapping->setRelatedId($relatedId);
            $mapping->setStoreViewCode($this->storeViewCode);

            if ($this->getApiUrl()) {
                $mapping->setApiUrl($this->getApiUrl());
            }
            $this->em->persist($mapping);
            $this->em->flush();
            
            return $mapping;
        }
    }

    protected function deleteMapping($mapping)
    {
        if ($mapping && $mapping instanceof DataMapping) {
            $this->em->remove($mapping);
            $this->em->flush();
        }

        return null;
    }

    

    private function getApiUrl()
    {
        return str_replace('https://', 'http://', $this->getHostName());
    }

    public function getCategories($productCustomAttributes)
    {
        $categories = [];
        
        foreach ($productCustomAttributes as $attribute) {
            if ($attribute['attribute_code'] === "category_ids") {
                $values = !empty($attribute['value']) ? $attribute['value'] : [];
                foreach ($values as $externalId) {
                    $categories[] = $this->connectorService->findCodeByExternalId($externalId, 'category');
                }
            }
        }
        
        return array_filter($categories);
    }
}
