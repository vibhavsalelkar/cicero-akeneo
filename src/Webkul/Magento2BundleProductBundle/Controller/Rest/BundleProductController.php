<?php
namespace Webkul\Magento2BundleProductBundle\Controller\Rest;

use Webkul\Magento2Bundle\Entity\ProductMapping;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Webkul\Magento2BundleProductBundle\Validator\Constraints\BundleOptionValidator;

class BundleProductController extends \ProductController {

    private $connectorService;
    const DEFAULT_ERROR_MESSAGE = 'Property "bundleid" does not exist.';
      
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
     * @param \AttributeFilterInterface             $attributeFilterInterface
     * @param Magento2Connector             $connectorService
     */
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
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }
        
        $data = json_decode($request->getContent(), true);

        $product = $this->productBuilder->createProduct(
            $data['identifier'] ?? null,
            $data['family'] ?? null
        );
        
        $violations = $this->validator->validate($product);

        if (0 === $violations->count()) {

            if(isset($data['identifier'])) {
                $existProduct = $this->connectorService->checkExistProduct($data['identifier'], 'configurable');
                if($existProduct) {

                    $normalizedViolations = [
                        [
                        "attribute"=> "sku",
                        "locale"=> NULL,
                        "scope"=> NULL,
                        "message"=> 'The same code is already set on product model.'
                    ]];

                    return new JsonResponse(['values' => $normalizedViolations], 400);
                }
                
                $this->connectorService->createProductMapping($data['identifier'], 'bundle');
            }

            $this->productSaver->save($product);

            return new JsonResponse($this->normalizer->normalize(
                $product,
                'internal_api',
                $this->getNormalizationContext()
            ));
        }

        $normalizedViolations = [];
        foreach ($violations as $violation) {
            $normalizedViolations[] = $this->constraintViolationNormalizer->normalize(
                $violation,
                'internal_api',
                ['product' => $product]
            );
        }

        return new JsonResponse(['values' => $normalizedViolations], 400);
    }


    public function postAction(Request $request, $id)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }
        
        $product = $this->findProductOr404($id);
        if ($this->objectFilter->filterObject($product, 'pim.internal_api.product.edit')) {
            throw new AccessDeniedHttpException();
        }
        $data = json_decode($request->getContent(), true);
        
        try {
            $data = $this->productEditDataFilter->filterCollection($data, null, ['product' => $product]);
        } catch (ObjectNotFoundException $e) {
            throw new BadRequestHttpException();
        }
        $this->updateProduct($product, $data);

        $violations = $this->validator->validate($product);

        $violations->addAll($this->localizedConverter->getViolations());
        if(isset($data['bundleOptions']) && 0 === $violations->count()) {
            $customValidator = new BundleOptionValidator();
            $customViolations = $customValidator->validateBundleOptions($data['bundleOptions'], $this->connectorService);
            if (count($customViolations) !== 0) {
                return new JsonResponse(['values' => $customViolations], 400);             
            } 

        }

        if (0 === $violations->count()) {
            $this->productSaver->save($product);

            $normalizedProduct = $this->normalizer->normalize(
                $product,
                'internal_api',
                $this->getNormalizationContext()
            );

            return new JsonResponse($normalizedProduct);
        }

        $normalizedViolations = [];
        foreach ($violations as $violation) {
            $normalizedViolations[] = $this->constraintViolationNormalizer->normalize(
                $violation,
                'internal_api',
                ['product' => $product]
            );
        }

        return new JsonResponse(['values' => $normalizedViolations], 400);
    }

}