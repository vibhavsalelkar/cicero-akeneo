<?php

namespace Webkul\ImageGalleryBundle\Datasource\Orm;

use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();

class ResultRecordHydrator implements \HydratorInterface
{
    private $repository;
    public function setRepo($repository) {
        $this->repository = $repository;
    }
    /**
     * {@inheritdoc}
     */
    public function hydrate($qb, array $options = [])
    {
        $records = [];
        $results = $qb->getQuery()->execute();

        foreach ($results as $record) {
            $record['thumbnail'] = null;
            $repoQB = $this->repository->createQueryBuilder('wem')
                        ->select('md.filePath')
                        ->leftJoin('wem.medias', 'md')
                        ->andWhere('wem.code =:code')
                        ->andWhere('md.thumbnail =:thumbnail')
                        ->setParameters(['code'=>$record['code'], 'thumbnail'=> true]);
            
            $repoResults = $repoQB->getQuery()->getOneOrNullResult();
            
            if (!empty($repoResults)) {
                $record['thumbnail'] = $repoResults['filePath'];
            }

            if ('array' === gettype($record) && isset($record[0])) {
                $newRecord = $record[0];
                if (count($record) > 1) {
                    foreach ($record as $key => $value) {
                        if (!is_numeric($key)) {
                            $newRecord->$key = $value;
                        }
                    }
                }
                $record = $newRecord;

                if (array($record)) {
                    $record['is_checked'] = false;
                }
            } else {
                if (array($record)) {
                    $record['is_checked'] = false;
                }
            }

            $records[] = new ResultRecord($record);
            
        }

        return $records;
    }
}
