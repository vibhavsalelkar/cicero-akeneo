<?php
namespace Webkul\Magento2BundleProductBundle\Doctrine\ORM\Repository;

use Doctrine\ORM\EntityRepository;
use Webkul\Magento2BundleProductBundle\Entity\Product;

$obj = new \Webkul\Magento2BundleProductBundle\Listener\AkeneoVersionsCompatibility();
$obj->checkVersionAndCreateClassAliases();

class ProductModelRepository extends \OrmProductModelRepository
{

     /**
     * {@inheritdoc}
     */
    public function findDescendantProductIdentifiers(\ProductModelInterface $productModel): array
    {
        $qb = $this
            ->_em
            ->createQueryBuilder()
            ->select('p.identifier')
            ->from(Product::class, 'p')
            ->innerJoin('p.parent', 'pm', 'WITH', 'p.parent = pm.id')
            ->where('p.parent = :parent')
            ->orWhere('pm.parent = :parent')
            ->setParameter('parent', $productModel);

        return $qb->getQuery()->execute();
    }
    
    /**
     * {@inheritdoc}
     */
    public function findChildrenProducts(\ProductModelInterface $productModel): array
    {
        $qb = $this
            ->_em
            ->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.parent = :parent')
            ->setParameter('parent', $productModel);

            
        return $qb->getQuery()->execute();
    }
}