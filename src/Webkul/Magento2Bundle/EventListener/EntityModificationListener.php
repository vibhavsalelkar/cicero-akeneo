<?php

namespace Webkul\Magento2Bundle\EventListener;

use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webkul\Magento2Bundle\Entity\EntityTracker;
use Webkul\Magento2Bundle\Services\Magento2Connector;

class EntityModificationListener
{
    private $em;
    private $connectorService;

    public function __construct(EntityManager $em, Magento2Connector $connectorService)
    {
        $this->em = $em;
        $this->connectorService = $connectorService;
    }

    /**
     * Event Listener on pre save.
     *
     * @param GenericEvent $event
     */
    public function onPreSave(GenericEvent $event)
    {
        $subject = $event->getSubject();
        $uow = $this->em->getUnitOfWork();
        /** Update the DataMapping Code If SKU update for pervent the Duplicacy of product export */
        $originalData = $uow->getOriginalEntityData($subject);
        $index = method_exists($subject, 'getCode') ? 'code' : (method_exists($subject, 'getIdentifier') ? 'identifier' : null);
        if ($index && isset($originalData[$index])) {
            $code = $index === 'code' ? $subject->getCode() : $subject->getIdentifier();
            if ($code !== $originalData[$index]) {
                $mappings = $this->em->getRepository('Magento2Bundle:DataMapping')->findBy(['code'=> $originalData[$index]]);

                if ($mappings) {
                    foreach ($mappings as $mapping) {
                        if ($mapping) {
                            $mapping->setCode($code);
                            $this->em->persist($mapping);
                            $this->em->flush();
                        }
                    }
                }
                
                $mappings = $this->em->getRepository('Magento2Bundle:ProductMapping')->findBy(['sku'=> $originalData[$index]]);
                if ($mappings) {
                    foreach ($mappings as $mapping) {
                        if ($mapping) {
                            $mapping->setCode($code);
                            $this->em->persist($mapping);
                            $this->em->flush();
                        }
                    }
                }
            }
        }
    }

    /**
     * Event Listener on post save.
     *
     * @param GenericEvent $event
     */
    public function onPostSave(GenericEvent $event)
    {
        $subject = $event->getSubject();
        
        if (get_class($subject) === 'Akeneo\Component\Versioning\Model\Version') {
            if ($subject->getContext() === 'Deleted') {
                $snapshot = $subject->getSnapshot();
                $resourceName = $subject->getResourceName();

                $action = 'delete';
                $entity = $this->getEntityByClassName($resourceName);
                $code = $snapshot['code'] ?? ($snapshot['sku']);
                if ($entity == 'attribute') {
                    $this->removeFromMagentoSettings($code);
                }
            }
        } else {
            $action = 'save';
            $entity = $this->getEntityByClassName(get_class($subject));
            $code = method_exists($subject, 'getCode') ? $subject->getCode() : (method_exists($subject, 'getIdentifier') ? $subject->getIdentifier() : null);
        }

        if (!empty($entity) && !empty($code)) {
            $this->updateOrCreateTrackByEntityCodeAndAction($entity, $code, $action);
        }
    }

    protected function getEntityByClassName($className)
    {
        $entity = null;
        if (preg_match("#Category$#", $className)) {
            $entity = 'category';
        } elseif (preg_match("#Attribute$#", $className)) {
            $entity = 'attribute';
        } elseif (preg_match("#AttributeOption$#", $className)) {
            $entity = 'option';
        } elseif (preg_match("#Family$#", $className)) {
            $entity = 'family';
        } elseif (preg_match("#AttributeGroup$#", $className)) {
            $entity = 'group';
        // } else if(preg_match("#Product$#", $className)) {
        //     $entity = 'product';
        // } else if(preg_match("#ProductModel$#", $className)) {
        //     $entity = 'product-model';
        } else {
            // don't do anything
            return;
        }

        return $entity;
    }

    protected function updateOrCreateTrackByEntityCodeAndAction($entity, $code, $action)
    {
        $repo = $this->em->getRepository('Magento2Bundle:EntityTracker');
        $track = $repo->findOneBy(['entityType' => $entity, 'code' => $code]);
        if (!$track) {
            $track = new EntityTracker();
        }
        $track->setEntityType($entity);
        $track->setCode($code);
        $track->setAction($action);
        $this->em->persist($track);
        $this->em->flush();
    }

    protected function removeFromMagentoSettings($code)
    {
        /* change custom-atribute mappings */
        $otherMappings = $this->connectorService->getOtherMappings();
        $indexes = ['images', 'custom_fields', 'import_custom_fields'];
        $changeFlag = false;
        foreach ($indexes as $index) {
            if (!empty($otherMappings[$index]) &&
                ($key = array_search($code, $otherMappings[$index])) !== false
            ) {
                unset($otherMappings[$index][$key]);
                $otherMappings[$index] = array_values($otherMappings[$index]);
                $changeFlag = true;
            }
        }
        if ($changeFlag) {
            $this->connectorService->saveOtherMappings($otherMappings);
        }
        /* change other settings */
        $otherMappings = $this->connectorService->getSettings();
        $indexes = ['magento2_attachment_fields', 'magento2_amasty_attachment_fields'];
        $changeFlag = false;
        foreach ($indexes as $index) {
            $otherMappings[$index]= !empty($otherMappings[$index]) ? json_decode($otherMappings[$index], true) : [];

            if (!empty($otherMappings[$index]) &&
                ($key = array_search($code, $otherMappings[$index])) !== false
            ) {
                unset($otherMappings[$index][$key]);
                $otherMappings[$index] = json_encode($otherMappings[$index]);
                $changeFlag = true;
            }
        }

        if ($changeFlag) {
            $this->connectorService->saveSettings($otherMappings);
        }
    }

    public function onPreRemove(GenericEvent $event)
    {
        $subject = $event->getSubject();
        if (get_class($subject) === 'Akeneo\Pim\Enrichment\Component\Category\Model\Category' || get_class($subject) === 'Pim\Bundle\CatalogBundle\Entity\Category') {
            $resourceName = $subject->getCode();
            $action = 'delete';
            $entity = 'category';
            $code = $resourceName;
        }

        if (!empty($entity) && !empty($code)) {
            $this->updateOrCreateTrackByEntityCodeAndAction($entity, $code, $action);
        }
    }
}
