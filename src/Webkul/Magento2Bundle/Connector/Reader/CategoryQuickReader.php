<?php

namespace Webkul\Magento2Bundle\Connector\Reader;

use Doctrine\Common\Persistence\ObjectRepository;
use Webkul\Magento2Bundle\Traits\ChannelAwareTrait;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * attribute reader, basic
 */
class CategoryQuickReader extends \AbstractReader implements
    \ItemReaderInterface,
    \InitializableInterface,
    \StepExecutionAwareInterface
{
    use ChannelAwareTrait;

    /** @var ObjectRepository */
    protected $repository;

    /**
     * @param ObjectRepository $repository
     */
    public function __construct(ObjectRepository $repository, $channelRepo)
    {
        $this->repository = $repository;
        $this->channelRepo = $channelRepo;
    }

    /**
     * {@inheritdoc}
     */
    protected function getResults()
    {
        $filters = $this->stepExecution->getJobParameters()->get('filters');

        $rootCategoryId = $this->getDefaultCategoryTreeId($this->stepExecution->getJobParameters());

        $rootCategoryCode = $this->getDefaultCategoryTreeCode($this->stepExecution->getJobParameters());

        $filteredCategories = [ $rootCategoryCode ];
        if (count($filteredCategories) == 1 && $rootCategoryCode === reset($filteredCategories)) {
            if ($rootCategoryId) {
                $categories = $this->repository->findBy(
                    [ 'root' => $rootCategoryId ],
                    [ 'root' => 'ASC', 'left' => 'ASC' ]
                );
            } else {
                $categories = $this->repository->getOrderedAndSortedByTreeCategories();
            }

            foreach ($categories as $key => $category) {
                if ($rootCategoryId == $category->getId()) {
                    unset($categories[$key]);
                    break;
                }
            }
        } else {
            $categories = $this->repository->getCategoriesByCodes($filteredCategories);
            if ($categories instanceof ArrayCollection) {
                $categories = $categories->toArray();
            }
        }

        return new \ArrayIterator($categories);
    }

    private function getCategoriesFromFilter($data)
    {
        $result = null;
        foreach ($data as $key => $value) {
            if (!empty($value['field']) && 'categories' == $value['field']) {
                $result = $value['value'];
            }
        }
        return $result;
    }
}
