<?php

namespace Webkul\Magento2Bundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
// use Akeneo\Pim\Structure\Bundle\Form\Type\AttributeOptionType as PimAttributeOptionType;
use Webkul\Magento2Bundle\Form\Type\ImageCollectionType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\AbstractType;
use Akeneo\Pim\Structure\Bundle\Form\Type\AttributeOptionValueType;

class AttributeOptionType extends AbstractType
{
    /** @var string */
    protected $dataClass;

    /**
     * @param string $dataClass
     */
    public function __construct($dataClass)
    {
        $this->dataClass = $dataClass;
    }

    /**
    * {@inheritdoc}
    */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addImage($builder);

        $this->addFieldId($builder);

        $this->addFieldOptionValues($builder);

        $this->addFieldCode($builder);
    }

    /**
     * Add field id to form builder
     *
     * @param FormBuilderInterface $builder
     */
    protected function addImage(FormBuilderInterface $builder)
    {
        $builder->add(
            'image',
            ImageCollectionType::class
        );
    }
    

    /**
     * Add field id to form builder
     *
     * @param FormBuilderInterface $builder
     */
    protected function addFieldId(FormBuilderInterface $builder)
    {
        $builder->add('id', HiddenType::class);
    }

    /**
     * Add option code
     *
     * @param FormBuilderInterface $builder
     */
    protected function addFieldCode(FormBuilderInterface $builder)
    {
        $builder->add('code', TextType::class, ['required' => true]);
    }

    /**
     * Add options values to form builder
     *
     * @param FormBuilderInterface $builder
     */
    protected function addFieldOptionValues(FormBuilderInterface $builder)
    {
        $builder->add(
            'optionValues',
            CollectionType::class,
            [
                'entry_type'   => AttributeOptionValueType::class,
                'allow_add'    => true,
                'allow_delete' => true,
                'by_reference' => false,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class'      => $this->dataClass,
                'csrf_protection' => false
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'pim_enrich_attribute_option';
    }
}
