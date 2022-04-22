<?php
namespace Webkul\Magento2Bundle\Controller;

use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\InternalApi\AttributeOptionSearchableRepository;
use Webkul\Magento2Bundle\Form\Type\AttributeOptionType;
use Akeneo\Pim\Structure\Component\Model\AttributeOptionInterface;
use Akeneo\Pim\Structure\Component\Manager\AttributeOptionsSorter;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Structure\Component\Repository\AttributeOptionRepositoryInterface;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use FOS\RestBundle\View\ViewHandlerInterface;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Akeneo\Pim\Structure\Bundle\Controller\InternalApi\AttributeOptionController as PimAttributeOptionController;

class AttributeOptionController
{
    /**
     * Manage form submission of an attribute option
     *
     * @param AttributeOptionInterface $attributeOption
     * @param array                    $data
     *
     * @return JsonResponse
     */
    protected function manageFormSubmission(AttributeOptionInterface $attributeOption, array $data = [])
    {
        $form = $this->formFactory->createNamed('option', AttributeOptionType::class, $attributeOption);
        

        $form->submit($data, false);
        
        if ($form->isValid()) {
            $this->optionSaver->save($attributeOption);

            $option = $this->normalizer->normalize($attributeOption, 'array', ['onlyActivatedLocales' => true]);
            
            return new JsonResponse($option);
        }

        return new JsonResponse($this->getFormErrors($form), 400);
    }


}