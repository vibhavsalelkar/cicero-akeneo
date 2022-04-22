<?php

namespace Webkul\Magento2Bundle\Datasource;

// use Pim\Bundle\DataGridBundle\Datasource\Datasource;
use Webkul\Magento2Bundle\Repository\DataMappingRepository;
// use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\HydratorInterface;
use Webkul\Magento2Bundle\Datasource\Orm\CustomObjectIdHydrator;
use Oro\Bundle\PimDataGridBundle\Datasource\DatasourceInterface;
use Oro\Bundle\PimDataGridBundle\Datasource\ParameterizableInterface;
use Oro\Bundle\PimDataGridBundle\Datasource\Datasource;

/**
 * Export Mapping datasource
 *
 * @author    Webkul <support@webkul.com>
 *
 */
class DataMappingDatasource extends Datasource implements DatasourceInterface, ParameterizableInterface
{
    /** @var DataMappingRepository */
    protected $repository;

    /** @var CustomObjectIdHydrator */
    protected $hydrator;

    /** @var array */
    protected $parameters = [];

    /**
     * @param DataMappingRepository $om
     * @param HydratorInterface          $hydrator
     */
    public function __construct(OdooExportMappingRepository $repository, \HydratorInterface $hydrator)
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
    }

    /**
     * @param string $method the query builder creation method
     * @param array  $config the query builder creation config
     *
     * @return Datasource
     */
    protected function initializeQueryBuilder($method, array $config = [])
    {
        $this->qb = $this->repository->$method('wdm');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getResults()
    {
        return $this->hydrator->hydrate($this->qb);
    }

    /**
     * {@inheritdoc}
     */
    public function getMassActionRepository()
    {
        return $this->massRepository;
    }
}
