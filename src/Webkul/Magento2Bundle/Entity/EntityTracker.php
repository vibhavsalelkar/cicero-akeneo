<?php

namespace Webkul\Magento2Bundle\Entity;

/**
 * EntityTracker
 */
class EntityTracker
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
    private $action;

    /**
     * @var \DateTime
     */
    private $lastUpdatedAt;


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
     * @return EntityTracker
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
     * @return EntityTracker
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
     * Set action
     *
     * @param string $action
     *
     * @return EntityTracker
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get action
     *
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set lastUpdatedAt
     *
     * @param \DateTime $lastUpdatedAt
     *
     * @return EntityTracker
     */
    public function setLastUpdatedAt($lastUpdatedAt)
    {
        $this->lastUpdatedAt = $lastUpdatedAt;

        return $this;
    }

    /**
     * Get lastUpdatedAt
     *
     * @return \DateTime
     */
    public function getLastUpdatedAt()
    {
        return $this->lastUpdatedAt;
    }

    /**
     * @ORM\PrePersist
     */
    public function setLastUpdatedAtValue()
    {
        $this->lastUpdatedAt = new \DateTime();
    }
}
