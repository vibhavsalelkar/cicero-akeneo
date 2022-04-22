<?php

namespace Webkul\Magento2Bundle\Connector\Reader;

use Doctrine\Common\Persistence\ObjectRepository;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * attribute option reader
 */
class AttributeOptionReader extends \AbstractReader implements
    \ItemReaderInterface,
    \InitializableInterface,
    \StepExecutionAwareInterface
{
    /** @var ObjectRepository */
    protected $repository;

    protected $connectorService;

    /**
     * @param ObjectRepository $repository
     */
    public function __construct(
        ObjectRepository $repository,
        Magento2Connector $connectorService
    ) {
        $this->repository = $repository;
        $this->connectorService = $connectorService;
    }

    /**
     * {@inheritdoc}
     */
    protected function getResults()
    {
        $mappings = $this->connectorService->getOtherMappings();
        $attrMappings = $this->connectorService->getAttributeMappings();
        if (isset($attrMappings['website_ids'])) {
            unset($attrMappings['website_ids']);
        }
        $mappedStandard = array_values($attrMappings);
        $mappedCustom = !empty($mappings['custom_fields']) ? $mappings['custom_fields'] : [];
        if (isset($this->stepExecution->getJobParameters()->get('filters')['attributeExport'])) {
            $mappedCustom = $this->stepExecution->getJobParameters()->get('filters')['attributeExport'];
        }
        
        $mappedAll = array_merge($mappedCustom, $mappedStandard);
        $selectAttrs = $this->connectorService->getSelectTypeAttributes();
        $selectedAttrs = array_intersect($selectAttrs, $mappedAll);
        $attributesOptions = $this->findOptionsByAttributesCodes($selectedAttrs);

        return new \ArrayIterator($attributesOptions);
    }

    protected function findOptionsByAttributesCodes($codes)
    {
        return $this->repository->createQueryBuilder('o')
                ->leftJoin('o.attribute', 'a')
                ->andWhere('a.code in (:codes)')
                ->setParameter('codes', $codes)
                ->getQuery()->getResult();
    }
}
