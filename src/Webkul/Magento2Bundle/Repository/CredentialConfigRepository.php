<?php

namespace Webkul\Magento2Bundle\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
// use Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$versionCompatibily = new AkeneoVersionsCompatibility();
$versionCompatibily->checkVersionAndCreateClassAliases();


class CredentialConfigRepository extends EntityRepository implements \DatagridRepositoryInterface, \MassActionRepositoryInterface
{
    /** @var EntityManager */
    protected $em;

    /** @var string */
    protected $entityName;

    /**
     * @param EntityManager $em
     * @param string        $class
     */
    public function __construct(EntityManager $em, $class)
    {
        $classMeta = ! $class instanceof ClassMetadata ? $em->getClassMetadata($class) : $class;
        parent::__construct(
             $em,
             $classMeta
         );

        $this->em = $em;
        $this->entityName = $class instanceof ClassMetadata ? $class->getName() : $class;
    }

    /**
     * {@inheritdoc}
     */
    public function createDatagridQueryBuilder()
    {
        $qb = $this->createQueryBuilder('wem');
        
        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteFromIds(array $identifiers)
    {
        if (empty($identifiers)) {
            throw new \LogicException('No Instance to remove');
        }

        $qb = $this->em->createQueryBuilder();
        $qb->delete($this->entityName, 'wem')
            ->where($qb->expr()->in('wem.id', $identifiers));

        return $qb->getQuery()->execute();
    }

    /**
     * {@inheritdoc}
     *
     * @param QueryBuilder $queryBuilder
     */
    public function applyMassActionParameters($qb, $inset, array $values)
    {
        if ($values) {
            $rootAlias = $qb->getRootAlias();
            $valueWhereCondition =
                $inset
                    ? $qb->expr()->in($rootAlias, $values)
                    : $qb->expr()->notIn($rootAlias, $values);
            $qb->andWhere($valueWhereCondition);
        }

        if (null !== $qb->getDQLPart('where')) {
            $whereParts = $qb->getDQLPart('where')->getParts();
            $qb->resetDQLPart('where');

            foreach ($whereParts as $part) {
                if (!is_string($part) || !strpos($part, 'entityIds')) {
                    $qb->andWhere($part);
                }
            }
        }

        $qb->setParameters(
            $qb->getParameters()->filter(
                function ($parameter) {
                    return $parameter->getName() !== 'entityIds';
                }
            )
        );
    }
}
