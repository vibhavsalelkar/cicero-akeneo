<?php

namespace Acme\Bundle\XlsxConnectorBundle\Job\JobParameters;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface;
use Akeneo\Tool\Component\Localization\Localizer\LocalizerInterface;
use Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\FileExtension;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class SimpleXlsxImport implements
    DefaultValuesProviderInterface,
    ConstraintCollectionProviderInterface
{
    /** @var ConstraintCollectionProviderInterface */
    protected $constraintCollectionProvider;

    /** @var DefaultValuesProviderInterface */
    protected $defaultValuesProvider;

    /**
     * {@inheritdoc}
     */
    public function getDefaultValues()
    {
        return [
            'filePath'                  => null,
            'uploadAllowed'             => true,
            'dateFormat'                => LocalizerInterface::DEFAULT_DATE_FORMAT,
            'decimalSeparator'          => LocalizerInterface::DEFAULT_DECIMAL_SEPARATOR,
            'enabled'                   => true,
            'categoriesColumn'          => 'categories',
            'familyColumn'              => 'family',
            'groupsColumn'              => 'groups',
            'enabledComparison'         => true,
            'realTimeVersioning'        => true,
            'invalid_items_file_format' => 'xlsx',
            'convertVariantToSimple'    => false,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraintCollection()
    {
        return new Collection(
            [
                'fields' => [
                    'filePath' => [
                        new NotBlank(['groups' => ['Execution', 'UploadExecution']]),
                        new FileExtension(
                            [
                                'allowedExtensions' => ['xlsx', 'zip'],
                                'groups'            => ['Execution', 'UploadExecution']
                            ]
                        )
                    ],
                    'uploadAllowed' => [
                        new Type('bool'),
                        new IsTrue(['groups' => 'UploadExecution']),
                    ],
                    'decimalSeparator' => [
                        new NotBlank()
                    ],
                    'dateFormat' => [
                        new NotBlank()
                    ],
                    'enabled' => [
                        new Type('bool')
                    ],
                    'categoriesColumn' => [
                        new NotBlank()
                    ],
                    'familyColumn' => [
                        new NotBlank()
                    ],
                    'groupsColumn' => [
                        new NotBlank()
                    ],
                    'enabledComparison' => [
                        new Type('bool')
                    ],
                    'realTimeVersioning' => [
                        new Type('bool')
                    ],
                    'invalid_items_file_format' => [
                        new Type('string')
                    ],
                    'convertVariantToSimple' => [
                        new Type('bool')
                    ]
                ]
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports(JobInterface $job)
    {
        return $job->getName() === 'xlsx_custom_product_import';
    }
}