<?php

namespace Webkul\Magento2Bundle\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Media controller
 */
class MediaController
{
    /** @var ValidatorInterface */
    protected $validator;

    /** @var \PathGeneratorInterface */
    protected $pathGenerator;

    /** @var string */
    protected $uploadDir;

    /** @var \FileStorage */
    private $fileStorer;

    protected $shortPathGenerator;

    /**
     * @param ValidatorInterface     $validator
     * @param \PathGeneratorInterface $pathGenerator
     * @param string                 $uploadDir
     */
    public function __construct(
        ValidatorInterface $validator,
        \Akeneo\Tool\Component\FileStorage\PathGenerator $pathGenerator,
        $uploadDir,
        \Akeneo\Tool\Component\FileStorage\File\FileStorer $fileStorer,
        \Akeneo\Tool\Component\FileStorage\FilesystemProvider $filesystemProvider,
        \FileInfoRepositoryInterface $fileInfoRepository,
        $shortPathGenerator,
        array $filesystemAliases
    ) {
        $this->validator = $validator;
        $this->pathGenerator = $pathGenerator;
        $this->uploadDir = $uploadDir->getTempStoragePath();
        $this->fileStorer = $fileStorer;
        $this->filesystemProvider = $filesystemProvider;
        $this->fileInfoRepository = $fileInfoRepository;
        $this->filesystemAliases = $filesystemAliases;
        $this->shortPathGenerator = $shortPathGenerator;
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

    protected function storeFile(UploadedFile $uploadedFile): \FileInfoInterface
    {
        return $this->fileStorer->store($uploadedFile, \FileStorage::CATALOG_STORAGE_ALIAS);
    }


    /**
     * @param string $filename
     *
     * @throws NotFoundHttpException
     *
     * @return StreamedFileResponse
     */
    public function downloadShortPathAction($filename)
    {
        $filename = urldecode($filename);
        $filename = $this->shortPathGenerator->getFullPathByShortFileName($filename);
        
        foreach ($this->filesystemAliases as $alias) {
            $fs = $this->filesystemProvider->getFilesystem($alias);
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
        }
    }
}
