<?php

namespace Webkul\Magento2Bundle\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

class MappedAttributeRepository extends EntityRepository
{
    // /** @var EntityManager */
    // protected $em;

    //  /** @var string */
    // protected $entityName;

    //  /**
    //   * @param EntityManager $em
    //   * @param string        $class
    //   */
    //  public function __construct(EntityManager $em, $class)
    //  {
    //      $classMeta = ! $class instanceof ClassMetadata ? $em->getClassMetadata($class) : $class;
    //      parent::__construct(
    //          $em, $classMeta
    //     );

    //      $this->em = $em;
    //      $this->entityName = $class instanceof ClassMetadata ? $class->getName() : $class;
    //  }
}
