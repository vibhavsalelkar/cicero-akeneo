<?php

namespace Webkul\Magento2Bundle\Datasource\Orm;

// use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\HydratorInterface;

use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface;
use Webkul\Magento2Bundle\Services\Magento2Connector;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$versionCompatibily = new AkeneoVersionsCompatibility();
$versionCompatibily->checkVersionAndCreateClassAliases();


/**
 * Hydrate results of Doctrine ORM query as array of ids
 *
 * @author    Webkul <support@webkul.com>
 *
 */
class CustomAttributeMappingHydrator implements \HydratorInterface
{
    const SECTION_CHILD_ATTRIBUTE_MAPPING = 'magento2_child_attribute_mapping';

    /**
     * @var Magento2Connector
     */
    protected $connectorService; 

    protected $customAttributesMapping;

    protected $otherMappingAttributes; 

    /**
     * @param Magento2Connector $connectorService
     */
    public function __construct(Magento2Connector $connectorService)
    {
        $this->connectorService = $connectorService;
    }
    protected $skipAttributesType = ['pim_catalog_identifier', 'pim_catalog_file', 'pim_catalog_image', 'pim_reference_data_multiselect', 'pim_reference_data_simpleselect'];
    protected $reservedAttributes = [
        'sku', 'name','weight', 'status', 'description', 'short_description', 'price', 'visibility', 'weight',
        'tax_class_id', 'quantity_and_stock_status', 'category_ids', 'tier_price', 'price_view', 'gift_message_available', 'website_ids'
    ];
    /**
     * {@inheritdoc}
     */
    public function hydrate($qb, array $options = [])
    {
        $customAttributesMapping = $this->getCustomAttributesMapping();
        $records = [];
        $qb->andWhere('a.type NOT IN (:skipAttributesType)');
        $qb->andWhere('a.code NOT IN (:skipExistingAttributes)');
        $qb->setParameter('skipAttributesType', $this->skipAttributesType);
        $qb->setParameter('skipExistingAttributes', array_merge($this->getAttributesMapping(), $this->reservedAttributes));
            
        
        foreach ($qb->getQuery()->execute() as $record) {
            // if (in_array($record[0]->getType(), arra$this->skipAttributesType)) {
            //     continue;
            // }

            // if (in_array($record[0]->getCode(), $this->getAttributesMapping() )) {
            //     continue;
            // }

            // if(in_array($record[0]->getCode(), $this->reservedAttributes)) {
            //     continue;
            // }

            $record['is_checked'] = false;
            $record['in_mapping'] = false;
            if(in_array($record[0]->getCode(), $customAttributesMapping)) {
                $record['is_checked'] = true;
                $record['in_mapping'] = true;
            }

            $records[] = new ResultRecord($record);
        } 

        return $records;
    } 

    public function getCustomAttributesMapping()
    {
        if(!$this->customAttributesMapping) {
            $this->customAttributesMapping = $this->connectorService->getOtherMappings();
        }

        return $this->customAttributesMapping['custom_fields'] ? $this->customAttributesMapping['custom_fields'] : [];     
    }

    public function getAttributesMapping()
    {
        if(!$this->otherMappingAttributes) {
            $this->otherMappingAttributes = [];
            $this->otherMappingAttributes = array_merge($this->otherMappingAttributes, array_values($this->connectorService->getAttributeMappings()));
            $this->otherMappingAttributes = array_merge($this->otherMappingAttributes, array_values($this->connectorService->getAttributeMappings(self::SECTION_CHILD_ATTRIBUTE_MAPPING)));

        }
    
        return array_unique($this->otherMappingAttributes);     
    }
   
}
