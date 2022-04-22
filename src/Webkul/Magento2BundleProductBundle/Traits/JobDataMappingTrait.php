<?php
namespace Webkul\Magento2BundleProductBundle\Traits;

use Webkul\Magento2BundleProductBundle\Entity\JobDataMapping;

/**
 * jobDataMappint trait use to add mapping and remove at the time of job execution
 */
trait JobDataMappingTrait
{
    protected function addJobDataMapping(string $productIdentifier, string $mappingType,array $extras = [])
    {
        if(isset($this->stepExecution) && $this->stepExecution->getJobExecution()->getId()) {
            $jobInstanceId = $this->stepExecution->getJobExecution()->getId();
        }

        $mapping = $this->getJobMappingByIdentifier($productIdentifier, $mappingType);
        
        if(!$mapping) {
            $mapping = new JobDataMapping();
        }

        $mapping->setProductIdentifier($productIdentifier);
        $mapping->setMappingType($mappingType);

        if(isset($jobInstanceId)) {
            $mapping->setJobInstanceId($jobInstanceId);
        }

        if($extras) {
            $mapping->setExtras($extras);
        }
        
        $this->em->persist($mapping);
        $this->em->flush();

        return $mapping;

    }

    protected function getJobMappingByIdentifier(string $productIdentifier, string $mappingType, string  $jobInstanceId = null) 
    {
        $params = [
            'productIdentifier' => $productIdentifier,
            'mappingType'   =>  $mappingType,
        ];

        if(!empty($jobInstanceId)) {
            $params['jobInstanceId'] = $jobInstanceId;
        }

        $mapping = isset($this->jobDataMappingRepository) ? $this->jobDataMappingRepository->findOneBy($params) : null;

        return $mapping;
    }
    
}