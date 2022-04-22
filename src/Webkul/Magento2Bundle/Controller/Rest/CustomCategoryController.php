<?php

namespace Webkul\Magento2Bundle\Controller\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CustomCategoryController extends Controller
{

    /** @var CategoryRepositoryInterface */
    protected $categoryRepository;

    /** @var SecurityFacade */
    protected $securityFacade;

    /** @var string */
    protected $categoryClass;

    /** @var string */
    protected $template;

    /**
     * @param CategoryRepositoryInterface $categoryRepository
     * @param SecurityFacade              $securityFacade
     * @param string                      $categoryClass
     * @param string                      $acl
     * @param string                      $template
     */
    public function __construct(
        \CategoryRepositoryInterface $categoryRepository,
        string $categoryClass,
        string $template
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryClass = $categoryClass;
        $this->template = $template;
    }

    public function listCategoriesAction(Request $request, $categoryId)
    {
        // if (!$this->securityFacade->isGranted($this->acl)) {
        //     throw new AccessDeniedException();
        // }

        // $entityWithCategories = $this->findEntityWithCategoriesOr404($id);
        $category = $this->categoryRepository->find($categoryId);

        if (null === $category) {
            throw new NotFoundHttpException(sprintf('%s category not found', $this->categoryClass));
        }

        $categories = null;
        $selectedCategoryIds = $request->get('selected', null);
        if (null !== $selectedCategoryIds) {
            $categories = $this->categoryRepository->getCategoriesByIds($selectedCategoryIds);
        }
        // elseif (null !== $entityWithCategories) {
        //     $categories = $entityWithCategories->getCategories();
        // }

        $trees = $this->getFilledTree($category, $categories);

        return $this->render($this->template, ['trees' => $trees, 'categories' => $categories]);
    }

    protected function getFilledTree($parent, $categories): array
    {
        return $this->categoryRepository->getFilledTree($parent, $categories);
    }
}