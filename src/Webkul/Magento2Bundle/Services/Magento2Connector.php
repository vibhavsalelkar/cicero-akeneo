<?php

namespace Webkul\Magento2Bundle\Services;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Entity;
use Webkul\Magento2Bundle\Component\Validator\ValidCredentials;
use Webkul\Magento2Bundle\Entity\ProductMapping;
use Webkul\Magento2Bundle\Entity\DataMappingRepository;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\Connector\Writer\AttributeOptionWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Oro\Bundle\ConfigBundle\Entity\ConfigValue;

class Magento2Connector
{
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = '';
    
    const SECTION = 'magento2_connector';

    const SECTION_STORE_MAPPING = 'magento2_store_mapping';

    const SECTION_ATTRIBUTE_MAPPING = 'magento2_attribute_mapping';

    const SECTION_STORE_SETTING = 'magento2_store_';

    const SECTION_OTHER_MAPPINGS = 'magento2_other_mapping';

    const SETTING_SECTION = 'magento2_otherSettings';

    const SUPPORT_SWATCH_IMAGES = "support_swatch_images";

    private $requiredKeys = [ 'hostName', 'authToken' ];

    private $stepExecution;

    private $attrGroupArray = [];

    private $attributeOptions;

    private $attributeLabels = [];

    private $credentials = [];

    private $attributesAxesOptions;

    private $localeRepo;

    private $fileSystemProvider;
    
    private $em;
    
    private $attributeRepo;

    private $categoryRepo;

    private $channelRepo;

    private $attributeGroupRepo;

    private $attributeOptionRepo;

    private $familyRepo;

    private $familyVariantRepo;

    private $productRepo;

    private $productModelRepo;

    private $familyVariantFactory;

    private $familyVariantUpdater;

    private $familySaver;

    private $familyVariantSaver;

    private $familyUpdater;

    private $updaterSetterRegistery;

    private $normalizedViolations;

    private $apiSerializer;

    private $router;

    private $container;

    private $validator;
    
    public function __construct(
        $fileSystemProvider,
        \Doctrine\ORM\EntityManager $em,
        $attributeRepo,
        $categoryRepo,
        $channelRepo,
        $localeRepo,
        $attributeGroupRepo,
        $attributeOptionRepo,
        $familyRepo,
        $familyVariantRepo,
        $productRepo,
        $productModelRepo,
        $familyVariantFactory,
        $familyVariantUpdater,
        $familySaver,
        $familyVariantSaver,
        $familyUpdater,
        $updaterSetterRegistery,
        $normalizedViolations,
        $apiSerializer,
        $router,
        $container,
        $validator
    ) {
        $this->fileSystemProvider = $fileSystemProvider;
        $this->em = $em;
        $this->attributeRepo = $attributeRepo;
        $this->categoryRepo = $categoryRepo;
        $this->channelRepo = $channelRepo;
        $this->localeRepo = $localeRepo;
        $this->attributeGroupRepo = $attributeGroupRepo;
        $this->attributeOptionRepo = $attributeOptionRepo;
        $this->familyRepo = $familyRepo;
        $this->familyVariantRepo = $familyVariantRepo;
        $this->productRepo = $productRepo;
        $this->productModelRepo = $productModelRepo;
        $this->familyVariantFactory = $familyVariantFactory;
        $this->familyVariantUpdater = $familyVariantUpdater;
        $this->familySaver = $familySaver;
        $this->familyVariantSaver = $familyVariantSaver;
        $this->familyUpdater = $familyUpdater;
        $this->updaterSetterRegistery = $updaterSetterRegistery;
        $this->normalizedViolations = $normalizedViolations;
        $this->apiSerializer = $apiSerializer;
        $this->router = $router;
        $this->container = $container;
        $this->validator = $validator;
    }

    public function setStepExecution($stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    public function getInstalledModule()
    {
        return $this->container->getParameter('kernel.bundles');
    }

    public function getMagentoVersion2()
    {
        $result = $this->getCredentials();
        $hostName = $result['hostName'];
        $hostName = rtrim($hostName, '/');
        $hostName = $hostName . '/magento_version';
      
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $hostName,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
          ));

        $result = curl_exec($ch);
        
        $result = html_entity_decode($result);
        $result = explode('/', $result);
        if (count($result) && isset($result[1])) {
            $result = explode('(', $result[1]);
            $result = trim($result[0]);
        }
        
        curl_close($ch);
        if ($result == "") {
            $result = "2.3";
        }
            
        return $result;
    }

    public function getCredentials()
    {
        if (empty($this->credentials)) {
            $params = $this->stepExecution ? $this->stepExecution->getJobparameters()->all() : null;
            
            if (!empty($this->stepExecution) && $this->stepExecution instanceof \StepExecution) {
                if (!empty($params['exportProfile'])) {
                    $credential = $this->em->getRepository('Magento2Bundle:CredentialConfig')->findOneBy([
                        "id" => $params['exportProfile'],
                        "active" => 1
                    ]);
                } elseif ($this->isQuickExport($this->stepExecution->getJobParameters())) {
                    $credential = $this->em->getRepository('Magento2Bundle:CredentialConfig')->findOneBy([
                        "defaultSet" => 1,
                        "active" => 1
                    ]);
                }

                if (!empty($credential)) {
                    $credentials = [
                        'hostName' => $credential->getHostName(),
                        'authToken' => $credential->getAuthToken(),
                    ];
                    if ($credential->getResources()) {
                        $resources = json_decode($credential->getResources(), true);
                        $credentials = array_merge($credentials, $resources);
                    }
                    $this->credentials = $credentials;
                }
            }
        }
        
        return $this->credentials;
    }

    /**
     * check if job is quick export?
     * @param JobParameters $parameters
     * @return boolean isQuickExport
     */
    public function isQuickExport(\JobParameters $parameters)
    {
        $filters = $parameters->get('filters');

        return !empty($filters[0]['context']);
    }

    public function getAttributeMappings($section = self::SECTION_ATTRIBUTE_MAPPING)
    {
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
        $attrMappings = $repo->findBy([
            'section' => $section
            ]);

        return $this->indexValuesByName($attrMappings);
    }

    public function saveAttributeMappings($attributeData, $section = self::SECTION_ATTRIBUTE_MAPPING)
    {
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');

        foreach ($attributeData as $key => $value) {
            if (strpos($value, ' ')) {
                unset($attributeData[$key]);
            }
        }

        /* remove extra mapping not recieved in new save request */
        $extraMappings = array_diff(array_keys($this->getAttributeMappings($section)), array_keys($attributeData));
        foreach ($extraMappings as $mCode => $aCode) {
            $mapping = $repo->findOneBy([
                'name' => $aCode,
                'section' => $section
            ]);
            if ($mapping) {
                $this->em->remove($mapping);
            }
        }
        
        
        /* save attribute mappings */
        foreach ($attributeData as $mCode => $aCode) {
            $mCode = strip_tags($mCode);
            $aCode = strip_tags($aCode);
            
            $attribute = $repo->findOneBy([
                'name' => $mCode,
                'section' => $section
            ]);
            
            if ((strpos($aCode, ' ') || $aCode == 'Select Attribute') && $attribute) {
                $this->em->remove($attribute);
                continue;
            }

            if ($attribute) {
                $attribute->setValue($aCode);
                $this->em->persist($attribute);
            } else {
                $attribute = new ConfigValue();
                $attribute->setSection($section);
                $attribute->setName($mCode);
                $attribute->setValue($aCode);
                $this->em->persist($attribute);
            }
        }

        $this->em->flush();
    }

    public function checkCredentialAndGetStoreViews($params)
    {
        if (is_array($params) && $this->array_keys_exists($this->requiredKeys, $params)) {
            return $this->fetchStoreApi($params);
        }
    }

    public function saveOtherSettings($params)
    {
        if (is_array($params) && $this->array_keys_exists($this->requiredKeys, $params)) {
            $storeConfigs = $this->fetchStoreApi($params, $params['hostName'] . '/rest/V1/store/storeConfigs?');
            $configs = $storeConfigs['error'] ? [] : json_decode($storeConfigs, true);
            $setting = [];
            foreach ($configs as $config) {
                $setting[$config['code']] = [];
                foreach (['locale', 'base_currency_code', 'weight_unit'] as $property) {
                    if (isset($config[$property])) {
                        $setting[$config['code']][$property] = $config[$property];
                    }
                }
            }
            $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');

            $configValue = $repo->findOneBy([
                'section' => self::SECTION_STORE_SETTING,
                'name' => 'settings',
            ]);
            if ($configValue) {
                $configValue->setValue(json_encode($setting));
            } else {
                $configValue = new ConfigValue();
                $configValue->setValue(json_encode($setting));
                $configValue->setSection(self::SECTION_STORE_SETTING);
                $configValue->setName('settings');
            }
            $this->em->persist($configValue);
            
            $this->em->flush();
        }
    }

    public function getOtherSettings()
    {
        $otherSettings = null;

        if (empty($otherSettings)) {
            $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');

            $configValue = $repo->findOneBy([
                'section' => self::SECTION_STORE_SETTING,
                'name' => 'settings',
            ]);
            $otherSettings = $configValue && $configValue->getValue() ? json_decode($configValue->getValue(), true) : null;
        }

        return $otherSettings;
    }

    public function saveSettings($params, $section = self::SETTING_SECTION)
    {
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
        foreach ($params as $key => $value) {
            if (gettype($value) === 'array') {
                $value = json_encode($value);
            }

            if (gettype($value) == 'string' || "NULL" === gettype($value)) {
                $field = $repo->findOneBy([
                    'section' => $section,
                    'name' => $key,
                    ]);
                    
                if (null != $value) {
                    if (!$field) {
                        $field = new ConfigValue();
                    }
                    $field->setName($key);
                    $field->setSection($section);
                    $field->setValue($value);
                    $this->em->persist($field);
                } elseif ($field) {
                    $this->em->remove($field);
                }
            }

            $this->em->flush();
        }
    }

    public function getSettings($section = self::SETTING_SECTION)
    {
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
        if (empty($this->settings[$section])) {
            $configs = $repo->findBy([
                'section' => $section
                ]);

            $this->settings[$section] = $this->indexValuesByName($configs);
        }
        
        return $this->settings[$section];
    }


    public function saveOtherMappings($params)
    {
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
        
        $configValue = $repo->findOneBy([
            'section' => self::SECTION_OTHER_MAPPINGS,
            'name'    => 'otherMappings',
        ]);
        if ($configValue) {
            $configValue->setValue(json_encode($params));
        } else {
            $configValue = new ConfigValue();
            $configValue->setValue(json_encode($params));
            $configValue->setSection(self::SECTION_OTHER_MAPPINGS);
            $configValue->setName('otherMappings');
        }
        $this->em->persist($configValue);
        $this->em->flush();
    }

    public function getOtherMappings()
    {
        static $otherMappings;

        if (empty($otherMappings)) {
            $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');

            $configValue = $repo->findOneBy([
                'section' => self::SECTION_OTHER_MAPPINGS,
                'name' => 'otherMappings',
            ]);

            $otherMappings = $configValue && $configValue->getValue() ? json_decode($configValue->getValue(), true) : null;
        }

        return $otherMappings;
    }

    public function findChannelByIdentifier($channelCode)
    {
        return $this->channelRepo->findOneByIdentifier($channelCode);
    }


    public function getChannelRepository()
    {
        return $this->channelRepo;
    }
    public function getStoreMapping()
    {
        $credentials = $this->getCredentials();
        $storeMapping = !empty($credentials['storeMapping']) ? $credentials['storeMapping'] : [];
        $storeMapping = array_filter($storeMapping);

        return $storeMapping;
    }

    public function getIdentifierAttributeCode()
    {
        $identifierAttr = $this->attributeRepo->findOneByType('pim_catalog_identifier');
        
        return $identifierAttr ? $identifierAttr->getCode() : null;
    }

    public function getAttributeByCode($attrCode)
    {
        return  $this->attributeRepo->findOneByIdentifier($attrCode);
    }


    public function getSelectTypeAttributes()
    {
        return array_merge(
            $this->attributeRepo->getAttributeCodesByType('pim_catalog_simpleselect'),
            $this->attributeRepo->getAttributeCodesByType('pim_catalog_multiselect')
        );
    }


    public function getGroupByAttributeCode($attrCode)
    {
        if (!isset($this->attrGroupArray[$attrCode])) {
            $attribute = $this->getAttributeByCode($attrCode);
            $group = $attribute->getGroup();
            if (!$group) {
                $this->useFallBackGroupFetchMethod();
            } else {
                $this->attrGroupArray[$attrCode] = $group;
            }
        }

        return !empty($this->attrGroupArray[$attrCode]) ? $this->attrGroupArray[$attrCode] : null;
    }

    private function useFallBackGroupFetchMethod()
    {
        $groups =  $this->attributeGroupRepo->findAll();
        foreach ($groups as $group) {
            $attrs = $group->getAttributes();
            foreach ($attrs as $attr) {
                $code = $attr->getCode();
                $this->attrGroupArray[$code] = $group;
            }
        }
    }


    public function getFamilyByCode($code)
    {
        return $this->familyRepo->findOneByIdentifier($code);
    }

    public function getFamilyVariantByIdentifier($identifier)
    {
        return $this->familyVariantRepo->findOneByIdentifier($identifier);
    }

    private function fetchStoreApi($params, $url = null)
    {
        try {
            $oauthClient = new OAuthClient($params['authToken'], $params['hostName']);
            if (empty($url)) {
                $url = $params['hostName'] . '/rest/V1/store/storeViews?';
            }
            $oauthClient->fetch($url, [], 'GET', ['Content-Type' => 'application/json', 'Accept' => 'application/json']);

            $results = $oauthClient->getLastResponse();
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }
        return $results;
    }

    private function array_keys_exists(array $keys, array $arr)
    {
        $flag = true;
        foreach ($keys as $key) {
            if (!array_key_exists($key, $arr)) {
                $flag = false;
                break;
            }
        }

        return $flag;
    }

    private function indexValuesByName($values)
    {
        $result = [];
        foreach ($values as $value) {
            $result[$value->getName()] = $value->getValue();
        }
        return $result;
    }


    public function findCodeByExternalId($externalId, $entity)
    {
        $apiUrl = $this->getApiUrl();
        $mapping = $this->em->getRepository('Magento2Bundle:DataMapping')->findOneBy([
            'externalId'   => $externalId,
            'entityType' => $entity,
            'apiUrl' => $apiUrl,
        ]);
         
        return $mapping ? $mapping->getCode() : null;
    }

    // check category code present in db
    public function matchCategoryCodeInDb($code)
    {
        $results = $this->categoryRepo->findOneByCode($code);
        if ($results) {
            foreach ($results as $result) {
                if (!empty($result['code'])) {
                    return $result['code'];
                }
            }
        } else {
            return null;
        }
    }

    public function convertToCode($name, $changeCase = true)
    {
        setlocale(LC_ALL, 'en_US.utf8');
        $name = iconv('utf-8', 'ascii//TRANSLIT', $name);
        $name = ltrim($name, '_');

        if ($changeCase) {
            $name = strtolower($name);
        }
        $name = str_ireplace("+", "_", $name);
        $name = str_ireplace(",", "_", $name);
        $name = str_ireplace(".", "_", $name);
        $code = preg_replace(['#\s#', '#-#', '#[^a-zA-Z0-9_+\s]#'], ['_', '_', ''], $name);
                        
        $code = substr($code, 0, 100);
        
        return $code;
    }

    public function attributeOptionCheckInDB($optionCode, $code, $localeLabels = [])
    {
        $attributeOptionRepo = $this->attributeOptionRepo;

        $results = $attributeOptionRepo->createQueryBuilder('o')
                    ->select('o.id, o.code, o.sortOrder, a.code as attributeId')
                    ->innerJoin('o.attribute', 'a')
                    ->leftJoin('o.optionValues', 't')
                    ->Where('o.code = :option_code')
                    ->orWhere('t.value IN (:option_labels)')
                    ->andWhere('a.code = :attribute_code')
                    ->setParameters(['attribute_code' => $code,'option_code' => $optionCode,'option_labels' => $localeLabels])
                    ->getQuery()
                    ->getResult();
     
        if ($results === null) {
            return $results;
        } else {
            return !empty($results[0]) ? $results[0]: null;
        }
    }
    
    public function matchAttributeCodeInDb($code)
    {
        $attributeRepo = $this->attributeRepo;
        $results = $attributeRepo->createQueryBuilder('a')
                    -> select('a.code as code')
                    -> where('a.code = :code')
                    -> setParameter('code', $code)
                    -> getQuery()->getResult();
        if ($results) {
            foreach ($results as $result) {
                if (!empty($result['code'])) {
                    return $result['code'];
                }
            }
        } else {
            return null;
        }
    }

    public function findAttrTypeInDB($code)
    {
        $attributeRepo = $this->attributeRepo;

        $results = $attributeRepo->createQueryBuilder('a')
                    -> select('a.type as type')
                    -> where('a.code = :code')
                    -> setParameter('code', $code)
                    -> getQuery()->getResult();
        if ($results) {
            foreach ($results as $result) {
                if (!empty($result['type'])) {
                    return $result['type'];
                }
            }
        } else {
            return null;
        }
    }

    // Formate Value according to akeneo
    public function formateValue($attributeCode, $value, $locale, $scope)
    {
        $results = $this->getAttributeTypeLocaleAndScope($attributeCode);
        $localizable = isset($results[0]['localizable']) ? $results[0]['localizable'] : null;
        $scopable = isset($results[0]['scopable']) ? $results[0]['scopable'] : null ;
        $type = isset($results[0]['type']) ? $results[0]['type'] : null ;
        $result = null;
        
        if ($type === "pim_catalog_price_collection") {
            $result= [
                array(
                    'locale' => $localizable ? $locale : null,
                    'scope' => $scopable ? $scope : null,
                    'data' => [
                        array(
                            'amount' => isset($value) ? $value : 0,
                            'currency' => 'USD' //will be change
                        )
                    ]
                )
            ];
        } elseif ($type === "pim_catalog_metric") {
            $defaultUnit =  isset($results[0]['defaultMetricUnit']) ? $results[0]['defaultMetricUnit'] : null ;
            $metricFamily = isset($results[0]['metricFamily']) ? $results[0]['metricFamily'] : null ;

            if ($metricFamily === 'Weight') {
                $result= [
                    array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => array(
                                'amount' => isset($value) ? $value : 0,
                                'unit' => $defaultUnit
                            ),
                    )
                ];
            } else {
                $value = explode(' ', $value);
                $result= [
                    array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' =>  array(
                                    'amount' => isset($value[0]) ? $value[0]  : 0,
                                    'unit' => isset($value[1]) ? $value[1]  : $defaultUnit,
                                ),
                        
                    )
                ];
            }
        } elseif (in_array($type, ['pim_catalog_multiselect', 'pim_catalog_simpleselect'])) {
            if ($type === "pim_catalog_multiselect") {
                $value = is_array($value) ? $value : explode(',', $value);

                foreach ($value as $id) {
                    $options[] = $this->searchOptionsValueByExternalId($id, $attributeCode);
                }
            } else {
                $value = is_array($value) ? reset($value) : $value;
                $options = $this->searchOptionsValueByExternalId($value, $attributeCode);
            }

            if (!empty($options)) {
                $result= [
                    array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => isset($options) ? $options : null
                    )
                ];
            }
        } elseif (in_array($type, ['pim_catalog_text','pim_catalog_textarea' ])) {
            if (isset($value)) {
                if (!is_array($value)) {
                    $value = (string)$value;
                }
                $result= [
                    array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => isset($value) ? $value : null
                    )
                ];
            }
        } else {
            if (isset($value)) {
                $result= [
                    array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => isset($value) ? $value : null
                    )
                ];
            }
        }

        return $result;
    }

    // get Attribute By Local and Scope
    public function getAttributeTypeLocaleAndScope($field)
    {
        $attributeRepo = $this->attributeRepo;

        $results = $attributeRepo->createQueryBuilder('a')
                ->select('a.code, a.type, a.localizable as localizable, a.scopable as scopable, a.defaultMetricUnit as defaultMetricUnit, a.metricFamily as metricFamily')
                ->where('a.code = :code')
                ->setParameter('code', $field)
                ->getQuery()->getResult();
        
        return $results;
    }
    
    public function searchOptionsValueByExternalId($externalId, $attributeCode)
    {
        $option = null;
        $results = $this->em->createQueryBuilder()
                -> select('d.code as code')
                -> from('Magento2Bundle:DataMapping', 'd')
                -> where('d.externalId = :id')
                -> andWhere('d.entityType = :entity')
                -> andWhere('d.code LIKE :code')
                -> setParameters([
                    'id' => $externalId,
                    'entity' => 'option',
                    'code' => '%' . addcslashes($attributeCode, '%_') . '%'
                ])
                -> getQuery()->getResult();
                 
        foreach ($results as $result) {
            $option = !empty($result['code']) ? explode("(", $result['code'])[0] : '';
            break;
        }
        
        return $option;
    }

    public function searchOptionsValueByCode($code, $attributeCode)
    {
        $option = null;
        $results = $this->em->createQueryBuilder()
                -> select('d.externalId as externalId')
                -> from('Magento2Bundle:DataMapping', 'd')
                -> andWhere('d.entityType = :entity')
                -> andWhere('d.code LIKE :code')
                -> setParameters([
                    'entity' => 'option',
                    'code' => $code.'('.$attributeCode.')',
                ])
                -> getQuery()->getResult();
                 
        foreach ($results as $result) {
            $option = !empty($result['externalId']) ? explode("(", $result['externalId'])[0] : '';
            break;
        }
        
        return $option;
    }
    
    public function getMappingByEntity($entity, $jobInstance)
    {
        $apiUrl = $this->getApiUrl();
        
        $results = $this->em->createQueryBuilder()
                 -> select('d.code, d.relatedId, d.extras')
                 -> from('Magento2Bundle:DataMapping', 'd')
                 -> where('d.entityType = :entity')
                 -> andWhere('d.jobInstanceId = :jobInstance')
                 -> andWhere('d.apiUrl = :apiUrl')
                 -> setParameters(
                     [
                        'entity' => $entity,
                        'jobInstance' => $jobInstance,
                        'apiUrl' => $apiUrl
                     ]
                 )
                 -> getQuery()->getResult();
      
        return $results;
    }


    public function removeAllMappingByEntity($entity, $jobInstance)
    {
        $apiUrl = $this->getApiUrl();
        
        $results = $this->em->createQueryBuilder()
                 -> delete()
                 -> from('Magento2Bundle:DataMapping', 'd')
                 -> where('d.entityType = :entity')
                 -> andWhere('d.jobInstanceId != :jobInstance')
                 -> setParameters(
                     [
                        'entity' => $entity,
                        'jobInstance' => $jobInstance
                     ]
                 )
                 -> getQuery()->getResult();
      
        return $results;
    }

    public function getMergeMappings()
    {
        // fetch attribute Mapping
        $attributeMappings = $this->getAttributeMappings();

        //fetch other Mapping
        $otherMapping = $this->getOtherMappings();
        $finalOtherMapping = [];
        if (!empty($otherMapping['import_custom_fields'])) {
            foreach ($otherMapping['import_custom_fields'] as $key => $value) {
                $finalOtherMapping[$value] = $value;
            }
        }
        //merge attribute and other mapping
        $attributeMappings = array_merge($attributeMappings, $finalOtherMapping);

        return $attributeMappings;
    }

    public function getOptionLabelByAttributeCodeAndLocale($attribute, $code, $locale)
    {
        $attributeRepo = $this->attributeOptionRepo;
                
        $attrCode = $attribute . '.' . $code;
        if (empty($this->attributeOptions[$attrCode])) {
            $this->attributeOptions[$attrCode] = $attributeRepo->findOneByIdentifier($attrCode);
        }

        $attributeOption = $this->attributeOptions[$attrCode];
        if ($attributeOption) {
            $attributeOption->setLocale($locale);

            return $attributeOption->__toString() !== '[' . $code . ']' ? $attributeOption->__toString() : $code;
        }
    }

    public function getAttributeLabelByCodeAndLocale($code, $locale)
    {
        if (empty($this->attributeLabels[$code])) {
            $attributeRepo = $this->attributeRepo;
            $result = $attributeRepo->createQueryBuilder('att')
                    ->select('trans.label')
                    ->leftJoin('att.translations', 'trans')
                    ->andWhere('trans.locale = :locale')
                    ->andWhere('att.code = :code')
                    ->setParameters(['locale' => $locale,'code' => $code])
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

            $label = isset($result['label']) ? $result['label'] : $code;
            $label = $label !== '[' . $code . ']' ? $label : $code;
        }
        
        $this->attributeLabels[$code] = $label ?? $code;
        
        return $this->attributeLabels[$code];
    }

    public function getImageContentByPath($path)
    {
        return $this->fileSystemProvider->getFilesystem('catalogStorage')
                ->read($path);
    }

    public function getCategoryByCode($categoryCode)
    {
        return $this->categoryRepo->findOneByCode($categoryCode);
    }

    public function getAllAkeneoCategories()
    {
        $categoryData = [];
        $akeneoCategories = $this->categoryRepo->createQueryBuilder('c')
                            ->select('c.code, trans.label')
                            ->leftJoin('c.translations', 'trans')
                            ->getQuery()->getResult();

        if (!empty($akeneoCategories)) {
            foreach ($akeneoCategories as $category) {
                $categoryData[strtolower($this->formatUrlKey($category['label']))] = $category['code'];
            }
        }
        
        return $categoryData;
    }

    public function getLocalizableOrScopableAttributes()
    {
        $attributeRepo = $this->attributeRepo;
        $results = $attributeRepo->createQueryBuilder('a')
                ->select('a')
                ->orWhere('a.scopable = :one')
                ->orWhere('a.localizable = :one')
                ->setParameter('one', 1)
                ->getQuery()->getResult();

        return $results;
    }

    public function getPriceAttributes()
    {
        $attributeRepo = $this->attributeRepo;
        return $attributeRepo->getAttributeCodesByType('pim_catalog_price_collection');
    }

    public function getAttributeAndTypes()
    {
        $attributeRepo = $this->attributeRepo;
        
        $results = $attributeRepo->createQueryBuilder('a')
            ->select('a.code, a.type')
            ->getQuery()
            ->getArrayResult();

        $attributes = [];
        if (!empty($results)) {
            foreach ($results as $attribute) {
                $attributes[$attribute['code']] = $attribute['type'];
            }
        }

        return $attributes;
    }

    public function getAttributeAsImageByFamily($familyCode)
    {
        $family = $this->familyRepo->findOneByCode($familyCode);

        return $family && $family->getAttributeAsImage() ? $family->getAttributeAsImage()->getCode() : null;
    }

    public function createFamilyVariant($content)
    {
        $familyVariant = $this->familyVariantFactory->create();
        $this->familyVariantUpdater->update($familyVariant, $content);
        $violations = $this->validator->validate($familyVariant);
        $normalizedViolations = [];
        foreach ($violations as $violation) {
            $normalizedViolations[] = $this->normalizedViolations->normalize(
                $violation,
                'internal_api',
                ['family_variant' => $familyVariant]
            );
        }

        if (count($violations) > 0) {
            return new JsonResponse($normalizedViolations, 400);
        }
        
        $this->familyVariantSaver->save($familyVariant);

        return new JsonResponse(
            $this->apiSerializer->normalize(
                $familyVariant,
                'internal_api'
            )
        );
    }

    public function getApiUrl()
    {
        $credentials = $this->getCredentials();
        $apiUrl = array_key_exists('hostName', $credentials) ? rtrim($credentials['hostName'], '/') : '';
        $apiUrl = str_replace('https://', 'http://', $apiUrl);

        return $apiUrl;
    }

    public function checkExistProduct($code, $type)
    {
        if ($type == 'configurable') {
            $entity = $this->productModelRepo->findOneByIdentifier($code);
            if (!empty($entity)) {
                return true;
            } else {
                return false;
            }
        }

        if ($type == 'simple') {
            $entity = $this->productRepo->findOneByIdentifier($code);
            
            if (!empty($entity)) {
                return true;
            } else {
                return false;
            }
        }
    }

    public function findFamilyVariantCode($productCode)
    {
        $result = $this->productModelRepo->createQueryBuilder('p')
                        ->select('f.code')
                        ->leftJoin('p.familyVariant', 'f')
                        ->where('p.code = :code')
                        ->setParameter('code', $productCode)
                        ->getQuery()->getResult();
        if (isset($result[0])) {
            return $result[0]['code'] ? $result[0]['code'] : null;
        }
    }

    public function getFamilyAttributesByCode($code)
    {
        $attributeCodes = [];
        $family = $this->familyRepo->findOneBy(['code' => $code]);

        if (null === $family) {
            throw new NotFoundHttpException(
                sprintf('Family with code %s does not exist.', $code)
            );
        }
        //normalize family data
        $familyNormalize = $this->apiSerializer->normalize(
            $family,
            'internal_api',
            ['apply_filters' => true]
        );
        $attributeGroupsToFetch = array_unique(array_column($familyNormalize['attributes'], 'group'));

        $attributes = $family->getAttributeCodes();
        $results = $this->attributeRepo->createQueryBuilder('a')
                    ->select('a.code, g.code as gcode')
                    ->leftJoin('a.group', 'g')
                    ->addOrderBy('g.sortOrder', 'ASC')
                    ->addOrderBy('a.sortOrder', 'ASC')
                    ->addOrderBy('a.id', 'ASC')
                    ->where('a.code IN (:attributeCodes)')
                    ->setParameter('attributeCodes', $attributes)
                    ->getQuery()
                    ->getResult();

        //result create as group code with attribute code
        if ($results) {
            foreach ($results as $result) {
                if (isset($result['code'])) {
                    $attributeCodes[$result['code']] = $result['gcode'] ;
                }
            }
        }
        //sort group wise
        $sortedAttributeCodes = [];
        foreach ($attributeGroupsToFetch as $group) {
            foreach ($attributeCodes as $attribute => $attributeGroup) {
                if (strcasecmp($group, $attributeGroup) == 0) {
                    $sortedAttributeCodes[] = $attribute;
                }
            }
        }
        
        return $sortedAttributeCodes;
    }

    public function updateFamilyAttributes($family, $attributes, $channelCode = "ecommerce")
    {
        if ($family) {
            $attributes_requirements = [];
            
            foreach ($family->getAttributeRequirements() as $attribute_require) {
                $attributes_requirements[] = $attribute_require->getAttribute()->getCode();
            }
            
            $attributes_requirementsByChannel = [$channelCode => $attributes_requirements];
            
            $data = array(
                'code' => $family->getCode(),
                'attributes' => array_merge(
                    $attributes,
                    $family->getAttributeCodes()
                ),
                'attribute_as_label' => !empty($family->getAttributeAsLabel()) ? $family->getAttributeAsLabel()->getCode() : null,
                'attribute_as_image' => !empty($family->getAttributeAsImage()) ? $family->getAttributeAsImage()->getCode() : null,
                'attribute_requirements' => $attributes_requirementsByChannel,
            );

            try {
                $this->familyUpdater->update($family, $data);
                $this->familySaver->save($family);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        } else {
            throw new \Exception("Family Not Found");
        }
    }

    public function getChildProductsByProductModelCode($productModelCode, $variantAttributes)
    {
        $model = $this->productModelRepo->findOneByIdentifier($productModelCode);
        $childs = $this->productModelRepo->findChildrenProducts($model);
        $attributeRepo = $this->attributeRepo;
        $data = [];
        foreach ($childs as $key => $child) {
            $variantAttributes = array_merge(['sku'], $variantAttributes);
            
            $childData = [];
            foreach ($variantAttributes as $attribute) {
                $val = $child->getValue($attribute);
                if (!$val && $attribute === 'sku') {
                    $value = $child->getIdentifier();
                } else {
                    $value = $val->__toString();

                    $attributeObject = $attributeRepo->findOneByIdentifier($attribute);
                    if ($attributeObject->getType() === 'pim_catalog_metric' && $val->hasData()) {
                        $valueData = $val->getData();
                        $value =  $valueData->getData() . ' ' . $valueData->getUnit();
                    }
                }
                
                $childData[$attribute] = strpos($value, '[') === 0  ? trim($value, '[]') : $value;
            }
            
            $data[] = $childData;
        }

        return $data;
    }

    public function generateUrl($url, $params, $absolute)
    {
        $credentials = $this->getCredentials();
        if (isset($credentials['host'])) {
            $this->router->getContext()->setHost($credentials['host']);
            $this->router->getContext()->setBaseUrl('');
        }
        if (isset($credentials['scheme'])) {
            $this->router->getContext()->setScheme($credentials['scheme']);
        }

        return $this->router->generate($url, $params, $absolute);
    }


    public function findModelByIdentifier($sku)
    {
        return $this->productModelRepo->findOneByIdentifier($sku);
    }

    public function findAttributeByIdentifier($identifier)
    {
        return $this->attributeRepo->findOneByIdentifier($identifier);
    }

    public function getSetterByAttributeCode($identifier)
    {
        return $this->updaterSetterRegistery->getSetter($identifier);
    }

    public function saveProductModel(\ProductModelInterface $model)
    {
        $this->em->persist($model);
        $this->em->flush();
    }

    public function saveProduct($model)
    {
        $this->em->persist($model);
        $this->em->flush();
    }

    public function getProductModelsByNamePrefix($namePrefix)
    {
        $results = $this->productModelRepo->createQueryBuilder('p')
                   ->select('p.code')
                   ->andWhere('p.code like :namePrefix')
                   ->setParameter('namePrefix', $namePrefix . '%')
                   ->getQuery()->getArrayResult();

        return array_filter(
            array_map([$this, 'getCode'], $results)
        );
    }

    /**
     * Return the Code index from the array
     * @param var array
     *
     * @return var string
     */
    private function getCode($p)
    {
        return $p['code'];
    }


    /**
     *  Validate the Credentials
     */
    public function validateCredentials()
    {
        $credentials = $this->getCredentials();
        $storeView = $this->checkCredentialAndGetStoreViews($credentials);
        
        if (!$storeView || isset($storeView['error'])) {
            $errorMessage = $storeView['error'] ?? 'Invalid Credentials. Check if credential is Enabled and Valid.';
            throw new \Exception(sprintf('%s', $errorMessage));
        }
        
        return $storeView;
    }

    public function findProductByIdentifier($identifier)
    {
        return $this->productRepo->findOneByIdentifier($identifier);
    }

    public function findProductModelByIdentifier($identifier)
    {
        return $this->productModelRepo->findOneByIdentifier($identifier);
    }

    public function deleteFamilyRelatedMappingsByCodeEntityAndUrl($code, $entity, $url)
    {
        $result = $this->em->createQueryBuilder()
            ->delete("Magento2Bundle:DataMapping", "m")
            ->andWhere("m.code like :code")
            ->andWhere("m.entityType = :entityType")
            ->andWhere("m.apiUrl = :url")
            ->setParameters([
                 "code" => '%-' . $code,
                 "entityType" => $entity,
                 "url" => $url,
             ])->getQuery()->getResult();
    }

    public function getMappingByCode($code, $entityName)
    {
        $qb = $this->em->createQueryBuilder()
            ->select("m")
            ->from("Magento2Bundle:DataMapping", "m")
            ->andWhere("m.code = :code")
            ->andWhere("m.entityType = :entityType")
            ->andWhere("m.apiUrl = :url")
            ->setParameters([
                 "code" => $code,
                 "entityType" => $entityName,
                 "url" => $this->getApiUrl(),
             ]);
        $result = $qb->getQuery()->getResult();

        return $result[0] ?? null;
    }

    public function getEntityTrackByEntityAndCode($entity, $code)
    {
        $repo = $this->em->getRepository('Magento2Bundle:EntityTracker');
        $track = $repo->findOneBy(['entityType' => $entity, 'code' => $code]);

        return $track;
    }

    public function removeTrack($track)
    {
        if ($track && $track instanceof \Webkul\Magento2Bundle\Entity\EntityTracker) {
            $this->em->remove($track);
            $this->em->flush();
        }
    }

    public function createProductMapping($identifier, $type)
    {
        $mapping = $this->em->getRepository('Magento2Bundle:ProductMapping')->findOneBySku($identifier);
        if (!$mapping) {
            $mapping = new ProductMapping();
        }
        $mapping->setSku($identifier);
        $mapping->setType($type);
        $this->em->persist($mapping);
        $this->em->flush();
    }

    public function getProductMapping($identifier)
    {
        return $this->em->getRepository('Magento2Bundle:ProductMapping')->findOneBySku($identifier);
    }

    public function removeProductMapping($identifier)
    {
        $mapping = $this->em->getRepository('Magento2Bundle:ProductMapping')->findOneBySku($identifier);
        
        if ($mapping) {
            $this->em->remove($mapping);
            $this->em->flush();
        }
    }
    
    public function formatUrlKey($string)
    {
        setlocale(LC_ALL, 'en_US.utf8');
        $string = str_replace(["'",' ø '], ['',' '], $string);
        try {
            $string = iconv('utf-8', 'ascii//TRANSLIT', $string);
        } catch (\Exception $e) {
            // skip charset error for character conversion
        }
        $string = preg_replace('/[^a-zA-Z0-9\']/', '-', $string);

        return $string;
    }

    /**
     * Return the all Image type attribute codes
     * @return Array $imageCodes
     */
    public function getImageAttributeCodes():array
    {
        $imageCodes = [];
        $attributeRepo = $this->attributeRepo;
        $attributeCodes = $attributeRepo->getAttributeCodesByType('pim_catalog_image');
        if ($attributeCodes) {
            $imageCodes = is_array($attributeCodes) ? $attributeCodes : [$attributeCodes];
        }

        return $imageCodes;
    }
 
    /**
     * Check the MagentoSKU and AkeneoSKU if Change Remove Product from Magento
     * @var string $sku
     */
    public function checkMappingAndRemoveMagentoProduct($sku)
    {
        $jsonHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $mapping = $this->getMappingByCode($sku, 'product');
        $magentoSKU = $mapping ? $mapping->getMagentoCode() : $sku;
        
        if ($sku !== $magentoSKU) {
            /* delete old product */
            $credentials = $this->getCredentials();
            if (isset($credentials['authToken']) && isset($credentials['hostName'])) {
                $oauthClient = new OAuthClient($credentials['authToken'], $credentials['hostName']);
                $url = $oauthClient->getApiUrlByEndpoint('getProduct');
                $url = str_replace('{sku}', urlencode($magentoSKU), $url);
                try {
                    $oauthClient->fetch($url, null, 'DELETE', $jsonHeaders);
                    $response = json_decode($oauthClient->getLastResponse(), true);
                } catch (\Exception $e) {
                    $response = [];
                }
            }
        }
    }

    /**
    * when only vendor data is present
    */
    public function getVendorByCode($code)
    {
        return $this->em->getRepository('Magento2MarketplaceBundle:Vendor')->findOneByCode($code);
    }
    
    public function getVendorReferenceSelectCode()
    {
        $attributeRepo = $this->attributeRepo;
        $qb = $attributeRepo->createQueryBuilder('att')
            ->select('att.code')
            ->andWhere('att.type = :type')
            ->andWhere('att.properties LIKE :properties')
            ->setParameter('type', 'pim_reference_data_simpleselect')
            ->setParameter('properties', '%"vendor"%')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getResult();

        return count($result) ? $result[0]['code'] : '';
    }

    /**
     * Get the Group Code Entity
     */
    public function getAttributeGroupByCode($code)
    {
        $attributeGroupRepo = $this->attributeGroupRepo;
        return $attributeGroupRepo->findOneByIdentifier($code);
    }

    public function getActiveLocales()
    {
        return $this->localeRepo->getActivatedLocaleCodes();
    }

    public function checkAttrUsedAsAxis($code)
    {
        $flag = false;
        $searchResult = $this->familyVariantRepo->createQueryBuilder('f')
                        ->leftJoin('f.variantAttributeSets', 'vas')
                        ->leftJoin('vas.axes', 'attr')
                        ->select('attr.code')
                        ->where('attr.code = :code')
                        ->setParameters(['code'=> $code])
                        ->getQuery()->getResult();
        
        if (!empty($searchResult)) {
            $flag = true;
        }
        return $flag;
    }

    public function getSelectTypeMetricAttributes($code)
    {
        $flag = false;
        $searchResult = $this->familyVariantRepo->createQueryBuilder('f')
                        ->leftJoin('f.variantAttributeSets', 'vas')
                        ->leftJoin('vas.axes', 'attr')
                        ->select('attr.code')
                        ->where('attr.code = :code')
                        ->setParameters(['code'=> $code])
                        ->getQuery()->getResult();
        return $searchResult;
    }

    public function findOptionsByAttributesCodesFromProduct($codes)
    {
        $flag = false;
        $qb = $this->productRepo->createQueryBuilder('p');
        $qb->innerJoin('p.familyVariant', 'fv')
            ->innerJoin('fv.variantAttributeSets', 'vs')
            ->innerJoin('vs.axes', 'attr')
            ->andWhere('attr.code in (:codes)')
            ->setParameter('codes', $codes)
            ->distinct(true);

        $result = $qb->getQuery()->getResult();
        return $result;
    }

    public function attributesAxesOptions()
    {
        //fetch other Mapping
        if (!$this->attributesAxesOptions) {
            $valuesForLocale = [];
            $otherMapping = $this->getOtherMappings();
            $otherSettings = $this->getSettings();

            $exportAttributeCodes = $otherMapping['custom_fields'] ?? [];

            $qb = $this->productRepo->createQueryBuilder('p');
            $qb->select('attr.code', 'p.id')
                ->innerJoin('p.family', 'fv')
                ->innerJoin('fv.attributes', 'attr')
                ->where('attr.type = :type')
                ->andwhere('attr.code in (:codes)')
                ->setParameter('type', 'pim_catalog_metric')
                ->setParameter('codes', $exportAttributeCodes)
                ->distinct(true);
            $variantProductsAxes = $qb->getQuery()->getResult();
            foreach ($variantProductsAxes as $variantProductAxe) {
                if (isset($variantProductAxe['id']) && isset($variantProductAxe['code'])) {
                    $entity = $this->productRepo->findOneById($variantProductAxe['id']);
                    $value  = $entity->getValue($variantProductAxe['code']);
                    if ($value) {
                        $option = $value->getData();
                        $value = $option->getData();
                        $unit = $option->getUnit();
    
                        //initialise array
                        if (!isset($valuesForLocale[$variantProductAxe['code']])) {
                            $valuesForLocale[$variantProductAxe['code']] = [];
                        }
                    
                        if (isset($otherSettings['metric_selection'])) {
                            $unit = $otherSettings['metric_selection'] == "true" ? $unit: "";
                        } else {
                            $unit = "";
                        }

                        $valuesForLocale[$variantProductAxe['code']] = array_unique(array_merge($valuesForLocale[$variantProductAxe['code']], [round($value, 2) . ' ' . $unit]));
                    }
                }
            }

            $this->attributesAxesOptions = $valuesForLocale;
        }

        return $this->attributesAxesOptions;
    }

    public function matchRootCategoryCodeInDbByLabel($label, $parent = null)
    {
        $parentId = null;
        
        if ($parent) {
            $parentCategory = $this->categoryRepo->findOneByCode($parent);
            if ($parentCategory) {
                $parentId = $parentCategory->getId();
            }
        }

        $nativeQuery = $this->categoryRepo->createQueryBuilder('c')
                    ->select('c.code')
                    ->andWhere('trans.label = :label')
                    ->leftJoin('c.parent', 'parent')
                    ->leftJoin('c.translations', 'trans')
                    ->setParameter('label', $label);
        
        if ($parentId) {
            $nativeQuery->andWhere('parent.id = :parentId')
                    ->setParameter('parentId', $parentId);
        }
      
        $result = $nativeQuery->setMaxResults(1)->getQuery()->getOneOrNullResult();
        
        return isset($result['code']) ? $result['code'] : null;
    }

    /**
     *  To fetch the products variants attributes by identifier
     */
    public function getFamilyVariantAttributs($identifier)
    {
        $qb = $this->productRepo->createQueryBuilder('p');
        $results =  $qb->innerJoin('p.familyVariant', 'fv')
                    ->select('attr.code')
                    ->innerJoin('fv.variantAttributeSets', 'vs')
                    ->innerJoin('vs.attributes', 'attr')
                    ->andWhere('p.identifier = :identifier')
                    ->setParameter('identifier', $identifier)
                    ->getQuery()->getResult();

        if (is_array($results)) {
            $results = array_column($results, 'code');
        }

        return $results;
    }


    public function findIdentifiersNotEmptyParent()
    {
        $productsIdentifiers = $this->productRepo->createQueryBuilder('p')
                    ->select('p.identifier')
                    ->leftJoin('p.parent', 'parent')
                    ->where('parent.code IS NOT NULL')
                    ->getQuery()->getResult();
         
        return array_column($productsIdentifiers, 'identifier');
    }

    public function findIdentifiersEmptyParent()
    {
        $productsIdentifiers = $this->productRepo->createQueryBuilder('p')
                    ->select('p.identifier')
                    ->leftJoin('p.parent', 'parent')
                    ->where('parent.code IS NULL')
                    ->getQuery()->getResult();
         
        return array_column($productsIdentifiers, 'identifier');
    }

    /**
     *  To fetch the products variants attributes by identifier
     */
    public function getVariantAxes($identifier)
    {
        $qb = $this->productRepo->createQueryBuilder('p');
        $results =  $qb->innerJoin('p.familyVariant', 'fv')
                    ->select('attr.code')
                    ->innerJoin('fv.variantAttributeSets', 'vs')
                    ->innerJoin('vs.axes', 'attr')
                    ->andWhere('p.identifier = :identifier')
                    ->setParameter('identifier', $identifier)
                    ->getQuery()->getResult();

        if (is_array($results)) {
            $results = array_column($results, 'code');
        }

        return $results;
    }

    /**
     * To fetch the familyvairnat code
     */
    public function getFamilyVariantCode($identifier)
    {
        $qb = $this->productRepo->createQueryBuilder('p');
        $results =  $qb->innerJoin('p.familyVariant', 'fv')
                    ->select('fv.code')
                    ->andWhere('p.identifier = :identifier')
                    ->setParameter('identifier', $identifier)
                    ->getQuery()->getResult();

        if (is_array($results)) {
            $results = array_column($results, 'code');
        }

        return is_array($results) ? reset($results) : null;
    }
    
    public function getAttrVisualSwatchImageURL($attributeCode, $optionCode)
    {
        $imageURL = null;
        if ($this->container->hasParameter(self::SUPPORT_SWATCH_IMAGES) && in_array($host, $this->container->getParameter(self::SUPPORT_SWATCH_IMAGES))) {
            $results =  $this->attributeOptionRepo->createQueryBuilder('ao')
                        ->innerJoin('ao.attribute', 'a')
                        ->select('ao.image')
                        ->andWhere('ao.code = :optionCode')
                        ->andWhere('a.code = :attrCode')
                        ->andWhere('a.type = :type')
                        ->setParameters(['attrCode'=> $attributeCode, 'optionCode' => $optionCode, 'type' => 'pim_catalog_simpleselect'])
                        ->setMaxResults(1)
                        ->getQuery()->getOneOrNullResult();
                        
            if (is_array($results) && isset($results['image']['filePath'])) {
                $imageURL = $this->generateImageUrl($results['image']['filePath']);
            }
        }

        return $imageURL;
    }

    public function generateImageUrl($filename, $host = null)
    {
        $filename = urldecode($filename);
        $credentials = $this->getCredentials();
        
        $host = !empty($credentials['host']) ? $credentials['host'] : null;
        $scheme = !empty($credentials['scheme']) ? $credentials['scheme'] : 'http';
        if ($host) {
            $context = $this->router->getContext();
            $context->setHost($host);
            $context->setScheme($scheme);
        }
        $request = new Request();
        try {
            $url = $this->router->generate('pim_enrich_media_show', [
                                        'filename' => urlencode($filename),
                                        'filter'=>'preview'
                                     ], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Exception $e) {
            $url  = '';
        }

        return $url;
    }

    public function closeDoctrineConnections()
    {
        if ($this->container->hasParameter('doctrine.connections') && !($this->container->hasParameter('doctrine.keep_connections') && $this->container->getParameter('doctrine.keep_connections') === true)) {
            foreach ($this->container->getParameter('doctrine.connections') as $id) {
                if (!$this->container instanceof IntrospectableContainerInterface || $this->container->initialized($id)) {
                    $this->container->get($id)->close();
                }
            }
        }
    }

    public function downloadableFileUrl($fileName)
    {
        $router = $this->container->get('router');
        $context = $router->getContext();
        $url = $router->generate('pim_enrich_media_download', [
            'filename' => urlencode($fileName)
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        
        return $url;
    }

    public function getDeletedCategoryFromAkeneo()
    {
        $repo = $this->em->getRepository('Magento2Bundle:EntityTracker');
        $tracks = $repo->findBy(['entityType' => 'category', 'action' => 'delete']);
        
        return $tracks;
    }

    public function convertAssetToPath($attrValue)
    {
        foreach ($attrValue as $key => $values) {
            $damService = $this->container->get('webkul_dam.repository.asset');
            $assets = $damService->getArrayResultsByIdentifiers($values['data']);
            $medias = array_column($assets, 'medias');
            $imagePath = [];
            if (empty($medias)) {
                $medias = array_column($assets, 'filePath');
                $imagePath = array_filter($medias);
            } else {
                foreach ($medias as $media) {
                    $images = array_column($media, 'filePath');
                    $imagePath = array_merge($imagePath, $images);
                }
            }

            $attrValue[$key]['data'] = $imagePath;
        }

        return $attrValue;
    }

    public function getAssetAttributeCodes()
    {
        return $this->attributeRepo->getAttributeCodesByType(
            'pim_catalog_asset_group'
        );
    }
    
    public function getTypeOfAttribute($mediaAttribute)
    {
        $attrType = false;
        $repo = $this->attributeRepo->findOneByIdentifier($mediaAttribute);

        if ($repo) {
            $attrType = $repo->getType();
        }

        return $attrType;
    }
    /**
    * @var string $param
    *
    * @return bool
    * */
    public function isSupportFor($param)
    {
        if ($this->container->hasParameter($param) && $this->container->getParameter($param)) {
            return true;
        }

        return false;
    }

    /**
    * Return the channel Locles codes
    * @param string $code
    * @return array $localeCodes
    */
    public function getChannelLocales(string $code):array
    {
        $channel = $this->findChannelByIdentifier($code);
        $localeCodes = [];

        if ($channel) {
            $localeCodes = $channel->getLocaleCodes();
        }

        return $localeCodes;
    }
}
