<?php
namespace Webkul\Magento2GroupProductBundle\Controller\Rest\Akeneo5;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webkul\Magento2Bundle\Entity\ProductMapping;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2GroupProductBundle\Controller\Rest\Akeneo5\ProductController;

class GrouppedProductController extends ProductController
{
   
    /**
     * @param Request $request
     *
     * @return Response
     */
    public function createAction(Request $request)
    {
        $response = parent::createAction($request);
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['identifier'])) {
            $this->connectorService->createProductMapping($data['identifier'], 'grouped');
        }
        
        return $response;
    }
}
