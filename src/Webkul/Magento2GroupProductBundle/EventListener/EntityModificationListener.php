<?php

namespace Webkul\Magento2GroupProductBundle\EventListener;

use Symfony\Component\EventDispatcher\GenericEvent;
use Webkul\Magento2Bundle\Entity\EntityTracker;
use Doctrine\ORM\EntityManager;
use Webkul\Magento2GroupProductBundle\Services\Magento2GroupProductConnector;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Response;

class EntityModificationListener
{
    private $em;
    private $connectorService;

    public function __construct(EntityManager $em, Magento2GroupProductConnector $connectorService)
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
        $code = method_exists($subject, 'getCode') ? $subject->getCode() : (method_exists($subject, 'getIdentifier') ? $subject->getIdentifier() : null);
        if ($index && isset($originalData[$index])) {
            if ($code !== $originalData[$index]) {
                $mapping = $this->em->getRepository('Magento2Bundle:DataMapping')->findOneBy(['code'=> $originalData[$index]]);
                if ($mapping) {
                    $mapping->setCode($code);
                    $this->em->persist($mapping);
                    $this->em->flush();
                }
            }
        }
        
        /** Save the DataMapping Code */
        if (method_exists($subject, 'getAssociations')) {
            foreach ($subject->getAssociations() as $associationType) {
                if ('webkul_magento2_groupped_product' === $associationType->getAssociationType()->getCode()) {
                    $mapping = $this->em->getRepository('Magento2Bundle:ProductMapping')->findOneBySku($code);
                    
                    if (!$mapping) {
                        if (
                            (
                                $associationType->getProducts()->count() > 0
                            || $associationType->getGroups()->count() > 0
                            || $associationType->getProductModels()->count() > 0
                            )
                            &&
                            (
                                method_exists($subject, 'getBundleOptions')
                                && !empty($subject->getBundleOptions())
                            )) {
                            $violations = "One product cann't be both Group and Bundle product";
                            $response = new Response($violations, 400);
                            throw new BadRequestHttpException($response->getContent());
                        } else {
                            if ($associationType->getProducts()->count() > 0 || $associationType->getGroups()->count() > 0 || $associationType->getProductModels()->count() > 0) {
                                $this->connectorService->createProductMapping($code, 'grouped');
                                break;
                            }
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
    /**
     * Event Listener on pre remove.
     *
     * @param GenericEvent $event
     */
    public function onPreRemove(GenericEvent $event)
    {
        $subject = $event->getSubject();
        $code = method_exists($subject, 'getCode') ? $subject->getCode() : (method_exists($subject, 'getIdentifier') ? $subject->getIdentifier() : null);
        /** Delete the DataMapping Code */

        if (method_exists($subject, 'getAssociations') && $code) {
            foreach ($subject->getAssociations() as $associationType) {
                if ('webkul_magento2_groupped_product' === $associationType->getAssociationType()->getCode()) {
                    if ($associationType->getProducts()->count() > 0 || $associationType->getGroups()->count() > 0 || $associationType->getProductModels()->count() > 0) {
                        $this->connectorService->removeProductMapping($code);
                    }
                    break;
                }
            }
        }
    }
}
