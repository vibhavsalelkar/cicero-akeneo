<?php

namespace Webkul\Magento2Bundle\Connector\Reader;

use Doctrine\Common\Persistence\ObjectRepository;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * attribute reader, basic
 */
class AttributeQuickReader extends \AbstractReader implements
    \ItemReaderInterface,
    \InitializableInterface,
    \StepExecutionAwareInterface
{
    /** @var ObjectRepository */
    protected $repository;

    /**
     * @param ObjectRepository $repository
     */
    public function __construct(ObjectRepository $repository, Magento2Connector $connectorService)
    {
        $this->repository = $repository;
        $this->connectorService = $connectorService;
    }

    /**
     * {@inheritdoc}
     */
    protected function getResults()
    {
        $mappings = $this->connectorService->getOtherMappings();
        $selectedAttrs = !empty($mappings['custom_fields']) ? $mappings['custom_fields'] : [];
        $attributes = $this->findAttributesByCodes($selectedAttrs);

        return new \ArrayIterator($attributes);
    }

    protected function findAttributesByCodes($codes)
    {
        return $this->repository->createQueryBuilder('a')
                ->andWhere('a.code in (:codes)')
                ->setParameter('codes', $codes)
                ->getQuery()->getResult();
    }
}
