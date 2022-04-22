<?php

namespace Webkul\Magento2Bundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Webkul\Magento2Bundle\Entity\CredentialConfig;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;

/**
 * Configuration rest controller in charge of the magento2 connector configuration managements
 */
class CredentialController extends Controller
{
    const READONLY_CREDENTIALS = 'credentailsReadonlyUrls';
    const QUICK_EXPORT_CODE = 'magento2_product_quick_export';
    
    /**
     * add credentials
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function addAction(Request $request)
    {
        $id = $request->get('id');
        $em = $this->getDoctrine()->getManager();
        $data = json_decode($request->getContent(), true);
        $hostName = rtrim($data['hostName'], '/\\');
        if ($id) {
            $credential = $em->getRepository(CredentialConfig::class)->findOneById($id);
        } elseif ($hostName) {
            $credential = $em->getRepository(CredentialConfig::class)->findOneByHostName($hostName);
        }
        
        if (!$credential) {
            $credential = new CredentialConfig();
        }

        $data = json_decode($request->getContent(), true);
        $em = $this->getDoctrine()->getManager();
        $connectorService = $this->get('magento2.connector.service');

        if (!empty($credential) && !empty($data['hostName']) && !empty($data['authToken'])) {
            $storeViews = $connectorService->checkCredentialAndGetStoreViews($data);
            if (!empty($storeViews) && !isset($storeViews['error'])) {
                $credential->setHostName($data['hostName']);
                $credential->setAuthToken($data['authToken']);

                $host = $request->getHost();
                if ($request->getPort() && !in_array($request->getPort(), [443, 80])) {
                    $host .= ':' . $request->getPort();
                }
                $data['host'] = $host;
                $data['scheme'] = $request->getScheme();
                $data['storeViews'] = gettype($storeViews) == 'string' ? json_decode($storeViews, true) : $storeViews;
                if (!empty($data['storeMapping'])) {
                    $data = $this->modifyStoreMappings($data);
                }
               
                $resource = [];

                foreach (['host', 'scheme', 'storeViews', 'storeMapping', 'defaultLocale', 'defaultChannel'] as $key) {
                    if (isset($data[$key])) {
                        $resource[$key] = $data[$key];
                    }
                }
                
                if (isset($resource['storeMapping']) && is_array($resource['storeMapping'])) {
                    foreach ($resource['storeMapping'] as $key => $value) {
                        if ($key == 'allStoreView') {
                            if (isset($value['channel']) && isset($value['locale']) && isset($value['currency']) && !empty($value['channel']) && !empty($value['locale']) && !empty($value['currency'])) {
                                break;
                            } else {
                                return new JsonResponse([ 'hostName' => 'Error! Default Store View Not Mapped' ], Response::HTTP_BAD_REQUEST);
                            }
                        }
                    }
                }

                $credential->setResources(
                    is_array($resource) ? json_encode($resource) : $resource
                );
                
                $em->persist($credential);
                $em->flush();
                
                $data= ['meta' => [
                    'id' => $credential->getId()
                ]];
                
                return new JsonResponse($data);
            }
        }
        $erroMessage = $storeViews['error'] ?? 'Error! invalid credentials';
 
        return new JsonResponse([ 'hostName' => $storeViews ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * toogle status
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function toggleStatusAction($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $credential = $em->getRepository(CredentialConfig::class)->findOneById($id);

        if ($credential) {
            if ($credential->getActive()) {
                $credential->setDefaultSet(0);
            }
            $credential->setActive($credential->getActive() ? 0 : 1);
            $em->persist($credential);
            $em->flush();
        }

        if (!$request->isXmlHttpRequest()) {
            return $this->redirect($this->generateUrl('webkul_magento2_connector_configuration'));
        }

        return new JsonResponse(['successful' => true]);
    }

    /**
     * delete credentials
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function deleteAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $credential = $em->getRepository(CredentialConfig::class)->findOneById($id);
        
        if ($credential) {
            //Skip the Readonly Parameter to delete
            $host = $credential->getHostName();
            
            if ($this->container->hasParameter(self::READONLY_CREDENTIALS) && in_array($host, $this->container->getParameter(self::READONLY_CREDENTIALS))) {
                return new JsonResponse(['successful' => false], 400);
            }
            
            $em->remove($credential);
            $em->flush();
        }

        return new JsonResponse([]);
    }


    /**
     * get credential
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function getAction($id, Request $request)
    {
        $data = [];
        $em = $this->getDoctrine()->getManager();
        $credential = $em->getRepository(CredentialConfig::class)->findOneById($id);

        if ($credential) {
            $data = [
                'id' => $credential->getId(),
                'hostName' => $credential->getHostName(),
                'authToken' => $credential->getAuthToken(),
            ];
            if ($credential->getResources()) {
                $result = json_decode($credential->getResources(), true);

                if ($result) {
                    $data = array_merge($data, $result);
                }
            }
        }

        return new JsonResponse($data);
    }

    /**
     * get credentials
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function getAllAction()
    {
        $em = $this->getDoctrine()->getManager();
        $credentials = $em->getRepository(CredentialConfig::class)->findAll();
        $data = [];

        foreach ($credentials as $credential) {
            if ($credential->getActive()) {
                $data[$credential->getId()] = $credential->getHostName();
            }
        }

        return new JsonResponse($data);
    }

    public function changeDefaultAction(Request $request)
    {
        $id = $request->get('id');
        $em = $this->getDoctrine()->getManager();
        $otherCredential = $em->getRepository(CredentialConfig::class)->findByDefaultSet(1);
        $credential = $em->getRepository(CredentialConfig::class)->findOneById($id);
        
        $this->checkAndSaveQuickJob();

        if ($credential) {
            foreach ($otherCredential as $otherCred) {
                if ($otherCred !== $credential) {
                    $otherCred->setDefaultSet(0);
                    $em->persist($otherCred);
                }
            }
            if ($credential->getActive()) {
                $credential->setDefaultSet($credential->getDefaultSet() ? 0 : 1);
            }
            $em->persist($credential);
            $em->flush();
        }

        return new JsonResponse(['successful' => true]);
    }

    protected function modifyStoreMappings($params)
    {
        $storeViews = $params['storeViews'] ?? [];
        if ($storeViews && !empty($params['storeMapping'])) {
            if (gettype($params['storeMapping']) == 'string') {
                $params['storeMapping'] = json_decode($params['storeMapping'], true);
            }
 
            $storeViewCodes = [];
            foreach ($storeViews as $storeView) {
                $storeViewCodes[] = $storeView['code'];
            }
            if (! in_array('allStoreView', $storeViewCodes)) {
                $storeViewCodes[] = 'allStoreView';
            }
            
            $mapData = !empty($params['storeMapping']) ? $params['storeMapping'] : [];
            if ($mapData) {
                $sortedMapping = ['allStoreView'=>[]];
                foreach ($mapData as $key => $mappingRow) {
                    if (in_array($key, $storeViewCodes)) {
                        $sortedMapping[$key] = $mappingRow;
                    }
                }
                
                $params['storeMapping'] = $sortedMapping;
            }
        }
        
        foreach ($params['storeMapping'] as $key => $storeMapping) {
        }
        
        return $params;
    }

    protected function checkAndSaveQuickJob()
    {
        $jobInstance = $this->get('pim_enrich.repository.job_instance')->findOneBy(['code' => self::QUICK_EXPORT_CODE]);
    
        if (!$jobInstance) {
            $em = $this->getDoctrine()->getManager();
            $jobInstance = new \JobInstance();
            $jobInstance->setCode(self::QUICK_EXPORT_CODE);
            $jobInstance->setJobName('magento2_quick_export');
            $jobInstance->setLabel('Magento 2 product quick export');
            $jobInstance->setConnector('Magento 2 Export Connector');
            $jobInstance->setType('quick_export');
            $em->persist($jobInstance);
            $em->flush();
        }
    }
}
