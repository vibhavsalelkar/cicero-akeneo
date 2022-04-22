<?php

namespace Webkul\ImageGalleryBundle\Connector\Reader;

use Webkul\ImageGalleryBundle\Repository\GalleryRepository;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();

class GalleryReader extends \AbstractReader implements
    \ItemReaderInterface,
    \InitializableInterface,
    \StepExecutionAwareInterface
{

    protected $repository;

    public function __construct(GalleryRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    protected function getResults()
    {
        $data =  new \ArrayIterator($this->repository->findBy([], ['code' => 'ASC', 'starred' => 'ASC']));
        
        return $data;
    }
}
