<?php

namespace Webkul\Magento2Bundle\Connector\Reader;

use Webkul\Magento2Bundle\Traits\ChannelAwareTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Category reader that reads categories ordered by tree and order inside the tree
 *
 * @author webkul
 */
class CategoryReader extends \PimCategoryReader
{
    use ChannelAwareTrait;
    protected $multipleCategoryTree = true;

    /**
     * @param \CategoryRepositoryInterface $repository
     */
    public function __construct(\CategoryRepositoryInterface $repository, \ChannelRepository $channelRepo)
    {
        $this->repository = $repository;
        $this->channelRepo = $channelRepo;
    }

   
    /**
     * {@inheritdoc}
     */
    protected function getResults()
    {
        $categories = [];
        $parameters = $this->stepExecution->getJobParameters();
        $filters = $parameters->get('filters');
        $rootCategoryId = $this->getDefaultCategoryTreeId($this->stepExecution->getJobParameters());
        $filteredCategories = $this->getCategoriesFromFilter($filters['data']);
        $filteredOperator = $this->getCategoriesFilterOperator($filters['data']);
        
        if ((!$this->isCategoryOnlyExport()
        || ($parameters->has('channelWiseExport') && $parameters->get('channelWiseExport') === true))
        && $filteredOperator === 'IN'
        ) {
            $categories = $this->repository->getCategoriesByCodes($filteredCategories);
            if ($categories instanceof ArrayCollection) {
                $categories = $categories->toArray();
            }
        } elseif ($parameters->has('channelWiseExport') && $parameters->get('channelWiseExport') === true) {
            $rootCategoryCodes = $this->getDefaultCategoryTreeCode($this->stepExecution->getJobParameters());
            $categories = new ArrayCollection();
            foreach ($rootCategoryCodes as  $value) {
                $rootCategory = $this->repository->findOneByIdentifier($value);
                if ($rootCategory) {
                    $childCategories = $this->repository->getAllChildrenCodes($rootCategory);
                    $categories->add($rootCategory);
                    foreach ($childCategories as $childrenCode) {
                        $category = $this->repository->findOneByIdentifier($childrenCode);
                        if ($category) {
                            $categories->add($category);
                        }
                    }
                }
            }
             
            if ($categories instanceof ArrayCollection) {
                $categories = $categories->toArray();
            }
        } else {
            $categories = $this->repository->getOrderedAndSortedByTreeCategories();
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

    private function getCategoriesFilterOperator($data)
    {
        $result = 'IN CHILDREN';
        foreach ($data as $key => $value) {
            if (!empty($value['field']) && 'categories' == $value['field']) {
                $result = $value['operator'];
            }
        }

        return $result;
    }

    private function isCategoryOnlyExport()
    {
        try {
            $flag = $this->stepExecution->getJobExecution()->getJobInstance()->getJobName() === 'magento2_category_export';
        } catch (\Exception $e) {
            $flag = false;
        }

        return $flag;
    }
}
