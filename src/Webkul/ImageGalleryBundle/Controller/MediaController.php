<?php

namespace Webkul\ImageGalleryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Webkul\ImageGalleryBundle\Entity\GalleryMedia;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();



class MediaController extends Controller
{

    /**
     * @param FilesystemProvider    $fileSystemProvider
     * @param PathGenerator $fileinforepository  
     */
    public function __construct(
        \FilesystemProvider $fileSystemProvider,
        \FileInfoRepository $fileInfoRepository,
        $gallaryservices,
        $filestorer
    ) {
        $this->gallaryservices = $gallaryservices;
        $this->fileSystemProvider = $fileSystemProvider;
        $this->fileInfoRepository = $fileInfoRepository;
        $this->filestorer = $filestorer;
    }

    /**
     * @param string $identifier
     *
     * @throws NotFoundHttpException
     *
     * @return \StreamedFileResponse
     */
    public function downloadAction($identifier)
    {
        $filename = urldecode($identifier);

        if (strpos($filename, '/tmp/pim/file_storage/') !== false) {
            $filename = str_replace('/tmp/pim/file_storage/', '', $filename);
        }
        $fs = $this->fileSystemProvider->getFilesystem('catalogStorage');
        if ($fs->has($filename)) {
            $stream = $fs->readStream($filename);
            $headers = [];

            if (null !== $fileInfo = $this->fileInfoRepository->findOneByIdentifier($filename)) {
                $headers['Content-Disposition'] = sprintf(
                    'attachment; filename="%s"',
                    $fileInfo->getOriginalFilename()
                );
            }

            return new \StreamedFileResponse($stream, 200, $headers);
        }

        throw new NotFoundHttpException(
            sprintf('File with key "%s" could not be found.', $filename)
        );
    }
    
    public function getVersion(){
        if (class_exists('Akeneo\Platform\CommunityVersion')) {
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        } elseif (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        }
        $version = $versionClass::VERSION;
        return $version;
    }

    public function editorMediaAction()
    {
        $version = $this->getVersion();
        if ($version > '3.0' && $version < '4.0') {
            return $this->render('ImageGalleryBundle:Media:editor.html.twig', array(
                'nounce' => false
            ));
        } else {
            return $this->render('@WebkulGalleryMedia/editor.html.twig', array(
                'nounce' => true
            ));
        }
    }

    
    public function editMediaAction(Request $request)
    {
        $url = $request->query->get('url');
        $code = $request->query->get('code');
        $filePath = $request->query->get('filepath');
        $router = $this->get('router');
        $url = $router->generate('webkulgallery_media_download', [
            'identifier' => rawurlencode($filePath)
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $url = str_replace("%2f", "%252F", $url);
        
        $host = $request->getSchemeAndHttpHost();
        $version = $this->getVersion();
        if ($version > '3.0' && $version < '4.0') {
            return $this->render('ImageGalleryBundle:Media:edit.html.twig', array(
                'url' => $url,
                'host' => $host,
                'code' => $code,
                'filePath' => $filePath,
                'nounce' => false
            ));
        } else {
            return $this->render('@WebkulGalleryMedia/edit.html.twig', array(
                'url' => $url,
                'host' => $host,
                'code' => $code,
                'filePath' => $filePath,
                'nounce' => true
            ));
        }
    }

    public function saveMediaAction(Request $request)
    {
        $params = $request->request->all() ? : json_decode($request->getContent(), true);
        $version = $this->getVersion();
        if ($version > '3.0' && $version < '4.0') {
            $fst = $this->fileSystemProvider->getFilesystem('tmpStorage');
        } else {
            $fst = $this->fileSystemProvider->getFilesystem(\FileStorage::CATALOG_STORAGE_ALIAS);
        }
        $fsc = $this->fileSystemProvider->getFilesystem(\FileStorage::CATALOG_STORAGE_ALIAS);
        $checkMedia = $this->gallaryservices->getMediaByFilepath($params['filePath']);
        $em = $this->getDoctrine()->getManager();

        if ($checkMedia) {
            $originalName = $checkMedia->getOriginalFilename();
            $img = $params['url'];
            $img = str_replace('data:image/png;base64,', '', $img);
            $img = str_replace(' ', '+', $img);
            $data = base64_decode($img);
            $oldPath = $params['filePath'];
            $filePath = pathinfo($params['filePath']);
            $filePath = $filePath['dirname'] . '/' . $originalName;
            $fst->put($filePath, $data);
            $newFilePath = $fst->getAdapter()->getPathPrefix() . $filePath;
            $uploadedFile = $this->postMedia($newFilePath, $originalName);

            $checkMedia->setFilePath($uploadedFile->getKey());
            $fst->delete($filePath);
            $fsc->delete($oldPath);
            $em->persist($checkMedia);
        }
        $em->flush();
        
        return new Response('success');
    }

    protected function postMedia($filePath, $originalName)
    {
        $filename = explode('/', $filePath);
        $fname = end($filename);

        $file = new UploadedFile(
            implode('/', $filename),
            $fname,
            mime_content_type($filePath),
            filesize($filePath),
            null,
            false
        );

        $uploadedFile = $this->storeFile($file);

        return $uploadedFile;
    }

    protected function storeFile(UploadedFile $uploadedFile)
    {
        return $this->filestorer->store($uploadedFile, \FileStorage::CATALOG_STORAGE_ALIAS);
    }

}
