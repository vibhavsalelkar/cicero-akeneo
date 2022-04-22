<?php

namespace Webkul\Magento2Bundle\Archiver;

use \ItemWriterInterface;
use \JobInterface;
use \JobRegistry;
use \JobExecution;
use \ItemStep;
use \ArchivableWriterInterface;
use \AbstractFilesystemArchiver;
use \ZipFilesystemFactory;
use League\Flysystem\Filesystem;
use Webkul\Magento2Bundle\Step\ItemStepInterface;

/**
 * Archive job execution files into conventional directories
 *
 */
class ArchivableFileWriterArchiver extends AbstractFilesystemArchiver
{
    /** @var ZipFilesystemFactory */
    protected $factory;

    /** @var string */
    protected $directory;

    /** @var JobRegistry */
    private $jobRegistry;

    /**
     * @param ZipFilesystemFactory $factory
     * @param Filesystem           $filesystem
     * @param JobRegistry          $jobRegistry
     */
    public function __construct(ZipFilesystemFactory $factory, Filesystem $filesystem, JobRegistry $jobRegistry)
    {
        $this->factory = $factory;
        $this->filesystem = $filesystem;
        $this->jobRegistry = $jobRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function archive(JobExecution $jobExecution)
    {
        $job = $this->jobRegistry->get($jobExecution->getJobInstance()->getJobName());
        foreach ($job->getSteps() as $step) {
            if (!$step instanceof ItemStep && !$step instanceof ItemStepInterface) {
                continue;
            }
            $writer = $step->getWriter();
            if ($this->isWriterUsable($writer)) {
                $zipName = sprintf('%s.zip', pathinfo($writer->getPath(), PATHINFO_FILENAME));

                $workingDirectory = $jobExecution->getExecutionContext()->get(JobInterface::WORKING_DIRECTORY_PARAMETER);
                $localZipPath = $workingDirectory.DIRECTORY_SEPARATOR.$zipName;

                $localZipFilesystem = $this->factory->createZip(
                    $localZipPath
                );

                foreach ($writer->getWrittenFiles() as $fullPath => $localPath) {
                    $stream = fopen($fullPath, 'r');
                    try {
                        $localZipFilesystem->putStream($localPath, $stream);
                    } catch (\Exception $e) {
                    }

                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                $localZipFilesystem->getAdapter()->getArchive()->close();
                try {
                    $this->archiveZip($jobExecution, $localZipPath, $zipName);
                } catch (\Exception $e) {
                }

                unlink($localZipPath);
            }
        }
    }

    /**
     * Put the generated zip file to the archive destination location
     */
    protected function archiveZip(JobExecution $jobExecution, string $localZipPath, string $destName)
    {
        $destPath = strtr(
            $this->getRelativeArchivePath($jobExecution),
            ['%filename%' => $destName]
        );

        if (!$this->filesystem->has(dirname($destPath))) {
            $this->filesystem->createDir(dirname($destPath));
        }

        $zipArchive = fopen($localZipPath, 'r');
        $this->filesystem->writeStream($destPath, $zipArchive);

        if (is_resource($zipArchive)) {
            fclose($zipArchive);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'archive';
    }

    /**
     * Verify if the writer is usable or not
     *
     * @param ItemWriterInterface $writer
     *
     * @return bool
     */
    protected function isWriterUsable(ItemWriterInterface $writer)
    {
        return $writer instanceof ArchivableWriterInterface && count($writer->getWrittenFiles()) >= 1;
    }

    /**
     * Check if the job execution is supported
     *
     * @param JobExecution $jobExecution
     *
     * @return bool
     */
    public function supports(JobExecution $jobExecution)
    {
        $job = $this->jobRegistry->get($jobExecution->getJobInstance()->getJobName());
        foreach ($job->getSteps() as $step) {
            if (($step instanceof ItemStep || $step instanceof ItemStepInterface) && $this->isWriterUsable($step->getWriter())) {
                return true;
            }
        }

        return false;
    }
}
