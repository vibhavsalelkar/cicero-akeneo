<?php

namespace Webkul\Magento2Bundle\Entity;

/**
 * DataMapping
 */
class DataMapping
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $entityType;

    /**
     * @var string
     */
    private $code;

    /**
     * @var string
     */
    private $externalId;

    /**
     * @var string
     */
    private $relatedId;

    /**
     * @var integer
     */
    private $jobInstanceId;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set entityType
     *
     * @param string $entityType
     *
     * @return DataMapping
     */
    public function setEntityType($entityType)
    {
        $this->entityType = $entityType;

        return $this;
    }

    /**
     * Get entityType
     *
     * @return string
     */
    public function getEntityType()
    {
        return $this->entityType;
    }

    /**
     * Set code
     *
     * @param string $code
     *
     * @return DataMapping
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set externalId
     *
     * @param string $externalId
     *
     * @return DataMapping
     */
    public function setExternalId($externalId)
    {
        $this->externalId = $externalId;

        return $this;
    }

    /**
     * Get externalId
     *
     * @return string
     */
    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * Set relatedId
     *
     * @param string $relatedId
     *
     * @return DataMapping
     */
    public function setRelatedId($relatedId)
    {
        $this->relatedId = $relatedId;

        return $this;
    }

    /**
     * Get relatedId
     *
     * @return string
     */
    public function getRelatedId()
    {
        return $this->relatedId;
    }

    /**
     * Set jobInstanceId
     *
     * @param integer $jobInstanceId
     *
     * @return DataMapping
     */
    public function setJobInstanceId($jobInstanceId)
    {
        $this->jobInstanceId = $jobInstanceId;

        return $this;
    }

    /**
     * Get jobInstanceId
     *
     * @return integer
     */
    public function getJobInstanceId()
    {
        return $this->jobInstanceId;
    }
    /**
     * @var string
     */
    private $storeViewCode;


    /**
     * Set storeViewCode
     *
     * @param string $storeViewCode
     *
     * @return DataMapping
     */
    public function setStoreViewCode($storeViewCode)
    {
        $this->storeViewCode = $storeViewCode;

        return $this;
    }

    /**
     * Get storeViewCode
     *
     * @return string
     */
    public function getStoreViewCode()
    {
        return $this->storeViewCode;
    }
    /**
     * @var string
     */
    private $apiUrl;


    /**
     * Set apiUrl
     *
     * @param string $apiUrl
     *
     * @return DataMapping
     */
    public function setApiUrl($apiUrl)
    {
        $this->apiUrl = $apiUrl;

        return $this;
    }

    /**
     * Get apiUrl
     *
     * @return string
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }
    /**
     * @var string
     */
    private $extras;


    /**
     * Set extras
     *
     * @param string $extras
     *
     * @return DataMapping
     */
    public function setExtras($extras)
    {
        $this->extras = $extras;

        return $this;
    }

    /**
     * Get extras
     *
     * @return string
     */
    public function getExtras()
    {
        return $this->extras;
    }
    /**
     * @var string
     */
    private $magentoCode;


    /**
     * Set magentoCode
     *
     * @param string $magentoCode
     *
     * @return DataMapping
     */
    public function setMagentoCode($magentoCode)
    {
        $this->magentoCode = $magentoCode;

        return $this;
    }

    /**
     * Get magentoCode
     *
     * @return string
     */
    public function getMagentoCode()
    {
        return $this->magentoCode;
    }
}
