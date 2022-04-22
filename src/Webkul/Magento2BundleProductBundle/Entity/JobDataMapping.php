<?php

namespace Webkul\Magento2BundleProductBundle\Entity;

/**
 * JobDataMapping
 */
class JobDataMapping
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $productIdentifier;

    /**
     * @var string
     */
    private $mappingType;

    /**
     * @var array
     */
    private $extras;

    /**
     * @var string
     */
    private $jobInstanceId;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set productIdentifier
     *
     * @param string $productIdentifier
     *
     * @return JobDataMapping
     */
    public function setProductIdentifier($productIdentifier)
    {
        $this->productIdentifier = $productIdentifier;

        return $this;
    }

    /**
     * Get productIdentifier
     *
     * @return string
     */
    public function getProductIdentifier()
    {
        return $this->productIdentifier;
    }

    /**
     * Set mappingType
     *
     * @param string $mappingType
     *
     * @return JobDataMapping
     */
    public function setMappingType($mappingType)
    {
        $this->mappingType = $mappingType;

        return $this;
    }

    /**
     * Get mappingType
     *
     * @return string
     */
    public function getMappingType()
    {
        return $this->mappingType;
    }

    /**
     * Set extras
     *
     * @param array $extras
     *
     * @return JobDataMapping
     */
    public function setExtras($extras)
    {
        $this->extras = $extras;

        return $this;
    }

    /**
     * Get extras
     *
     * @return array
     */
    public function getExtras()
    {
        return $this->extras;
    }

    /**
     * Set jobInstanceId
     *
     * @param string $jobInstanceId
     *
     * @return JobDataMapping
     */
    public function setJobInstanceId($jobInstanceId)
    {
        $this->jobInstanceId = $jobInstanceId;

        return $this;
    }

    /**
     * Get jobInstanceId
     *
     * @return string
     */
    public function getJobInstanceId()
    {
        return $this->jobInstanceId;
    }
}

