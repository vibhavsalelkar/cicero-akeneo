<?php

namespace Webkul\Magento2BundleProductBundle\Connector\Processor\Denormalizer\Akeneo3;

use Akeneo\Pim\Enrichment\Component\Product\Comparator\Filter\FilterInterface;
use Akeneo\Pim\Enrichment\Component\Product\EntityWithFamilyVariant\AddParent;
use Akeneo\Pim\Enrichment\Component\Product\ProductModel\Filter\AttributeFilterInterface;
use Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface;
use Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyException;
use Akeneo\Tool\Component\StorageUtils\Exception\PropertyException;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Denormalizer\FindProductToImport;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Denormalizer\MediaStorer;
use Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Denormalizer\ProductProcessor as BaseProductProcessor;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
/**
 * Product import processor, allows to,
 *  - create / update
 *  - convert localized attributes
 *  - validate
 *  - skip invalid ones and detach it
 *  - return the valid ones
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductProcessor extends BaseProductProcessor implements ItemProcessorInterface, StepExecutionAwareInterface
{
    /** @var FindProductToImport */
    private $findProductToImport;

    /** @var AddParent */
    private $addParent;

    /** @var ObjectUpdaterInterface */
    protected $updater;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var ObjectDetacherInterface */
    protected $detacher;

    /** @var FilterInterface */
    protected $productFilter;

    /** @var AttributeFilterInterface */
    private $productAttributeFilter;

    /** @var MediaStorer */
    private $mediaStorer;

    public function __construct(
        IdentifiableObjectRepositoryInterface $repository,
        FindProductToImport $findProductToImport,
        AddParent $addParent,
        ObjectUpdaterInterface $updater,
        ValidatorInterface $validator,
        ObjectDetacherInterface $detacher,
        FilterInterface $productFilter,
        AttributeFilterInterface $productAttributeFilter,
        MediaStorer $mediaStorer
    ) {
        parent::__construct($repository,
                            $findProductToImport,
                            $addParent,
                            $updater,
                            $validator,
                            $detacher,
                            $productFilter,
                            $productAttributeFilter,
                            $mediaStorer);

        $this->findProductToImport = $findProductToImport;
        $this->addParent = $addParent;
        $this->updater = $updater;
        $this->validator = $validator;
        $this->detacher = $detacher;
        $this->productFilter = $productFilter;
        $this->productAttributeFilter = $productAttributeFilter;
        $this->repository = $repository;
        $this->mediaStorer = $mediaStorer;
    }

    /**
     * {@inheritdoc}
     */
    public function process($item)
    {
        
        $itemHasStatus = isset($item['enabled']);
        if (!isset($item['enabled'])) {
            $item['enabled'] = $jobParameters = $this->stepExecution->getJobParameters()->get('enabled');
        }

        $identifier = $this->getIdentifier($item);

        if (null === $identifier) {
            $this->skipItemWithMessage($item, 'The identifier must be filled');
        }

        $parentProductModelCode = $item['parent'] ?? '';

        try {
            $familyCode = $this->getFamilyCode($item);
            $item['family'] = $familyCode;

            $item = $this->productAttributeFilter->filter($item);
            $filteredItem = $this->filterItemData($item);
            
            $product = $this->findProductToImport->fromFlatData($identifier, $familyCode);
        } catch (AccessDeniedException $e) {
            $this->skipItemWithMessage($item, $e->getMessage(), $e);
        }

        if (false === $itemHasStatus && null !== $product->getId()) {
            unset($filteredItem['enabled']);
        }

        $jobParameters = $this->stepExecution->getJobParameters();
        $enabledComparison = $jobParameters->get('enabledComparison');
        
        // if ($enabledComparison) {
        //     $filteredItem = $this->filterIdenticalData($product, $filteredItem);
            
        //     if (empty($filteredItem) && null !== $product->getId()) {
        //         $this->detachProduct($product);
        //         $this->stepExecution->incrementSummaryInfo('product_skipped_no_diff');

        //         return null;
        //     }
        // }

        if ('' !== $parentProductModelCode && !$product->isVariant()) {
            try {
                $product = $this->addParent->to($product, $parentProductModelCode);
            } catch (\InvalidArgumentException $e) {
                $this->detachProduct($product);
                $this->skipItemWithMessage($item, $e->getMessage(), $e);
            }
        }

        if (isset($filteredItem['values'])) {
            try {
                $filteredItem['values'] = $this->mediaStorer->store($filteredItem['values']);
            } catch (InvalidPropertyException $e) {
                $this->detachProduct($product);
                $this->skipItemWithMessage($item, $e->getMessage(), $e);
            }
        }
       
        try {
            $this->updateProduct($product, $filteredItem);
        } catch (PropertyException $exception) {
            $this->detachProduct($product);
            $message = sprintf('%s: %s', $exception->getPropertyName(), $exception->getMessage());
            $this->skipItemWithMessage($item, $message, $exception);
        } catch (InvalidArgumentException | AccessDeniedException $exception) {
            $this->detachProduct($product);
            $this->skipItemWithMessage($item, $exception->getMessage(), $exception);
        }

        $violations = $this->validateProduct($product);

        if ($violations->count() > 0) {
            $this->detachProduct($product);
            $this->skipItemWithConstraintViolations($item, $violations);
        }

        return $product;
    }
}
