<?php

namespace Webkul\Magento2BundleProductBundle\Connector\Processor\Denormalizer\Akeneo2;

use Akeneo\Component\Batch\Item\ItemProcessorInterface;
use Akeneo\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Akeneo\Component\StorageUtils\Exception\PropertyException;
use Akeneo\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Pim\Component\Catalog\Comparator\Filter\FilterInterface;
use Pim\Component\Catalog\EntityWithFamilyVariant\AddParent;
use Pim\Component\Catalog\Model\ProductInterface;
use Pim\Component\Catalog\ProductModel\Filter\AttributeFilterInterface;
use Pim\Component\Connector\Processor\Denormalization\Product\FindProductToImport;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Pim\Component\Connector\Processor\Denormalization\ProductProcessor as BaseProductProcessor;
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
        AttributeFilterInterface $productAttributeFilter
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
            $item = $this->productAttributeFilter->filter($item);

            $familyCode = $this->getFamilyCode($item);
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
