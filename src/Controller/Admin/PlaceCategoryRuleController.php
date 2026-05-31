<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Core\LocationCategory;
use App\Entity\Core\PlaceCategoryRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/place-category-rules', name: 'admin_place_category_rules_')]
final class PlaceCategoryRuleController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/place_category_rules/index.html.twig', [
            'rules' => $entityManager->getRepository(PlaceCategoryRule::class)->findBy([], ['priority' => 'ASC', 'matchValue' => 'ASC']),
            'categories' => $entityManager->getRepository(LocationCategory::class)->findBy(['isActive' => true], ['sortOrder' => 'ASC', 'name' => 'ASC']),
            'rule_types' => PlaceCategoryRule::ruleTypes(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('create_place_category_rule', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para crear la regla.');

            return $this->redirectToRoute('admin_place_category_rules_index');
        }

        $categoryId = $request->request->getInt('category_id');
        $category = $entityManager->getRepository(LocationCategory::class)->find($categoryId);
        $ruleType = $request->request->getString('rule_type');
        $matchValue = trim($request->request->getString('match_value'));

        if (!$category instanceof LocationCategory) {
            $this->addFlash('error', 'Selecciona una categoría canónica válida.');

            return $this->redirectToRoute('admin_place_category_rules_index');
        }

        if (!in_array($ruleType, PlaceCategoryRule::ruleTypes(), true)) {
            $this->addFlash('error', 'Selecciona un tipo de regla válido.');

            return $this->redirectToRoute('admin_place_category_rules_index');
        }

        if ($matchValue === '') {
            $this->addFlash('error', 'Captura el valor a comparar para la regla.');

            return $this->redirectToRoute('admin_place_category_rules_index');
        }

        $existing = $entityManager->getRepository(PlaceCategoryRule::class)->findOneBy([
            'category' => $category,
            'ruleType' => $ruleType,
            'matchValue' => mb_strtolower($matchValue),
        ]);

        if ($existing instanceof PlaceCategoryRule) {
            $this->addFlash('warning', 'Esa regla ya existe para la categoría seleccionada.');

            return $this->redirectToRoute('admin_place_category_rules_index');
        }

        $rule = new PlaceCategoryRule();
        $rule
            ->setCategory($category)
            ->setRuleType($ruleType)
            ->setMatchValue($matchValue)
            ->setPriority($request->request->getInt('priority', 100))
            ->setIsActive($request->request->getBoolean('is_active', true));

        $entityManager->persist($rule);
        $entityManager->flush();

        $this->addFlash('success', 'Regla de Places creada correctamente.');

        return $this->redirectToRoute('admin_place_category_rules_index');
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(PlaceCategoryRule $rule, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('toggle_place_category_rule_%d', $rule->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para cambiar la regla.');

            return $this->redirectToRoute('admin_place_category_rules_index');
        }

        $rule->setIsActive(!$rule->isActive());
        $entityManager->flush();

        $this->addFlash('success', $rule->isActive() ? 'Regla activada.' : 'Regla desactivada.');

        return $this->redirectToRoute('admin_place_category_rules_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(PlaceCategoryRule $rule, Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid(sprintf('delete_place_category_rule_%d', $rule->getId()), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'No se pudo validar la solicitud para eliminar la regla.');

            return $this->redirectToRoute('admin_place_category_rules_index');
        }

        $entityManager->remove($rule);
        $entityManager->flush();

        $this->addFlash('success', 'Regla eliminada.');

        return $this->redirectToRoute('admin_place_category_rules_index');
    }
}
