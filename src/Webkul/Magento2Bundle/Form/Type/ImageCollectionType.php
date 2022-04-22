<?php

/* you can create a data type to manage your image field with the key
*/

namespace Webkul\Magento2Bundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ImageCollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('filePath', TextType::class)
            ->add('originalFilename', TextType::class)
        ;
    }

    public function getName()
    {
        return 'imageCollection';
    }
}
