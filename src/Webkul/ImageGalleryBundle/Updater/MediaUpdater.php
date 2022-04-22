<?php

namespace Webkul\ImageGalleryBundle\Updater;

use Akeneo\Component\StorageUtils\Exception\InvalidObjectException;
use Akeneo\Component\StorageUtils\Exception\InvalidPropertyException;
use Akeneo\Component\StorageUtils\Exception\InvalidPropertyTypeException;
use Akeneo\Component\StorageUtils\Exception\UnknownPropertyException;
use Doctrine\Common\Util\ClassUtils;
use Webkul\ImageGalleryBundle\Entity\Gallery;
use Webkul\ImageGalleryBundle\Entity\GalleryMedia;
use Webkul\ImageGalleryBundle\Repository\GalleryRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();

class MediaUpdater implements \ObjectUpdaterInterface
{
    private $em;

    protected $galleryRepository;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var PathGeneratorInterface */
    protected $pathGenerator;

    /** @var string */
    protected $uploadDir;

    /** @var FileStorer */
    private $fileStorer;

    /** @var FilesystemProvider */
    protected $filesystemProvider;

    public function __construct(
        \Doctrine\ORM\EntityManager $em,
        GalleryRepository $galleryRepository,
        ValidatorInterface $validator,
        \PathGeneratorInterface $pathGenerator,
        $uploadDir,
        \FileStorer $fileStorer,
        \FilesystemProvider $filesystemProvider
    ) {
        $this->em = $em;
        $this->galleryRepository = $galleryRepository;
        $this->validator = $validator;
        $this->pathGenerator = $pathGenerator;
        $this->uploadDir = $uploadDir->getTempStoragePath();
        $this->fileStorer = $fileStorer;
        $this->filesystemProvider = $filesystemProvider;
    }


    public function update($asset, array $data, array $options = [])
    {
        if (!$asset instanceof Gallery) {
            throw \InvalidObjectException::objectExpected(
                ClassUtils::getClass($asset),
                Gallery::class
            );
        }

        foreach ($data as $field => $value) {
            $this->validateDataType($field, $value);

            if ('code' === $field) {
                $repo = $this->galleryRepository;
                $assetic = $repo->findOneBy([
                    'code' =>  $value
                ]);
            }


            $this->setData($asset, $field, $value);
        }

        return $this;
    }

    /**
     * Validate the data type of a field.
     *
     * @param string $field
     * @param mixed  $data
     *
     * @throws InvalidPropertyTypeException
     * @throws UnknownPropertyException
     */
    protected function validateDataType($field, $data)
    {
        if ('labels' === $field) {
            if (!is_array($data)) {
                throw \InvalidPropertyTypeException::arrayExpected($field, static::class, $data);
            }

            foreach ($data as $localeCode => $label) {
                if (null !== $label && !is_scalar($label)) {
                    throw \InvalidPropertyTypeException::validArrayStructureExpected(
                        'labels',
                        'one of the labels is not a scalar',
                        static::class,
                        $data
                    );
                }
            }
        } elseif (in_array($field, [
            'code', 'alt', 'created_at', 'description', 'media', 'is_checked', 'starred', 'title', 'updated_at',
            'categories', 'tags', 'expiration_date', 'group'
        ])) {
            if (null !== $data && 'media' !== $field && !is_scalar($data)) {
                throw \InvalidPropertyTypeException::scalarExpected($field, static::class, $data);
            }
        } elseif (in_array($field, ['validations', 'options'])) {
        } else {
            throw \UnknownPropertyException::unknownProperty($field);
        }
    }

    /**
     * @param Asset $asset
     * @param string                   $field
     * @param mixed                    $data
     *
     * @throws InvalidPropertyException
     */
    protected function setData(Gallery $asset, $field, $data)
    {
        if ('code' === $field && $asset->getCode() === null) {
            $asset->setCode($data);
        }

        if ('description' === $field) {
            $asset->setDescription($data);
        }

        if ('starred' === $field) {
            $asset->setStarred($data);
        }

        if ('title' === $field) {
            $asset->setTitle($data);
        }

        if ('alt' === $field) {
            $asset->setAlt($data);
        }

        if ('tags' === $field) {
            $repo = $this->em->getRepository('ImageGalleryBundle:GalleryTag');

            if ($data) {
                $tags = explode(' & ', $data);
                $alreadyAddedTags = [];
                if (!empty($asset->getTags())) {
                    foreach ($asset->getTags() as $value) {
                        $asset->removeTag($value);
                    }
                }

                foreach ($tags as $tag) {
                    if (!in_array($tag, $alreadyAddedTags)) {
                        $result = $repo->findOneBy(["code" => $tag]);

                        if ($result) {
                            $asset->addTag($result);
                        }
                    }
                }
            }
        }

        if ('expiration_date' === $field) {
            if ($data) {
                $asset->setExpirationDate($data);
            }
        }

        if ('group' === $field) {
            $repo = $this->em->getRepository('ImageGalleryBundle:Group');
            if ($data) {
                $result = $repo->findOneBy(["code" => $data]);
                if($result) {                    
                    $asset->setGalleryGroup($data);
                }
            }
        }

        if ('media' === $field) {
            $medias = [];
            if ($asset->getMedias()) {
                $fs = $this->filesystemProvider->getFilesystem('catalogStorage');
                foreach ($asset->getMedias() as $value) {
                    $medias[] = $value->getFilePath();
                    $asset->removeMedia($value);
                }
            }
            
            if (!empty($medias)) {
                foreach ($medias as $filePath) {
                    $repo = $this->em->getRepository('ImageGalleryBundle:GalleryMedia');
                    $media = $repo->findOneBy([
                        'filePath' => $filePath
                    ]);

                    if ($media) {
                        $this->em->remove($media);
                    }
                }
            }

            foreach ($data as $files) {
                if(!$files) {
                    continue;
                }
                $filename = explode('/', $files);
                $fname = end($filename);
                
                try {
                    $file = new UploadedFile(
                        implode('/', $filename),
                        $fname,
                        mime_content_type($files),
                        filesize($files),
                        null,
                        false
                    );

                    $uploadedFile = $this->postMedia($file);
                    $repo = $this->em->getRepository('ImageGalleryBundle:GalleryMedia');
                    $media = $repo->findOneBy([
                        'filePath' => $uploadedFile['filePath'],
                        'originalFilename' => $uploadedFile['originalFilename']
                    ]);

                    if (!$media) {
                        $media = new GalleryMedia();
                    }

                    $media->setFilePath($uploadedFile['filePath']);
                    $media->setOriginalFilename($uploadedFile['originalFilename']);
                    $media->setGallery($asset);


                    $asset->addMedia($media);
                } catch (\Exception $e) {
                    echo 'Message: ' . $e->getMessage();
                }
            }
        }
    }

    /**
     * Post a new media and return it's temporary identifier
     *
     * @param Request $request
     *
     * @return Response
     */
    public function postMedia($uploadedFile)
    {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile */

        $violations = $this->validator->validate($uploadedFile);

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = [
                    'message'       => $violation->getMessage(),
                    'invalid_value' => $violation->getInvalidValue()
                ];
            }

            return new JsonResponse($errors, 400);
        }

        $file = $this->storeFile($uploadedFile);
        $path = $uploadedFile->getPath();
        $filesystem = new Filesystem();
        try {
            $filesystem->remove($path);
        } catch (IOExceptionInterface $exception) {
            throw new \Exception("An error occurred while creating your directory at " . $exception->getPath());
        }

        return [
            'originalFilename' => $file->getOriginalFilename(),
            'filePath'         => $file->getKey()
        ];
    }

    /**
     * @param uploadedFile $uploadedFile
     * @return fileData
     */
    protected function storeFile(UploadedFile $uploadedFile)
    {
        return $this->fileStorer->store($uploadedFile, \FileStorage::CATALOG_STORAGE_ALIAS);
    }
}
