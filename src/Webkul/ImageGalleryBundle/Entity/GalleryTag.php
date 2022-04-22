<?php

namespace Webkul\ImageGalleryBundle\Entity;

/**
 * GalleryTag
 */
class GalleryTag
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $code;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    public $gallery;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->gallery = new \Doctrine\Common\Collections\ArrayCollection();
    }

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
     * Set code
     *
     * @param string $code
     *
     * @return GalleryTag
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
     * Add Gallery
     *
     * @param \Webkul\ImageGalleryBundle\Entity\Gallery $gallery
     *
     * @return GalleryTag
     */
    public function addGallery(\Webkul\ImageGalleryBundle\Entity\Gallery $gallery)
    {
        $this->gallery[] = $gallery;

        return $this;
    }

    /**
     * Remove Gallery
     *
     * @param \Webkul\ImageGalleryBundle\Entity\Gallery $gallery
     */
    public function removeGallery(\Webkul\ImageGalleryBundle\Entity\Gallery $gallery)
    {
        $this->gallery->removeElement($gallery);
    }

    /**
     * Get Gallery
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getGallery()
    {
        return $this->gallery;
    }
}
