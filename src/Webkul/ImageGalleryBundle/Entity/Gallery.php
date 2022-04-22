<?php

namespace Webkul\ImageGalleryBundle\Entity;

/**
 * Gallery
 */
class Gallery
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $code;

    /**
     * @var bool|null
     */
    private $starred = false;

    /**
     * @var string|null
     */
    private $title;

    /**
     * @var string|null
     */
    private $galleryGroup;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var string|null
     */
    private $alt;

    /**
     * @var \DateTime
     */
    private $createdAt;

    /**
     * @var \DateTime|null
     */
    private $updatedAt;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $medias;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $tags;

    /**
     * @var \DateTime|null
     */
    private $expiration_date;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->medias = new \Doctrine\Common\Collections\ArrayCollection();
        $this->tags = new \Doctrine\Common\Collections\ArrayCollection();

    }

    /**
     * Add tag
     *
     * @param \Webkul\ImageGalleryBundle\Entity\GalleryTag $tag
     *
     * @return Asset
     */
    public function addTag(\Webkul\ImageGalleryBundle\Entity\GalleryTag $tag)
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Remove tag
     *
     * @param \Webkul\ImageGalleryBundle\Entity\GalleryTag $tag
     */
    public function removeTag(\Webkul\ImageGalleryBundle\Entity\GalleryTag $tag)
    {
        $this->tags->removeElement($tag);
    }

    /**
     * Get tags
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set expirationDate.
     *
     * @param \DateTime|null $expirationDate
     *
     * @return Asset
     */
    public function setExpirationDate($expirationDate = null)
    {
        $this->expiration_date = $expirationDate;

        return $this;
    }

    /**
     * Get expirationDate.
     *
     * @return \DateTime|null
     */
    public function getExpirationDate()
    {
        return $this->expiration_date;
    }
    /**
     * @var string
     */


    /**
     * Set code.
     *
     * @param string $code
     *
     * @return Gallery
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }


    /**
     * Set galleryGroup.
     *
     * @param string $galleryGroup
     *
     * @return Gallery
     */
    public function setGalleryGroup($galleryGroup)
    {
        $this->galleryGroup = $galleryGroup;

        return $this;
    }

    /**
     * Get galleryGroup.
     *
     * @return string
     */
    public function getGalleryGroup()
    {
        return $this->galleryGroup;
    }

    /**
     * Set starred.
     *
     * @param bool|null $starred
     *
     * @return Gallery
     */
    public function setStarred($starred = null)
    {
        $this->starred = $starred;

        return $this;
    }

    /**
     * Get starred.
     *
     * @return bool|null
     */
    public function getStarred()
    {
        return $this->starred;
    }

    /**
     * Set description.
     *
     * @param string|null $title
     *
     * @return Gallery
     */
    public function setDescription($description = null)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string|null
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set title.
     *
     * @param string|null $title
     *
     * @return Gallery
     */
    public function setTitle($title = null)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string|null
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set alt.
     *
     * @param string|null $alt
     *
     * @return Gallery
     */
    public function setAlt($alt = null)
    {
        $this->alt = $alt;

        return $this;
    }

    /**
     * Get alt.
     *
     * @return string|null
     */
    public function getAlt()
    {
        return $this->alt;
    }

    /**
     * Set createdAt.
     *
     * @param \DateTime $createdAt
     *
     * @return Gallery
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt.
     *
     * @param \DateTime|null $updatedAt
     *
     * @return Gallery
     */
    public function setUpdatedAt($updatedAt = null)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt.
     *
     * @return \DateTime|null
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Add media.
     *
     * @param \Webkul\ImageGalleryBundle\Entity\GalleryMedia $media
     *
     * @return Gallery
     */
    public function addMedia(\Webkul\ImageGalleryBundle\Entity\GalleryMedia $media)
    {
        $this->medias[] = $media;

        return $this;
    }

    /**
     * Remove media.
     *
     * @param \Webkul\ImageGalleryBundle\Entity\GalleryMedia $media
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeMedia(\Webkul\ImageGalleryBundle\Entity\GalleryMedia $media)
    {
        return $this->medias->removeElement($media);
    }

    /**
     * Get medias.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getMedias()
    {
        return $this->medias;
    }

    public function setCreatedAtValue()
    {
        $this->createdAt = new \DateTime();
        // Add your code here
    }


    public function setUpdatedAtValue()
    {
        $this->updatedAt = new \DateTime();
        // Add your code here
    }
    /**
     * @var string|null
     */
    private $animationType;


    /**
     * Set animationType.
     *
     * @param string|null $animationType
     *
     * @return Gallery
     */
    public function setAnimationType($animationType = null)
    {
        $this->animationType = $animationType;

        return $this;
    }

    /**
     * Get animationType.
     *
     * @return string|null
     */
    public function getAnimationType()
    {
        return $this->animationType;
    }

}
