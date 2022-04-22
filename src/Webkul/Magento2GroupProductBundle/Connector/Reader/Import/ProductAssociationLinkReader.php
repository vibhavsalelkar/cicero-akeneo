<?php

namespace Webkul\Magento2GroupProductBundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Connector\Reader\Import\ProductReader as BaseReader;
use Webkul\Magento2GroupProductBundle\Repository\JobDataMappingRepository;
use Webkul\Magento2GroupProductBundle\Traits\JobDataMappingTrait;

class ProductAssociationLinkReader extends BaseReader
{
    use JobDataMappingTrait;

    protected $magentoProductType = 'grouped';
    const WEBKUL_GROUP_PRODUCT_ASSOCIATION_LINK = 'webkul_magento2_groupped_product';
    const MAPPING_TYPE = 'associatedLinks';

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->jobDataMappingRepository = $this->em->getRepository('Magento2GroupProductBundle:JobDataMapping');
        parent:: initialize();
    }

    protected function getFormatedProductBySKU($productSKU, $parentCode = null)
    {
        $formatedData = parent::getFormatedProductBySKU($productSKU, $parentCode);
        $linkSKUs = [];
        $mapping = $this->jobDataMappingRepository->findOneBy(['productIdentifier' => $productSKU, 'mappingType' => self::MAPPING_TYPE, 'jobInstanceId' => $this->stepExecution->getJobExecution()->getId()]);
        if ($mapping) {
            $linkSKUs = $mapping->getExtras();
        }
        
        if (!empty($formatedData)) {
            $formatedData['associations'] = [
                self::WEBKUL_GROUP_PRODUCT_ASSOCIATION_LINK => [
                    "products" => $linkSKUs
                ]
            ];
        }

        for ($read = 1; $read < count($linkSKUs); $read++) {
            $this->stepExecution->incrementSummaryInfo('read');
        }

        return $formatedData;
    }
}
