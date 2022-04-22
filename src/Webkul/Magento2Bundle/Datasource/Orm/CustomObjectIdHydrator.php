<?php

namespace Webkul\Magento2Bundle\Datasource\Orm;

// use Pim\Bundle\DataGridBundle\Datasource\ResultRecord\HydratorInterface;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();
/**
 * Hydrate results of Doctrine ORM query as array of ids
 *
 * @author    Webkul <support@webkul.com>
 *
 */
class CustomObjectIdHydrator implements \HydratorInterface
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($qb, array $options = [])
    {
        $qb->setMaxResults(null);
        $rootAlias = current($qb->getRootAliases());
        $rootIdExpr = sprintf('%s.id', $rootAlias);

        $from = current($qb->getDQLPart('from'));

        $qb
            ->resetDQLPart('from')
            ->from($from->getFrom(), $from->getAlias(), $rootIdExpr);

        $qb = $this->setOrderByFieldsToSelect($qb);
        $qb->addSelect($rootIdExpr);

        $results = $qb->getQuery()->getArrayResult();

        return array_keys($results);
    }

    /**
     * If the given query $qb has some fields in the "ORDER BY" statement,
     * put those fields in the "SELECT" statement too.
     *
     * This way we retrieve object IDs, and the fields we order by.
     *
     * @param mixed $qb
     *
     * @return mixed
     */
    protected function setOrderByFieldsToSelect($qb)
    {
        $originalSelects = $qb->getDQLPart('select');
        $orders = $qb->getDQLPart('orderBy');
        $newSelects = [];

        $qb->resetDQLPart('select');

        foreach ($originalSelects as $select) {
            foreach ($select->getParts() as $part) {
                $alias = stristr($part, ' as ');
                if (false !== $alias) {
                    $newSelects[str_ireplace(' as ', '', $alias)] = $part;
                }
            }
        }

        foreach ($orders as $order) {
            foreach ($order->getParts() as $part) {
                $alias = explode(' ', $part)[0];
                if (isset($newSelects[$alias])) {
                    $qb->addSelect($newSelects[$alias]);
                }
            }
        }

        return $qb;
    }
}
