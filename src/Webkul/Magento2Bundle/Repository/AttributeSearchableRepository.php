<?php
namespace Webkul\Mgento2Bundle\Repository;

use Akeneo\Tool\Component\StorageUtils\Repository\SearchableRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Attribute searchable repository
 */
class AttributeSearchableRepository implements SearchableRepositoryInterface
{
    /** @var EntityManagerInterface */
    protected $entityManager;
    /** @var string */
    protected $entityName;
    /**
     * @param EntityManagerInterface $entityManager
     * @param string                 $entityName
     */
    public function __construct(EntityManagerInterface $entityManager, $entityName)
    {
        $this->entityManager = $entityManager;
        $this->entityName = $entityName;
    }
    
    public function findBySearch($search = null, array $options = [])
    {
        $qb = $this->entityManager->createQueryBuilder()->select('p')->from($this->entityName, 'p');
        
        if (isset($options['searchBy']) && $options['searchBy'] === 'code') {
            $qb->andWhere('p.parent IS NULL');
        }

        if (null !== $search && '' !== $search) {
            $qb->leftJoin('c.translations', 'ct');
            $qb->andWhere('c.code like :search OR ct.label like :search');
            $qb->distinct();
            $qb->setParameter('search', '%' . $search . '%');
        }

        $qb = $this->applyQueryOptions($qb, $options);

        return $qb->getQuery()->getResult();
    }
    /**
     * @param QueryBuilder $qb
     * @param array        $options
     *
     * @return QueryBuilder
     */
    protected function applyQueryOptions(QueryBuilder $qb, array $options)
    {
        if (isset($options['limit'])) {
            $qb->setMaxResults((int) $options['limit']);
            if (isset($options['page'])) {
                $qb->setFirstResult((int) $options['limit'] * ((int) $options['page'] - 1));
            }
        }
        
        return $qb;
    }
}
