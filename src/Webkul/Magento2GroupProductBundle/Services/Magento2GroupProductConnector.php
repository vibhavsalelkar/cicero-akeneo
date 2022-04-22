<?php
namespace Webkul\Magento2GroupProductBundle\Services;

use Webkul\Magento2Bundle\Services\Magento2Connector;

/**
 * Magento2Group Product Connector Service
 */
class Magento2GroupProductConnector extends Magento2Connector
{
    protected $container;

    private $fileSystemProvider;
    
    private $em;
    
    private $attributeRepo;

    private $categoryRepo;

    private $channelRepo;

    private $attributeGroupRepo;

    private $attributeOptionRepo;

    private $familyRepo;

    private $familyVariantRepo;

    private $productRepo;

    private $productModelRepo;

    private $familyVariantFactory;

    private $familyVariantUpdater;

    private $familySaver;

    private $familyVariantSaver;

    private $familyUpdater;

    private $updaterSetterRegistery;

    private $normalizedViolations;

    private $apiSerializer;

    private $router;

    private $validator;

    public function __construct(
        $fileSystemProvider,
        \Doctrine\ORM\EntityManager $em,
        $attributeRepo,
        $categoryRepo,
        $channelRepo,
        $localeRepo,
        $attributeGroupRepo,
        $attributeOptionRepo,
        $familyRepo,
        $familyVariantRepo,
        $productRepo,
        $productModelRepo,
        $familyVariantFactory,
        $familyVariantUpdater,
        $familySaver,
        $familyVariantSaver,
        $familyUpdater,
        $updaterSetterRegistery,
        $normalizedViolations,
        $apiSerializer,
        $router,
        $container,
        $validator
    ) {
        parent::__construct($fileSystemProvider, $em, $attributeRepo, $categoryRepo, $channelRepo, $localeRepo, $attributeGroupRepo, $attributeOptionRepo, $familyRepo, $familyVariantRepo, $productRepo, $productModelRepo, $familyVariantFactory, $familyVariantUpdater, $familySaver, $familyVariantSaver, $familyUpdater, $updaterSetterRegistery, $normalizedViolations, $apiSerializer, $router, $container, $validator);

        $this->productRepo = $productRepo;
    }

    public function getCountGroupBundleProducts($identifier, $mappingType)
    {
        $pqb = $this->productRepo->createQueryBuilder('p')
                                                        ->leftJoin('p.associations', 'po')
                                                        ->andWhere('p.identifier = :identifier')
                                                        ->setParameter('identifier',$identifier);
        switch($mappingType) {
            case 'grouped':
               $result = $pqb->leftJoin('po.associationType','ast')
                            ->select('asocProduct.identifier')
                            ->leftJoin('po.products', 'asocProduct')
                            ->andWhere('ast.code = :code')
                            ->setParameter('code', 'webkul_magento2_groupped_product')
                            ->getQuery()->getArrayResult();
                return count($result);
                break;
            case 'bundle':
                $count = 0;                
                $bundleOptions = $pqb->select('p.bundleOptions')->getQuery()->setMaxResults(1)->getSingleResult();
                if(!empty($bundleOptions['bundleOptions'])) {
                    foreach ($bundleOptions['bundleOptions'] as $value) {
                        if (is_array($value) && isset($value['products']) && is_array($value['products'])) {
                            foreach ($value['products'] as $productIdentifier) {
                                if (!empty($productIdentifier)) {
                                    $count++;
                                }
                            }
                        }
                    }
                }
                return $count;
            break;
            default:
                return 0;
            break;
        }
    }
}
