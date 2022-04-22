<?php
namespace Webkul\Magento2GroupProductBundle\Controller\Rest\Akeneo5;

use Webkul\Magento2Bundle\Entity\ProductMapping;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Akeneo\Pim\Enrichment\Component\Product\EntityWithFamilyVariant\RemoveParentInterface;

class ProductController extends \ProductController {

    protected $connectorService;
    
     /**
     * @param \ProductRepositoryInterface    $productRepository
     * @param \CursorableRepositoryInterface $cursorableRepository
     * @param \AttributeRepositoryInterface  $attributeRepository
     * @param \ObjectUpdaterInterface        $productUpdater
     * @param \SaverInterface                $productSaver
     * @param NormalizerInterface           $normalizer
     * @param ValidatorInterface            $validator
     * @param \UserContext                   $userContext
     * @param \ObjectFilterInterface         $objectFilter
     * @param \CollectionFilterInterface     $productEditDataFilter
     * @param \RemoverInterface              $productRemover
     * @param \ProductBuilderInterface       $productBuilder
     * @param \AttributeConverterInterface   $localizedConverter
     * @param \FilterInterface               $emptyValuesFilter
     * @param \ConverterInterface            $productValueConverter
     * @param NormalizerInterface           $constraintViolationNormalizer
     * @param Magento2Connector             $connectorService
     * @param RemoveParentInterface         $removeParent
     * */
    
    public function __construct(
        \ProductRepositoryInterface $productRepository,
        \CursorableRepositoryInterface $cursorableRepository,
        \AttributeRepositoryInterface $attributeRepository,
        \ObjectUpdaterInterface $productUpdater,
        \SaverInterface $productSaver,
        NormalizerInterface $normalizer,
        ValidatorInterface $validator,
        \UserContext $userContext,
        \ObjectFilterInterface $objectFilter,
        \CollectionFilterInterface $productEditDataFilter,
        \RemoverInterface $productRemover,
        \ProductBuilderInterface $productBuilder,
        \AttributeConverterInterface $localizedConverter,
        \FilterInterface $emptyValuesFilter,
        \ConverterInterface $productValueConverter,
        NormalizerInterface $constraintViolationNormalizer,
        \ProductBuilderInterface $variantProductBuilder,
        \AttributeFilterInterface $attributeFilterInterface = null,
        RemoveParentInterface $removeParent,
        $elastic,
        Magento2Connector $connectorService
        
    ) {
        if(\AkeneoVersion::VERSION > 3.2) {
            parent::__construct(
                $productRepository,
                $cursorableRepository,
                $attributeRepository,
                $productUpdater,
                $productSaver,
                $normalizer,
                $validator,
                $userContext,
                $objectFilter,
                $productEditDataFilter,
                $productRemover,
                $productBuilder,
                $localizedConverter,
                $emptyValuesFilter,
                $productValueConverter,
                $constraintViolationNormalizer,
                $variantProductBuilder,
                $attributeFilterInterface,
                $removeParent,
                $elastic
            );
        } else {
            
            if(empty($attributeFilterInterface))
            {
                parent::__construct(
                    $productRepository,
                    $cursorableRepository,
                    $attributeRepository,
                    $productUpdater,
                    $productSaver,
                    $normalizer,
                    $validator,
                    $userContext,
                    $objectFilter,
                    $productEditDataFilter,
                    $productRemover,
                    $productBuilder,
                    $localizedConverter,
                    $emptyValuesFilter,
                    $productValueConverter,
                    $constraintViolationNormalizer,
                    $variantProductBuilder
                );
            } else {
                parent::__construct(
                    $productRepository,
                    $cursorableRepository,
                    $attributeRepository,
                    $productUpdater,
                    $productSaver,
                    $normalizer,
                    $validator,
                    $userContext,
                    $objectFilter,
                    $productEditDataFilter,
                    $productRemover,
                    $productBuilder,
                    $localizedConverter,
                    $emptyValuesFilter,
                    $productValueConverter,
                    $constraintViolationNormalizer,
                    $variantProductBuilder,
                    $attributeFilterInterface
                );
            }
        }
        $this->connectorService = $connectorService;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function createAction(Request $request)
    {
        $response = parent::createAction($request);
        $data = json_decode($request->getContent(), true);
        if(isset($data['identifier'])) {
            $this->connectorService->removeProductMapping($data['identifier']);
        }

        return $response;
    }

}
