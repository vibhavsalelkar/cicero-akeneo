<?php

namespace Webkul\ImageGalleryBundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Form\FormError;
use Oro\Bundle\ConfigBundle\Entity\ConfigValue;
use Symfony\Component\Intl\Intl;
use Akeneo\Component\Batch\Model\JobInstance;
use Akeneo\Tool\Component\Batch\Model\JobInstance as jobinstance2;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Webkul\ImageGalleryBundle\Services\ImageGalleryConnector;
use Symfony\Component\Validator\Constraints;
use Webkul\ImageGalleryBundle\Entity\Gallery;
use Webkul\ImageGalleryBundle\Entity\GalleryMedia;
use Webkul\ImageGalleryBundle\Repository\GalleryRepository;
use Webkul\ImageGalleryBundle\Repository\GalleryMediaRepository;
use Doctrine\ORM\EntityManager;
use Webkul\ImageGalleryBundle\Entity\Group;
use Webkul\ImageGalleryBundle\Classes\Version;

/**
 * Configuration rest controller in charge of the woocommerce connector configuration managements
 */
class ConfigurationController extends Controller
{
    protected $connectorService;
    protected $currencyRepo;
    protected $localeRepo;
    protected $jobInstanceRepo;
    protected $em;
    private $moduleVersion;

    /** @var FilesystemProvider */
    protected $filesystemprovider;

    /** @var GalleryRepository */
    protected $galleryRepository;

    /** @var GalleryMediaRepository */
    protected $galleryMediaRepository;

    public function __construct(
        $connectorService,
        $currencyRepo,
        $localeRepo,
        $jobInstanceRepo,
        $em,
        \FilesystemProvider $filesystemprovider,
        GalleryRepository   $galleryRepository,
        GalleryMediaRepository $galleryMediaRepository
    ) {
        $this->connectorService = $connectorService;
        $this->currencyRepo = $currencyRepo;
        $this->localeRepo = $localeRepo;
        $this->jobInstanceRepo = $jobInstanceRepo;
        $this->em = $em;
        $this->filesystemprovider = $filesystemprovider;
        $this->galleryRepository = $galleryRepository;
        $this->galleryMediaRepository = $galleryMediaRepository;
    }

    public function getAction()
    {
        return new JsonResponse([]);
    }

    public function getVersion()
    {
        if (class_exists('Akeneo\Platform\CommunityVersion')) {
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        } elseif (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        }
        $version = $versionClass::VERSION;
        return $version;
    }

    public function deleteGroupAction($id)
    {        
        $em = $this->getDoctrine()->getManager();
        $mapping = $em->getRepository('ImageGalleryBundle:Group')->find($id);
        if (!$mapping) {
            throw new NotFoundHttpException(
                sprintf('Instance with id "%s" not found', $id)
            );
        }
        $em->remove($mapping);
        $em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function saveGroupAction(Request $request)
    {
        $data = $this->getDecodedContent($request->getContent());
        $repo = $this->em->getRepository('ImageGalleryBundle:Group');
        
        if($data['code']) {
            $configs = $repo->findOneBy([
                'code' => $data['code']
                ]);
            if (!$configs) {
                $configs = new Group();
                $configs->setCode($data['code']);
                $this->em->persist($configs);
            } else {
                return new JsonResponse(
                    ['response' =>'Alredy Created Gallery existed with'],
                    RESPONSE::HTTP_BAD_REQUEST
                );
            }
            $this->em->flush();
            return new JsonResponse(['response' => 'Group Added']);
        }
    }

    public function saveAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $data = $this->getDecodedContent($request->getContent());
        if (!empty($data['code'])) {
            $gallery = $this->galleryRepository->findOneByCode($data['code']);
        }

        if (empty($gallery)) {
            $gallery = new Gallery();
        } else {
            if (!($request->query->get('identifier'))) {
                return new JsonResponse(
                    ['response' =>'Alredy Created Gallery existed with'],
                    RESPONSE::HTTP_BAD_REQUEST
                );
            }
        }
        if(isset($data['tag'])) {
            $repo = $this->em->getRepository('ImageGalleryBundle:GalleryTag');
            $tag =  $data['tag'];
            if ($tag) {
                $tags = $tag;
                $alreadyAddedTags = [];
                if (!empty($gallery->getTags())) {
                    foreach ($gallery->getTags() as $value) {
                        $gallery->removeTag($value);
                    }
                }

                foreach ($tags as $tag) {
                    if (!in_array($tag, $alreadyAddedTags)) {
                        $result = $repo->findOneBy(["code" => $tag]);
                        if ($result) {
                            $gallery->addTag($result);
                        }
                    }
                }
            }
            unset($data['tag']);
        }
        $form = $this->getGalleryForm($gallery);
        $form->submit($data);

        if ($form->isValid()) {
            $this->em->persist($gallery);
            $this->em->flush();

            $galleryMedias = !empty($data['medias']) ? $this->createMediasByAssetAndData($gallery, $data['medias']) : [];
            foreach ($galleryMedias as $galleryMedia) {
                if ($galleryMedia) {
                    $this->em->persist($galleryMedia);
                }
            }
            foreach ($gallery->getMedias() as $galleryMedia) {
                if (!in_array($galleryMedia, $galleryMedias)) {
                    $this->em->remove($galleryMedia);
                }
            }
            $this->em->flush();
            return new JsonResponse(['id' => $gallery->getId(), 'code' => $gallery->getCode()]);
        } else {
            if ($request->query->get('identifier')) {
                $errors = $this->getFormErrors($form);
            } else {
                $errors = ['values' => $this->getJsFormErrors($form)];
            }

            return new JsonResponse(
                $errors,
                RESPONSE::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * @param Request $request
     *
     * @throws BadRequestHttpException
     *
     * @return Response
     *
     * @AclAncestor("image_gallery_configuration")
     */
    public function deleteAction($code)
    {
        // $em = $this->getDoctrine()->getManager();
        // $asset = $this->galleryRepository->findOneByCode($code);
        $asset = $this->galleryRepository->findOneByCode($code);

        if ($asset) {
            foreach ($asset->getMedias() as $media) {
                $this->em->remove($media);
            }
            $this->em->remove($asset);
            $this->em->flush();
        }

        return new JsonResponse([]);
    }

    public function saveMediaAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $data = $this->getDecodedContent($request->getContent());
        $gallery = $this->galleryRepository->findOneByCode($data['code']);
        $galleryMedia = $this->galleryMediaRepository
            ->findOneBy(["gallery" => $gallery, 'filePath' => $data['filePath']]);
        if ($galleryMedia) {
            $description = isset($data['description']) ? $data['description'] : null;
            $thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : null;
            $title = isset($data['title']) ? $data['title'] : null;
           

            if(isset($data['thumbnail']) && $data['thumbnail'] == '1') {
                $galleries = $this->galleryMediaRepository->findByGallery($galleryMedia->getGallery()->getId());
                
                if(\is_array($galleries)) {
                    foreach ($galleries as $key => $value) {
                        $newGalleryMedia = $this->galleryMediaRepository
                        ->findOneBy(["id" => $value]);
                        if($newGalleryMedia) {
                            $newGalleryMedia->setThumbnail(0);
                        }
                    }
                }


            }
            $galleryMedia->setDescription($description);
            $galleryMedia->setThumbnail($thumbnail);
            $galleryMedia->setTitle($title);
        }
        $this->em->persist($galleryMedia);
        $this->em->flush();

        return new JsonResponse(['id' => $gallery->getId(), 'code' => $gallery->getCode()]);
    }

    protected function getGalleryForm($entity = null)
    {
        $form = $this->createFormBuilder($entity, [
            'allow_extra_fields' => true,
            'csrf_protection' => false
        ]);

        foreach ($this->getGalleryFields() as $field => $constraints) {
            $form->add($field, null, [
                'constraints' => $constraints
            ]);
        }

        return $form->getForm();
    }

    protected function getGalleryFields()
    {
        return [
            'code' => [
                new Constraints\Regex([
                    'pattern' => '/^[a-zA-Z0-9_]+$/i',
                    'htmlPattern' => '^[a-zA-Z0-9_]+$',
                    'message' => 'Gallery code may contain only letters, numbers and underscore.',
                    'match'   => true
                ]),
                new Constraints\NotBlank(),
            ],
            'title' => [new Constraints\Optional()],
            'alt' => [new Constraints\Optional()],
            'starred' => [new Constraints\Optional()],
            'description' => [new Constraints\Optional()],
            'expiration_date' => [new Constraints\Optional()],
            'galleryGroup' => [new Constraints\Optional()]

        ];
    }

    protected function getFormErrors($form)
    {
        $errorContext = [];
        foreach ($form->getErrors(true) as $key => $error) {
            $errorContext[$error->getOrigin()->getName()] = $error->getMessage();
        }

        return $errorContext;
    }

    protected function getJsFormErrors($form)
    {
        $errorContext = [];
        foreach ($form->getErrors(true) as $key => $error) {
            $errorContext[] = [
                'message' => $error->getMessage(),
                'path' => $error->getOrigin()->getName(),
            ];
        }

        return $errorContext;
    }

    public function getTagAction()
    {
        $tags = $this->connectorService->getAllTag();

        return new JsonResponse($tags);
    }

    public function getGroupsAction()
    {
        $allGroups = [];
        $repo = $this->em->getRepository('ImageGalleryBundle:Group');
        foreach ($repo->findAll() as $allGroup) {
            $allGroups[$allGroup->getId()] = $allGroup->getCode();
        }

        return new JsonResponse($allGroups);
    }

    public function addTagAction(Request $request)
    {
        $params = $request->request->all() ? : json_decode($request->getContent(), true);
        
        if ($request->getMethod() == 'POST') {
            $this->connectorService->saveTags($params);
        }
        return new JsonResponse(['nsvksbhsdibvhjx']);
    }


    /**
     * Get the current configuration
     *
     * @AclAncestor("image_gallery_configuration")
     *
     * @return JsonResponse
     */
    public function getGalleryAction($identifier)
    {
        $asset = $this->galleryRepository->getSingleArrayResultByCode($identifier);

        if (!$asset) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }
        if ($asset['code']) {
            $tags = $this->connectorService->getRelatedTagsOfGallery($asset['code']);
            if (!empty($tags)) {
                $asset['tag'] = $tags;
            }
        }
    
        $fs = $this->filesystemprovider->getFilesystem('catalogStorage');
        if (get_class($fs->getAdapter()) == 'League\Flysystem\AwsS3v3\AwsS3Adapter') {
            $router = $this->get('router');
            foreach ($asset['medias'] as $medKey => $medValue) {

                $awsService = $this->get('wk_aws_integration.service');
                $visibility = $awsService->getVisibilityByFileName($medValue['filePath']);
                $asset['medias'][$medKey]['visibility'] = $visibility['visibility'];
            }
        }

        return new JsonResponse($asset);
    }

    public function getMediaDetailAction(Request $request)
    {
        $identifier = $request->query->get('idenitifer');
        $filePath = $request->query->get('filePath');
        $code = $request->query->get('code');
        $filePath = urldecode($filePath);
        // $media = $this->galleryMediaRepository->findAll();
        $media = $this->galleryMediaRepository
            ->getSingleArrayResultByIdentifiersAndMediaKey($identifier, $filePath);
        $media['identifier'] = $identifier;
        $media['code'] = $code;
        $media['media'] = $media;

        return new JsonResponse($media);
    }

    public function getImageForSlider(Request $request)
    {
        // $identifier = $request->query->get('idenitifer');
        // $filePath = $request->query->get('filePath');
        // $code = $request->query->get('code');
        // $filePath = urldecode($filePath);

        $media = $this->galleryMediaRepository->findAll();
        // $media['identifier'] = $identifier;
        // $media['code'] = $code;
        // $media['media'] = $media;

        return new JsonResponse($media);
    }

    public function getModeVersionAction()
    {
        $data = [];
        if (null === $this->moduleVersion) {
            $versionObject = new Version();
            $this->moduleVersion = $versionObject->getModuleVersion();
        }
        $data['moduleVersion'] = $this->moduleVersion;


        return new JsonResponse($data);
    }

    // /**
    //  * Get the current configuration
    //  *
    //  * @AclAncestor("image_gallery_configuration")
    //  *
    //  * @return JsonResponse
    //  */
    // public function getGallerysAction(Request $request)
    // {
    //     $identifiers = explode(',', $request->query->get('identifiers'));
    //     $em = $this->getDoctrine()->getManager();

    //     $assets = $this->get('webkul_gallery.repository.media')->getArrayResultsByIdentifiers($identifiers);

    //     return new JsonResponse($assets);
    // }    

    public function getGalleryAssetsAction(Request $request)
    {
        // print_r('demochecwebkulgalleryk');
        // // print_r($request);
        // die;
        $identifiers = explode(',', $request->query->get('identifiers'));
        $em = $this->getDoctrine()->getManager();

        $assets = $this->galleryRepository->getArrayResultsByIdentifiers($identifiers);
        // print_r('assets');
        // print_r($assets);
        // die;
        return new JsonResponse($assets);
    }

    public function getLocaleMapListAction(Request $request)
    {
        $locales = [];
        $data = $this->localeRepo;
        $codes = $data->getActivatedLocalesQB()->getQuery()->getArrayResult();

        foreach ($codes as $code) {
            $locales[] = [
                "code" => $code['code'],
                "label" => Intl::getLocaleBundle()->getLocaleName($code['code'])
            ];
            // $locales[$code['code']] = Intl::getLocaleBundle()->getLocaleName($code['code']);
        }

        return new JsonResponse($locales);
    }

    public function getLocaleListAction(Request $request)
    {
        $locales = [];

        $data = $this->localeRepo;

        $codes = $data->getActivatedLocalesQB()->getQuery()->getArrayResult();

        foreach ($codes as $code) {
            if (is_array($code)) {
                $code = $code['code'];
            }

            $locales[$code] = Intl::getLocaleBundle()->getLocaleName($code);
        }

        return new JsonResponse($locales);
    }

    public function getScopeListAction(Request $request)
    {
        $userLocale = $this->get('security.token_storage')->getToken()->getUser()->getUiLocale()->getCode();

        return new JsonResponse($this->connectorService->getChannelList($userLocale));
    }

    /**
     * Get the JSON decoded content. If the content is not a valid JSON, it throws an error 400.
     *
     * @param string $content content of a request to decode
     *
     * @throws BadRequestHttpException
     *
     * @return array
     */
    protected function getDecodedContent($content)
    {
        $decodedContent = json_decode($content, true);

        if (null === $decodedContent) {
            throw new BadRequestHttpException('Invalid json message received');
        }

        return $decodedContent;
    }

    protected function createMediasByAssetAndData(Gallery $gallery, $data)
    {
        if (isset($data['data']) || !empty($data)) {
            $imageData = isset($data['data']) ? $data['data']
                : (!empty($data) ? $data : []);
        }
        if ($this->isAssoc($imageData)) {
            $imageData = [$imageData];
        }


        $galleryMedias = [];
        foreach ($imageData as $data) {
            $galleryMedia = null;
            if (isset($data['id'])) {
                $galleryMedia = $this->getDoctrine()->getRepository('ImageGalleryBundle:GalleryMedia')->findOneById($data['id']);
            } elseif (!empty($data['filePath']) && !empty($data['originalFilename'])) {
                if (!$galleryMedia) {
                    $galleryMedia = $this->getDoctrine()->getRepository('ImageGalleryBundle:GalleryMedia')->findOneBy(["filePath" => $data['filePath']]);
                }

                if (!$galleryMedia) {
                    $galleryMedia = new GalleryMedia();
                }
                if (isset($data['locale'])) {
                    $galleryMedia->setLocale($data['locale']);
                }
                $galleryMedia->setOriginalFilename($data['originalFilename']);
                $galleryMedia->setFilePath($data['filePath']);
                $galleryMedia->setGallery($gallery);
            }
            if ($galleryMedia) {
                $galleryMedias[] = $galleryMedia;
            }
        }

        return $galleryMedias;
    }

    public function isAssoc(array $arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function getCategoryAction()
    {
        return new JsonResponse([]);
    }
}
