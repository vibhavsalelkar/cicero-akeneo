<?php

namespace Webkul\Magento2Bundle\Entity;

/**
 * CredentialConfig
 */
class CredentialConfig
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $hostName;

    /**
     * @var string
     */
    private $authToken;

    /**
     * @var string
     */
    private $resources;

    /**
     * @var boolean
     */
    private $defaultSet = 0;

    /**
     * @var boolean
     */
    private $active = 0;


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
     * Set hostName
     *
     * @param string $hostName
     *
     * @return CredentialConfig
     */
    public function setHostName($hostName)
    {
        $this->hostName = $hostName;

        return $this;
    }

    /**
     * Get hostName
     *
     * @return string
     */
    public function getHostName()
    {
        return $this->hostName;
    }

    /**
     * Set authToken
     *
     * @param string $authToken
     *
     * @return CredentialConfig
     */
    public function setAuthToken($authToken)
    {
        $this->authToken = $authToken;

        return $this;
    }

    /**
     * Get authToken
     *
     * @return string
     */
    public function getAuthToken()
    {
        return $this->authToken;
    }

    /**
     * Set resources
     *
     * @param string $resources
     *
     * @return CredentialConfig
     */
    public function setResources($resources)
    {
        $this->resources = $resources;

        return $this;
    }

    /**
     * Get resources
     *
     * @return string
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * Set defaultSet
     *
     * @param boolean $defaultSet
     *
     * @return CredentialConfig
     */
    public function setDefaultSet($defaultSet)
    {
        $this->defaultSet = $defaultSet;

        return $this;
    }

    /**
     * Get defaultSet
     *
     * @return boolean
     */
    public function getDefaultSet()
    {
        return $this->defaultSet;
    }

    /**
     * Set active
     *
     * @param boolean $active
     *
     * @return CredentialConfig
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * Get active
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }
}
