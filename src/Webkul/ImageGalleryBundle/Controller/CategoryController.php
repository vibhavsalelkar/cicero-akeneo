<?php

namespace Webkul\ImageGalleryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManager;


class CategoryController extends Controller
{
    /** @var ValidatorInterface */
    protected $validator;

    /** @var PathGeneratorInterface */
    protected $pathGenerator;

    /** @var string */
    protected $uploadDir;

    /** @var FileStorer */
    private $fileStorer;

    /**
     * @param ValidatorInterface     $validator
     * @param PathGeneratorInterface $pathGenerator
     * @param string                 $uploadDir     
     */
    public function __construct(ValidatorInterface $validator, \PathGeneratorInterface $pathGenerator, $uploadDir, \FileStorer $fileStorer)
    {
        $this->validator = $validator;
        $this->pathGenerator = $pathGenerator;
        $this->uploadDir = $uploadDir;
        $this->fileStorer = $fileStorer;
        // $this->em = $em;
        // $this->galleryRepository = $galleryRepository;
    }


    /**
     * ToDo: add asset category here
     */
    public function getAction()
    {
        return new JsonResponse([]);
    }
}
