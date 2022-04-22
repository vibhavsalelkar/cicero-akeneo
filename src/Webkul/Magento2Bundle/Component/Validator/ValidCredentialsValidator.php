<?php

namespace Webkul\Magento2Bundle\Component\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Webkul\Magento2Bundle\Component\Validator\ValidCredentials;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Services\Magento2Connector;

/**
 * @author webkul
 */
class ValidCredentialsValidator extends ConstraintValidator
{
    private $requiredKeys = ['hostName', 'authToken'];
    const SECTION = 'magento2_connector';
    
    protected $connectorService;
    /**
     * {@inheritdoc}
     */
    public function __construct(Magento2Connector $connectorService)
    {
        $this->connectorService = $connectorService;
    }

    public function validate($singleValue, Constraint $constraint= null)
    {
        $value =  $this->context->getRoot();
        if (!empty($value['filters'])) {
            unset($value['filters']);
        }
        if (!empty($value['with_media'])) {
            unset($value['with_media']);
        }

        if (!$constraint instanceof ValidCredentials) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\CredentialValidator');
        }
        
        if (!empty($value['hostName']) && !empty($value['authToken'])) {
            $response = $this->fetchStoreApi($value);
            
            $successFlag = $response;
        }
        if (!empty($value['hostName']) && $value['authToken'] === str_repeat("*", 20)) {
            $data = $this->connectorService->getCredentials();
            $value['authToken'] = !empty($data["authToken"]) ? $data["authToken"] : '';
            $response = $this->fetchStoreApi($value);
            
            $successFlag = $response;
        } elseif (empty($value['hostName']) && empty($value['authToken'])) {
            /* empty credentials */
            $successFlag = true;
        }
        
        if (empty($successFlag)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(ValidCredentials::INVALID_CREDENTIAL)
                ->addViolation();
        }

        
        if (!empty($successFlag) &&  $successFlag !== true && empty($value['storeMapping'])) {
            $this->context->buildViolation($constraint->message2)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode("Fetch Store View")
                ->addViolation();
        }
        unset($successFlag);
        return true;
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


    private function fetchStoreApi($params)
    {
        try {
            $oauthClient = new OAuthClient($params['authToken'], $params['hostName']);
            $url = $params['hostName'] . '/rest/V1/store/storeViews';

            $results = $oauthClient->fetch($url, [], 'GET', ['Content-Type' => 'application/json', 'Accept' => 'application/json']);
        } catch (\Exception $e) {
            $results = null;
        }
        return $results;
    }
}
