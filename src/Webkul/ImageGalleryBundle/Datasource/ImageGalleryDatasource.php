<?php

namespace Webkul\ImageGalleryBundle\Datasource;

use Webkul\ImageGalleryBundle\Repository\GalleryRepository;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();


/**
 * Export Mapping datasource
 *
 * @author    Webkul
 * @copyright 2017 Webkul Software Pvt Ltd (http://www.webkul.com)
 */
class ImageGalleryDatasource extends \Datasource
{
    /** @var GalleryRepository */
    protected $repository;

    /** @var CustomObjectIdHydrator */
    protected $hydrator;

    /** @var array */
    protected $parameters = [];

    /**
     * @param GalleryRepository $om
     * @param HydratorInterface          $hydrator
     */
    public function __construct(GalleryRepository $repository, \HydratorInterface $hydrator)
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
        $this->qb->resetDQLPart('select');
        $this->qb->addSelect('wem.id, wem.code,wem.starred, wem.createdAt, wem.updatedAt, wem.title, wem.alt, wem.description, wem.galleryGroup');
        
        return $this;
    }
    // SELECT wem.id, wem.code,wem.starred, wem.createdAt, wem.updatedAt, wem.title, wem.alt, wem.description, wem.galleryGroup CASE WHEN (md.thumbnail = 1) THEN md.filePath ELSE 'NULL' END as thumbnail from wk_product_gallery as wem JOIN wk_product_gallery_media as md
    /**
     * {@inheritdoc}
     */
    public function getResults()
    {   
        $this->hydrator->setRepo($this->repository);

        return $this->hydrator->hydrate($this->qb);
    }
}
