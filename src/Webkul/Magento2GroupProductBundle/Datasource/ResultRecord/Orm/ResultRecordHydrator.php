<?php

namespace Webkul\Magento2GroupProductBundle\Datasource\ResultRecord\Orm;

use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;

$obj = new \Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility();
$obj->checkVersionAndCreateClassAliases();
/**
 * {@inheritdoc}
 */
class ResultRecordHydrator implements \HydratorInterface
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($qb, array $options = [])
    {
        
        $records = [];
        foreach ($qb->getQuery()->execute() as $record) {
            if (is_array($record) && reset($record) instanceof \Pim\Bundle\CatalogBundle\Entity\AssociationType) {
                $code = reset($record)->getCode();
                if ($code === 'webkul_magento2_groupped_product') {
                    continue;
                }
            }

            $records[] = new ResultRecord($record);
        }
        
        return $records;
    }
}
