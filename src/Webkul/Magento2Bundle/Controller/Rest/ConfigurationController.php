<?php

namespace Webkul\Magento2Bundle\Controller\Rest;

use Oro\Bundle\ConfigBundle\Entity\ConfigValue;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Url;
use Webkul\Magento2Bundle\Entity;
use Webkul\Magento2Bundle\Component\Version;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Configuration rest controller in charge of the magento2 connector configuration managements
 */
class ConfigurationController extends Controller
{
    const SECTION = 'magento2_connector';
    
    const SECTION_STORE_MAPPING = 'magento2_store_mapping';

    const SECTION_DRAFT_MAPPING = 'magento2_draft_mapping';

    const SECTION_CHILD_ATTRIBUTE_MAPPING = 'magento2_child_attribute_mapping';
    const SECTION_ASSOCIATION_ATTRIBUTE_MAPPING = 'magento2_association_mapping';
    protected $magento2Parameters = ['readonlyCredentials', 'support_magento2_productAttachment', 'support_amasty_productAttachment', 'support_amasty_product_parts'];

    protected $moduleVersion;

    protected $connectorService;

    protected $localesRepo;

    public function __construct($connectorService, $localesRepo)
    {
        $this->connectorService = $connectorService;
        $this->localesRepo = $localesRepo;
    }
    
    /**
     * Get the current configuration
     *
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function getAction()
    {
        $data = $this->connectorService->getCredentials();

        if (!empty($data["authToken"])) {
            $data["authToken"] = str_repeat("*", 20);
        }
        $data['mapping'] = $this->connectorService->getAttributeMappings();
        $data['childmapping'] = $this->connectorService->getAttributeMappings(self::SECTION_CHILD_ATTRIBUTE_MAPPING);
        $draftMapping= $this->connectorService->getAttributeMappings(self::SECTION_DRAFT_MAPPING);
        $data['draftMapping'] = is_array($draftMapping) ?  $draftMapping : [];
        $data['locales'] = $this->localesRepo->getActivatedLocalesQB()->getQuery()->getArrayResult();

        if ($otherMapping = $this->connectorService->getOtherMappings()) {
            $data['otherMappings'] = $otherMapping;
        }
        if ($otherSettings = $this->connectorService->getSettings()) {
            $data['otherSettings'] = $otherSettings;
        }
        if ($associationMapping = $this->connectorService->getSettings(self::SECTION_ASSOCIATION_ATTRIBUTE_MAPPING)) {
            $data['association'] = $associationMapping;
        }

        if (null === $this->moduleVersion) {
            $versionObject = new Version();
            $this->moduleVersion = $versionObject->getModuleVersion();
        }
        
        $data['moduleVersion'] = $this->moduleVersion;

        foreach ($this->magento2Parameters as $parameter) {
            $data[$parameter] = $this->container->hasParameter($parameter) ? $this->container->getParameter($parameter) : false;
        }
        
        return new JsonResponse($data);
    }

    public function getCredentailReadonlyParametersAction()
    {
        $data['credentailReadonlyParameters'] = $this->container->hasParameter('magento2_credentail_readonly_parameters') ? $this->container->getParameter('magento2_credentail_readonly_parameters') : array();
        
        return new JsonResponse($data);
    }
    /**
     * Set the current configuration
     * @AclAncestor("webkul_magento2_connector_configuration")
     *
     * @return JsonResponse
     */
    public function postAction(Request $request)
    {
        $data  = [];
        $request->request->remove('locales');
        $params = $request->request->all();
        $em = $this->getDoctrine()->getManager();
        
        switch ($request->attributes->get('tab')) {
            case 'mapping':

                if (isset($params['mapping'])) {
                    $attributeData = !empty($params['mapping']) ? $params['mapping'] : null;
                    if ($attributeData) {
                        $this->connectorService->saveAttributeMappings($attributeData);
                    }
                }
                if (isset($params['childmapping'])) {
                    $attributeData = !empty($params['childmapping']) ? $params['childmapping'] : null;
                    if ($attributeData) {
                        $this->connectorService->saveAttributeMappings($attributeData, self::SECTION_CHILD_ATTRIBUTE_MAPPING);
                    }
                }
                if (isset($params['otherMappings'])) {
                    $this->connectorService->saveOtherMappings($params['otherMappings']);
                }
                if (isset($params['draftMapping'])) {
                    $this->connectorService->saveAttributeMappings($params['draftMapping'], self::SECTION_DRAFT_MAPPING);
                }

                return new JsonResponse([]);
                break;

            case 'association':
                $associationData = !empty($params['association']) ? $params['association'] : null;
                if ($associationData) {
                    $this->connectorService->saveSettings($associationData, self::SECTION_ASSOCIATION_ATTRIBUTE_MAPPING);
                }
                return new JsonResponse([]);
                break;

            case 'storeMapping':
                $storeViews = !empty($params['storeViews']) ? json_decode($params['storeViews'], true) : [];
                $storeViewCodes = [];
                foreach ($storeViews as $storeView) {
                    $storeViewCodes[] = $storeView['code'];
                }
                $mapData = !empty($params['storeMapping']) ? $params['storeMapping'] : [];
                foreach ($mapData as $key => $mappingRow) {
                    if (!in_array($key, $storeViewCodes)) {
                        unset($mapData[$key]);
                    }
                }
                //default locale set
                if ($params['defaultLocale']) {
                    $repo = $em->getRepository('OroConfigBundle:ConfigValue');
                    $defaultLocaleMapping = $repo->findOneBy([
                        'section' => self::SECTION_STORE_MAPPING,
                        'name' => 'defaultLocale',
                        ]);

                    if (!$defaultLocaleMapping) {
                        $defaultLocaleMapping = new ConfigValue();
                        $defaultLocaleMapping->setSection(self::SECTION_STORE_MAPPING);
                        $defaultLocaleMapping->setName('defaultLocale');
                    }
                    $defaultLocaleMapping->setValue($params['defaultLocale']);
                    $em->persist($defaultLocaleMapping);
                    $em->flush();
                }

                if ($mapData) {
                    $repo = $em->getRepository('OroConfigBundle:ConfigValue');
                    $storeMapping = $repo->findOneBy([
                        'section' => self::SECTION_STORE_MAPPING,
                        'name' => 'storeMapping',
                        ]);

                    if (!$storeMapping) {
                        $storeMapping = new ConfigValue();
                        $storeMapping->setSection(self::SECTION_STORE_MAPPING);
                        $storeMapping->setName('storeMapping');
                    }
                    $storeMapping->setValue(json_encode($mapData));
                    $em->persist($storeMapping);
                    $em->flush();
                }
                return new JsonResponse([]);
                break;

            case 'credential':
                $form = $this->getConfigForm();
                if (!empty($params['hostName']) && $params['authToken'] === str_repeat("*", 20)) {
                    $data = $this->connectorService->getCredentials();
                    $params['authToken'] = !empty($data["authToken"]) ? $data["authToken"] : '';
                }
                $form->submit($params);
                $form->handleRequest($request);
                $storeViews = $this->connectorService->checkCredentialAndGetStoreViews($params);
                if (empty($storeViews)) {
                    $form->get('authToken')->addError(new FormError($this->get('translator')->trans('Invalid Credentials.')));
                } else {
                    $params['storeViews'] = $storeViews;
                }
                $params['host'] = $request->getHost();
                $params['scheme'] = $request->getScheme();

                //Readonly Params
                $readOnlyParams = $this->container->hasParameter('credentail_readonly_parameters') ? $this->container->getParameter('credentail_readonly_parameters') : array();
                
                if ($form->isSubmitted() && $form->isValid() && empty($readOnlyParams)) {
                    $this->checkAndSaveQuickJob();
                    $repo = $em->getRepository('OroConfigBundle:ConfigValue');
                    foreach ($params as $key => $value) {
                        if (in_array($key, array_keys($this->getCredentialWithContraints())) || $key == 'storeViews') {
                            if (!is_array($value)) {
                                $value = strip_tags($value);

                                $configValue = $repo->findOneBy([
                                        'section' => self::SECTION, 'name' => $key
                                        ]);

                                if ($configValue) {
                                    $configValue->setValue($value);
                                    $em->persist($configValue);
                                } else {
                                    $configValue = new ConfigValue();
                                    $configValue->setSection(self::SECTION);
                                    $configValue->setName($key);
                                    $configValue->setValue($value);
                                    $em->persist($configValue);
                                }
                                $em->flush();
                            }
                        }
                    }
                    $this->connectorService->saveOtherSettings($params);
                } else {
                    return new JsonResponse($this->getFormErrors($form), RESPONSE::HTTP_BAD_REQUEST);
                }
                // no break
            case 'otherSettings':
            default:
                $tab = $request->attributes->get('tab');
                $data = !empty($params[$tab]) ? $params[$tab] : null;
                if ($data) {
                    $this->connectorService->saveSettings($data);
                }
        }
        
        return $this->getAction();
    }

    public function getDataAction()
    {
        return new JsonResponse($this->mappingFields);
    }

    public function getMappingAttributesDataAction()
    {
        $connectorService = $this->get('magento2.connector.service');
        $data = $connectorService->getOtherMappings();

        return isset($data['custom_fields']) ? new JsonResponse($data['custom_fields']): new JsonResponse([]);
    }

    public function getCsvFileAction(Request $request)
    {
        $fileName = $request->get('path');
        $start = $request->get('start') ? : 0;
        $end = $request->get('end') ? : 0;

        if ($fileName && file_exists($fileName) && strpos(strrev($fileName), 'vsc.') === 0) {
            $file = new File($fileName);
            $response = new StreamedResponse(function () use ($file, $start, $end) {
                $handle = fopen($file->getRealPath(), 'r');
                $output = fopen('php://output', 'w');
                $count = 0;
                while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
                    if ($count === 0 || $start === 0 || $count >= $start) {
                        fputcsv($output, $row, ';', '"');
                    }
                    $count++;
                    if ($end && $count >= $end) {
                        break;
                    }
                }
                fclose($handle);
            });

            $response->headers->set('Content-Type', $file->getMimeType());
            return $response;
        }
        exit(',');
    }

   

    private function getConfigForm()
    {
        $form = $this->createFormBuilder(null, [
                    'allow_extra_fields' => true,
                    'csrf_protection' => false
                ]);

        foreach ($this->getCredentialWithContraints() as $field => $constraint) {
            $form->add($field, null, [
                    'constraints' => [
                        $constraint
                    ]
                ]);
        }

        return $form->getForm();
    }

    private function getFormErrors($form)
    {
        $errorContext = [];
        foreach ($form->getErrors(true) as $key => $error) {
            $errorContext[$error->getOrigin()->getName()] = $error->getMessage();
        }

        return $errorContext;
    }

    private function getCredentialWithContraints()
    {
        return [
            'hostName' => new Url(),
            'authToken' => new NotBlank(),
            'host' => new Optional(),
            'scheme' => new Optional(),
            
        ];
    }

    public function postStoreViewsandCheckCredentialAction(Request $request)
    {
        $value = $request->request->all();
        $storeView = null;
        if (!empty($value['configuration'])) {
            $storeView = $this->connectorService->checkCredentialAndGetStoreViews($value['configuration']);
        }
        
        if (!empty($storeView)) {
            $response = new Response();
            $response->setContent($storeView);
            $response->headers->set('Content-Type', 'application/json');
            $response->setStatusCode(Response::HTTP_OK);
        } else {
            $response = new Response();
            $response->setContent($storeView . $this->get('translator')->trans('Invalid Credentials.'));
            $response->headers->set('Content-Type', 'application/json');
            $response->setStatusCode(Response::HTTP_NOT_FOUND);
        }
        
        return $response;
    }
    
    /**
     * GET Job Instace class versionwise
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getJobInstanceClassAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }

        $version = new \AkeneoVersion();
        $jobInstanceClass = 'Akeneo\Component\Batch\Model\JobInstance';

        if ($version::VERSION > '3.0') {
            $jobInstanceClass = 'Akeneo\Tool\Component\Batch\Model\JobInstance';
        }
        
        return new JsonResponse(['history_class'=> $jobInstanceClass]);
    }
    
    public function getCategoryDetailsAction(Request $request)
    {
        $connectorService = $this->get('magento2.connector.service');
        $repo = $connectorService->getPimRepository('category');
        $params = $request->getQueryString();
        parse_str($params, $paramArray);
        $result = [];
        $ids = [];
        foreach ($paramArray as $key => $value) {
            $response = $repo->findBy([ 'code' => $value ]);
            if ($response) {
                $ids[] = $response[0]->getId();
                $result[$response[0]->getId()]['id'] = $response[0]->getId();
                $result[$response[0]->getId()]['rootId'] = $response[0]->getRoot();
                $result[$response[0]->getId()]['code'] = $response[0]->getCode();
            }
        }

        return new JsonResponse(['categoryData' => $result, 'ids' => $ids]);
    }

    private $mappingFields = [
        [
            'name' => 'sku',
            'label' => 'SKU',
            'placeholder' => '',
            'types' => [
                'pim_catalog_identifier',
                'pim_catalog_text',
                'pim_catalog_number',
            ],
            'tooltip' => 'supported attributes types: identifier, text, number (must be unique)',
        ],
        [
            'name' => 'name',
            'label' => 'magento2_connector.attribute.name',
            'placeholder' => '',
            'types' => [
                'pim_catalog_text',
            ],
            'tooltip' => 'supported attributes types: text',
        ],
        [
            'name' => 'weight',
            'label' => 'magento2_connector.attribute.weight',
            'placeholder' => '',
            'types' => [
                'pim_catalog_metric',
            ],
            'tooltip' => 'supported attributes types: metric',
        ],
        [
            'name' => 'price',
            'label' => 'magento2_connector.attribute.price',
            'placeholder' => '',
            'types' => [
                'pim_catalog_price_collection',
            ],
            'tooltip' => 'supported attributes types: price',
        ],
        [
            'name' => 'description',
            'label' => 'magento2_connector.attribute.description',
            'placeholder' => '',
            'types' => [
                'pim_catalog_textarea',
            ],
            'tooltip' => 'supported attributes types: textarea',
        ],
        [
            'name' => 'short_description',
            'label' => 'magento2_connector.attribute.short_description',
            'placeholder' => '',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_textarea',
            ],
            'tooltip' => 'supported attributes types: text, textarea',
        ],
        [
            'name' => 'quantity',
            'label' => 'magento2_connector.attribute.quantity',
            'placeholder' => '',
            'types' => [
                'pim_catalog_number',
            ],
            'tooltip' => 'supported attributes types: number',
        ],
        [
            'name' => 'visibility',
            'label' => 'magento2_connector.attribute.visibility',
            'placeholder' => 'by default: visible on both search and catalog',
            'types' => [
                'pim_catalog_simpleselect',
                'pim_catalog_number',
             ],
            'tooltip' => 'supported attributes types: simple select',
        ],
        [
            'name' => 'meta_title',
            'label' => 'magento2_connector.attribute.meta_title',
            'placeholder' => '',
            'types' => [
                'pim_catalog_text',
            ],
            'tooltip' => 'supported attributes types: text',
        ],
        [
            'name' => 'meta_keyword',
            'label' => 'magento2_connector.attribute.meta_keyword',
            'placeholder' => '',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_textarea',
            ],
            'tooltip' => 'supported attributes types: text',
        ],
        [
            'name' => 'meta_description',
            'label' => 'magento2_connector.attribute.meta_description',
            'placeholder' => '',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_textarea',
            ],
            'tooltip' => 'supported attributes types: text, textarea',
        ],
        [
            'name' => 'url_key',
            'label' => 'magento2_connector.attribute.url_key',
            'placeholder' => '',
            'types' => [
                'pim_catalog_text',
                'pim_catalog_identifier',
            ],
            'tooltip' => 'supported attributes types: text',
        ],
        [
            'name' => 'website_ids',
            'label' => 'magento2_connector.attribute.website_ids',
            'placeholder' => '',
            'types' => [
                'pim_catalog_multiselect',
            ],
            'tooltip' => 'supported attributes types: multiselect',
        ],
        [
            'name' => 'tax_class_id',
            'label' => 'magento2_connector.attribute.tax_class_id',
            'placeholder' => '',
            'types' => [
                'pim_catalog_simpleselect',
            ],
            'tooltip' => 'supported attributes types: simple select',
        ],
        [
            'name' => 'country_of_manufacture',
            'label' => 'magento2_connector.attribute.country_of_manufacture',
            'placeholder' => '',
            'types' => [
                'pim_catalog_simpleselect',
            ],
            'tooltip' => 'supported attributes types: simple select',
        ],
        

    ];
}
