<?php

namespace Webkul\ImageGalleryBundle\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
// use Webkul\ImageGalleryBundle\Repository\GalleryRepository;

/**
 * PostMedia Controller
 *
 * @author    Aman Srivastav <aman.srivastava462@webkul.com>
 * @copyright 2019 Webkul Soft Pvt. Ltd (https://webkul.com/)
 */
class PostMediaController
{
    /** @var ValidatorInterface */
    protected $validator;

    /** @var PathGeneratorInterface */
    protected $pathGenerator;

    // /** @var string */
    // protected $uploadDir;

    /** @var FileStorer */
    private $fileStorer;

    // /** @var EntityManager */
    // private $em;

    // /** @var GalleryRepository */
    // protected $galleryRepository;


    /**
     * @param ValidatorInterface     $validator
     * @param PathGeneratorInterface $pathGenerator
     * @param string                 $uploadDir     
     */
    public function __construct(
        ValidatorInterface $validator,
        \PathGeneratorInterface $pathGenerator,
        // $uploadDir, 
        \FileStorer $fileStorer
    ) {
        $this->validator = $validator;
        $this->pathGenerator = $pathGenerator;
        // $this->uploadDir = $uploadDir;
        $this->fileStorer = $fileStorer;
        // $this->em = $em;
        // $this->galleryRepository = $galleryRepository;
    }

    /**
     * Post a new media and return it's temporary identifier
     *
     * @param Request $request
     *
     * @return Response
     */
    public function postAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return new RedirectResponse('/');
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');

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

        return new JsonResponse(
            [
                'originalFilename' => $uploadedFile->getClientOriginalName(),
                'filePath'         => $file->getKey()
            ]
        );
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
