<?php

namespace Webkul\Magento2Bundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Webkul\Magento2Bundle\Repository\DataMappingRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;

/**
 * Export mapping rest controller in charge of the Magento mapping configuration managements
 *
 * @author    Webkul <support@webkul.com>
 *
 */
class DataMappingController extends Controller
{
    // /** @var EntityManager */
    // protected $em;

    // /** @var DataMappingRepository */
    // protected $dataMappingRepository;

    // /** @var CategoryRepositoryInterface */
    // protected $repository;

    //  /** @var NormalizerInterface */
    //  protected $normalizer;
    
    //   /** @var ObjectFilterInterface */
    // protected $objectFilter;
 
    // public function __construct(
    //     \CategoryRepositoryInterface $repository,
    //     NormalizerInterface $normalizer,
    //     \ObjectFilterInterface $objectFilter,
    //     DataMappingRepository $dataMappingRepository,
    //     EntityManager $em
    // ) {
        
    //     $this->repository = $repository;
    //     $this->normalizer = $normalizer;
    //     $this->objectFilter = $objectFilter;
    //     $this->dataMappingRepository = $dataMappingRepository;
    //     $this->em = $em;
    // }


    /**
     * Create data mapping
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function createAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        
        $akeneoEntityIdField   = 'akeneo' . ucfirst($data['type']) . 'Id';
        $magentoEntityField     = 'magento' . ucfirst($data['type']) . 'Id';
        $akeneoEntityNameField = 'akeneo' . ucfirst($data['type']) . 'Name';
        $magentoEntityNameField   = 'magento' . ucfirst($data['type']) . 'Name';

        $mapping = $this->DataMappingRepository->findOneBy([
                'code' => $data[$akeneoEntityIdField],
                'entityType' => $data['type']
            ]);

        if (!$data[$magentoEntityField] || !$data[$akeneoEntityIdField]) {
            $errors = [];
            if (!$data[$magentoEntityField]) {
                $errors[$magentoEntityField] = 'This value should not be blank.';
            }

            if (!$data[$akeneoEntityIdField]) {
                $errors[$akeneoEntityIdField] = 'This value should not be blank.';
            }

            return new JsonResponse($errors, 400);
        } elseif ($mapping && $mapping->getRelatedId() != $data[$magentoEntityField]) {
            return new JsonResponse([
                    $akeneoEntityIdField => 'Already mapped with another ' . $data['type'] . '.',
                ], 400);
        } else {
            $mapping = $this->dataMappingRepository->findOneBy([
                    'externalId' => $data[$magentoEntityField],
                    'entityType' => $data['type']
                ]);

            if ($mapping && $mapping->getCode() != $data[$akeneoEntityIdField]) {
                return new JsonResponse([
                        $magentoEntityField => 'Already mapped with another ' . $data['type'] . '.',
                    ], 400);
            } else {
                $mapping = $this->dataMappingRepository->findOneBy([
                        'akeneoEntityId' => $data[$akeneoEntityIdField],
                        'externalId' => $data[$magentoEntityField],
                        'entityType' => $data['type']
                    ]);

                if (!$mapping) {
                    $mapping = new DataMapping;
                }

                $mapping->setCode($data[$akeneoEntityIdField]);
                $mapping->setRelatedId($data[$magentoEntityField]);
                $mapping->setEntityType($data['type']);

                $this->em->persist($mapping);
                $this->em->flush();
            }
        }

        return new JsonResponse([
                'meta' => [
                    'id' => $mapping->getId()
                ]
            ]);
    }

    
    /**
     * Remove data mapping
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function deleteAction($id)
    {
        $mapping = $this->dataMappingRepository->find($id);
        if (!$mapping) {
            throw new NotFoundHttpException(
                sprintf('Mapping with id "%s" not found', $id)
            );
        }

        $this->em->remove($mapping);
        $this->em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * List all Akeneo categories
     *
     * @return JsonResponse
     */
    public function getAkeneoCategoriesAction(Request $request)
    {
        $normalizedCategories = [];

        // if ($request->isXmlHttpRequest()){
        $normalizer = $this->get('pim_internal_api_serializer');
            
        $categoryRepo = $this->get('webkul_magento2.repository.category.search');
        $options = $request->query->get('options', ['limit' => 3, 'expanded' => 1]);
            
        $expanded = !isset($options['expanded']) || $options['expanded'] === 1;
           
        $categories = $categoryRepo->findBySearch(
            $request->query->get('search'),
            $options
        );
            
        $normalizedCategories = [];
        foreach ($categories as $category) {
            $normalizedCategories[$category->getCode()] = $normalizer->normalize(
                $category,
                'internal_api',
                ['expanded' => $expanded]
            );
        }
        
        return new JsonResponse($normalizedCategories);
    }

    /**
     * List all Akeneo attributes
     *
     * @return JsonResponse
     */
    public function getAkeneoAttributesAction(Request $request)
    {
        $normalizedAttributes = [];

        $normalizer = $this->get('pim_internal_api_serializer');
        $attributeRepo = $this->get('webkul_magento2.repository.attribute.search');
        
        $options = $request->query->get('options', ['limit' => 3, 'expanded' => 1]);
        
        $expanded = !isset($options['expanded']) || $options['expanded'] === 1;

        $attributes = $attributeRepo->findBySearch(
            $request->query->get('search'),
            $options
        );
        foreach ($attributes as $attribute) {
            $normalizedAttributes[$attribute->getCode()] = $normalizer->normalize(
                $attribute,
                'internal_api',
                ['expanded' => $expanded]
            );
        }
        
        return new JsonResponse($normalizedAttributes);
    }
    /**
     * Returns mapping type for filter
     *
     * @return JsonResponse
     */
    public function getTypes()
    {
        return [
                'Category' => 'category',
                'Attribute' => 'attribute',
                'Attribute Option' => 'attribute.option',
                'Product Model' => 'product.model',
                'Product' => 'product'
            ];
    }

    /**
     * Return mapping type for filter
     *
     * @return JsonResponse
     */
    public function getCredentialApiUrl()
    {
        $data = [
            // 'Api Url' => '1'
        ];
  
        return $data;
    }

    /**
     * Return mapping type for filter
     *
     * @return JsonResponse
     */
    public function getMagentoApiAction()
    {
        $connectorService = $this->get('magento2.connector.service');
        $apiUrl = $connectorService->getCredentials();
        $apiUrl = $connectorService->getApiUrl();
        return new JsonResponse(['apiUrl' => $apiUrl]);
    }

    /**
     * Returns Magento category list
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function getMagentoCategoriesAction()
    {
        $categories = [
            'Master1' => '1',
            'Master1' => '2'
        ];
        
        
        return new JsonResponse();
    }

    /**
     * Returns magento attribute list
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function getMagentoAttributesAction()
    {
        $item = [
            'Master1' => '1',
            'Master1' => '1',
        ];
        $connectorService = $this->get('magento2.connector.service');
       

        return new JsonResponse($item);
    }
}
