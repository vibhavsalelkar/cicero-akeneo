<?php

namespace Webkul\Magento2BundleProductBundle\Listener;

use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webkul\Magento2BundleProductBundle\Validator\Constraints\BundleOptionValidator;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;


class EntityModificationListener
{
    private $em;
    private $connectorService;
    protected $violations;
    protected $router;

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
        $code = method_exists($subject, 'getCode') ? $subject->getCode() : (method_exists($subject, 'getIdentifier') ? $subject->getIdentifier() : null);
        /** Save the DataMapping Code */
        if (method_exists($subject, 'getBundleOptions') && !empty($subject->getBundleOptions()) && $code) {
            if (method_exists($subject, 'getAssociations')) {
                foreach($subject->getAssociations() as $associationType) {
                    if('webkul_magento2_groupped_product' === $associationType->getAssociationType()->getCode()) {
                        
                        if ($associationType->getProducts()->count() > 0 || $associationType->getGroups()->count() > 0 || $associationType->getProductModels()->count() > 0) {
                            $violations = "One product cann't be both Group and Bundle product";
                            $response = new Response($violations, 400);
                            throw new BadRequestHttpException($response->getContent());
                        }
                        break;
                    }
                }
            }

            $bundleOptions = $subject->getBundleOptions();
            if (!empty($bundleOptions)) {
                $validator = new BundleOptionValidator();
                $violations = $validator->validateBundleOptions($bundleOptions, $this->connectorService);
                
                if (count($violations) === 0) {
                    $this->connectorService->createProductMapping($code, 'bundle');                   
                } else {
                    $response = new JsonResponse($violations, 400);
                    throw new BadRequestHttpException($response->getContent());
                }
            }
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
        if (method_exists($subject, 'getBundleOptions') && !empty($subject->getBundleOptions()) && $code) {
            $this->connectorService->removeProductMapping($code);
        }
    }
}