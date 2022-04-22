<?php

namespace Webkul\Magento2Bundle\Connector\Reader;

use Doctrine\Common\Persistence\ObjectRepository;

/**
 * attribute reader, basic
 */
class FamilyQuickReader extends \AbstractReader implements
    \ItemReaderInterface,
    \InitializableInterface,
    \StepExecutionAwareInterface
{
    /** @var ObjectRepository */
    protected $repository;

    /**
     * @param ObjectRepository $repository
     */
    public function __construct(ObjectRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    protected function getResults()
    {
        return new \ArrayIterator($this->repository->findAll());
    }
}
