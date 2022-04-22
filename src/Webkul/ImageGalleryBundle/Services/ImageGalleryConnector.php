<?php

namespace Webkul\ImageGalleryBundle\Services;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Oro\Bundle\ConfigBundle\Entity\ConfigValue;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webkul\ImageGalleryBundle\Entity\GalleryTag;

class ImageGalleryConnector
{
    const SECTION = 'webkul_gallery_translator';
    const NAME = 'webkul_gallery_config';

    private $em;
    private $container;
    private $stepExecution;
    protected $localeRepo;
    protected $categoryRepo;
    protected $attributesRepo;
    protected $attributeOptionRepo;
    protected $attributeGroupRepo;
    protected $currencyRepo;
    protected $channelRepo;
    protected $familyRepo;
    protected $familyVariantRepo;
    protected $fileStorage;
    protected $productRepo;
    protected $productModelRepo;
    protected $jobInstanceRepo;
    protected $router;
    protected $imagineController;
    protected $productModelUpdater;
    protected $productModelSaver;
    protected $validator;

    public function __construct(
        $container,
        \Doctrine\ORM\EntityManager $em,
        $localeRepo,
        $categoryRepo,
        $attributesRepo,
        $attributeOptionRepo,
        $attributeGroupRepo,
        $currencyRepo,
        $channelRepo,
        $familyRepo,
        $familyVariantRepo,
        $fileStorage,
        $productRepo,
        $productModelRepo,
        $jobInstanceRepo,
        $router,
        $imagineController,
        $productModelUpdater,
        $productModelSaver,
        $validator
    ) {
        $this->container = $container;
        $this->em = $em;
        $this->localeRepo = $localeRepo;
        $this->categoryRepo = $categoryRepo;
        $this->attributesRepo = $attributesRepo;
        $this->attributeOptionRepo = $attributeOptionRepo;
        $this->attributeGroupRepo = $attributeGroupRepo;
        $this->currencyRepo = $currencyRepo;
        $this->channelRepo = $channelRepo;
        $this->familyRepo = $familyRepo;
        $this->familyVariantRepo = $familyVariantRepo;
        $this->fileStorage = $fileStorage;
        $this->productRepo = $productRepo;
        $this->productModelRepo = $productModelRepo;
        $this->jobInstanceRepo = $jobInstanceRepo;
        $this->router = $router;
        $this->imagineController = $imagineController;
        $this->productModelUpdater = $productModelUpdater;
        $this->productModelSaver = $productModelSaver;
        $this->validator = $validator;
    }

    public function setStepExecution($stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    public function saveData($params)
    {
        $value = serialize($params);
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
        $field = $repo->findOneBy([
            'section' => self::SECTION,
            'name' => self::NAME
        ]);
        if (!$field) {
            $field = new ConfigValue();
        }
        $field->setName(self::NAME);
        $field->setSection(self::SECTION);
        $field->setValue($value);
        $this->em->persist($field);
        $this->em->flush();
    }

    public function getData()
    {
        $value = [];
        $repo = $this->em->getRepository('OroConfigBundle:ConfigValue');
        $field = $repo->findOneBy([
            'section' => self::SECTION,
            'name' => self::NAME
        ]);

        if ($field) {
            $value = $field->getValue();
            $value = unserialize($value);
        }

        return $value;
    }

    public function getChannelList($userLocale)
    {
        $channelCodes = $this->channelRepo->getChannelCodes();
        $channels = [];

        foreach ($channelCodes as $code) {
            $channels[$code] = $this->channelRepo->findOneBy(["code" => $code])->getLabel($userLocale);
        }

        return $channels;
    }

    public function checkScope($code)
    {
        $repository = $this->attributesRepo;
        $value = $repository->findOneBy(['code' => $code]);
        if (!$value) {
            return null;
        }

        return $value->isScopable();
    }

    public function getAllGroups()
    {
        $allGroups = [];
        $repo = $this->em->getRepository('ImageGalleryBundle:Group');
        foreach ($repo->findAll() as $allGroup) {
            $allGroups[$allGroup->getCode()] = $allGroup->getCode();
        }

        return $allGroups;
    }

    public function updateProductModel($productModel, $data)
    {
        $this->productModelUpdater->update($productModel, $data);
    }

    public function saveProductModel($productModel)
    {
        $response = false;
        $violations = $this->validator->validate($productModel);
        if ($violations->count() > 0) {
            // $this->stepExecution->addWarning( $violations->__toString(), [], new \DataInvalidItem(['code' => $productModel->getCode() ]));
            // $this->stepExecution->incrementSummaryInfo('skip');
        } else {
            $response = true;
            $this->productModelSaver->save($productModel);
        }

        return $response;
    }

    public function getAllTag()
    {
        $allTag = [];
        $repoTags = $this->em->getRepository('ImageGalleryBundle:GalleryTag');
    
        foreach ($repoTags->findAll() as $allTags) {
            $allTag[] = $allTags->getCode();
        }
        
        return $allTag;
    }

    public function saveTags($params)
    {
        $repo = $this->em->getRepository('ImageGalleryBundle:GalleryTag');
        
        foreach ($params as $param) {
            $configs = $repo->findOneBy([
                'code' => $param
                ]);
            if (!$configs) {
                $configs = new GalleryTag();
                $configs->setCode($param);
                $this->em->persist($configs);
            }
            $this->em->flush();
        }
    }


    public function getRelatedTagsOfGallery($code)
    {
        $repo = $this->container->get('webkul_gallery.repository.media')->findOneByCode($code);
        $gallerytag = $repo->getTags();
        $storedRelatedTag = [];

        foreach ($gallerytag->getValues() as $tags) {
            $storedRelatedTag[] =$tags->getCode();
        }

        return $storedRelatedTag;
    }

    public function clean($full_path){
        return str_replace(array(
            "\\\\",
            "\\/", 
            "//", 
            "\\/", 
            "/\\"), DIRECTORY_SEPARATOR, $full_path);
    }

    public function transform($values, $filePath, $code)
    {
        $media = [];
       
        
        foreach ($values as $index => $value) {
            if (isset($value)) {
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    $media[] = $this->changeUrlToContent($value, $code, $index);
                } else {
                    $dataFilePath = $value;
                    $mediapath = sprintf(
                        '%s%s%s',
                        $filePath,
                        DIRECTORY_SEPARATOR,
                        $dataFilePath
                    );
                    $mediapath = $this->clean($mediapath);
                    try {
                        $link_array = explode('/', $filePath);
                        $page = str_replace(' ', '', end($link_array));
                       
                        $newFilePath = $page.'/'.$dataFilePath;

                        $newFileDirectory = substr($newFilePath, 0, strrpos($newFilePath, '/'));
                        if (!file_exists($newFilePath)) { 
                            $fs = $this->fileStorage->getFilesystem('catalogStorage');
                            $content = fopen($mediapath, 'r');
                            try {
                                $content = fopen($mediapath, 'r');
                            } catch (\Exception $e) {
                                
                            }
                            $fs->put($newFilePath, $content);

                            $newFilePath = $fs->getAdapter()->getPathPrefix() . $newFilePath;
                        }
                        
                        $media[] = $newFilePath;
                    } catch (\Exception $e) {
                        echo $e->getMessage();
                        $newFilePath = null;
                    }
                }
            }
        }

        return $media;
    }

    public function changeUrlToContent($url, $code, $index)
    {
        $path = preg_replace('#^http(s)?://#', '', $url);
        $path = explode('/', $path);
        $localpath = sys_get_temp_dir();
        for ($i=0; $i<count($path); $i++) {
            $localpath = rtrim($localpath, '/') .'/'. $path[$i];
        }
        try {
            if (!file_exists(dirname($localpath))) {
                mkdir(dirname($localpath), 0777, true);
            }
    
            $check = file_put_contents($localpath, file_get_contents($url));
        } catch (\Exception $e) {
            $localpath = null;
        }

        return $localpath;


    }

     public function getMediaByFilepath($filePath)
    {
        $result = [];
        $repo = $this->em->getRepository('ImageGalleryBundle:GalleryMedia');
        $media = $repo->findOneBy([
            'filePath' => $filePath
        ]);

        if ($media) {
            $result = $media;
        }

        return $result;
    }



}
