<?php

namespace Webkul\ImageGalleryBundle\Datasource\Orm;

class GroupDatasource extends \Datasource
{
    protected $repository;

    protected $hydrator;

    /** @var array */
    protected $parameters = [];

    public function __construct($repository, \HydratorInterface $hydrator)
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
        $this->qb = $this->repository->$method('wem');
        
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getResults()
    {
        return $this->hydrator->hydrate($this->qb);
    }
}
