<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\LocationCategory;
use App\Entity\Core\PlaceCategoryRule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:places:seed-category-rules',
    description: 'Seeds pragmatic Google Places category rules for the alpha catalog.',
)]
final class SeedPlaceCategoryRulesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $categories = $this->entityManager->getRepository(LocationCategory::class)->findBy(['isActive' => true]);
        if ($categories === []) {
            $io->warning('No active categories were found. Create canonical categories before seeding Places rules.');

            return Command::SUCCESS;
        }

        $created = 0;
        $skipped = 0;
        foreach ($this->seedDefinitions() as $definition) {
            $category = $this->findCategory($categories, $definition['category_keywords']);
            if (!$category instanceof LocationCategory) {
                $skipped += count($definition['rules']);
                continue;
            }

            foreach ($definition['rules'] as $ruleDefinition) {
                $matchValue = mb_strtolower(trim($ruleDefinition['match_value']));
                $existing = $this->entityManager->getRepository(PlaceCategoryRule::class)->findOneBy([
                    'category' => $category,
                    'ruleType' => $ruleDefinition['rule_type'],
                    'matchValue' => $matchValue,
                ]);

                if ($existing instanceof PlaceCategoryRule) {
                    $skipped += 1;
                    continue;
                }

                $rule = (new PlaceCategoryRule())
                    ->setCategory($category)
                    ->setRuleType($ruleDefinition['rule_type'])
                    ->setMatchValue($matchValue)
                    ->setPriority($ruleDefinition['priority'])
                    ->setIsActive(true);

                $this->entityManager->persist($rule);
                $created += 1;
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('Places category rules seeded. Created: %d. Skipped: %d.', $created, $skipped));

        return Command::SUCCESS;
    }

    /**
     * @param list<LocationCategory> $categories
     * @param list<string> $keywords
     */
    private function findCategory(array $categories, array $keywords): ?LocationCategory
    {
        foreach ($categories as $category) {
            $haystack = $this->normalizeText(sprintf(
                '%s %s %s',
                $category->getSlug(),
                $category->getName(),
                $category->getIconKey() ?? ''
            ));

            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $this->normalizeText($keyword))) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/\p{Mn}+/u', '', $normalized) ?? $value;
            }
        }

        return preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    }

    /**
     * @return list<array{
     *     category_keywords:list<string>,
     *     rules:list<array{rule_type:string, match_value:string, priority:int}>
     * }>
     */
    private function seedDefinitions(): array
    {
        return [
            [
                'category_keywords' => ['taco', 'taqu', 'antoj'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'mexican_restaurant', 'priority' => 10],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'taco_restaurant', 'priority' => 10],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'taqueria', 'priority' => 20],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'taquería', 'priority' => 20],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'tacos', 'priority' => 20],
                ],
            ],
            [
                'category_keywords' => ['fond', 'comida corrida', 'cocina economica'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'fonda', 'priority' => 25],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'cocina economica', 'priority' => 25],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'comida corrida', 'priority' => 25],
                ],
            ],
            [
                'category_keywords' => ['torta', 'sandwich'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'sandwich_shop', 'priority' => 30],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'torteria', 'priority' => 30],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'tortas', 'priority' => 30],
                ],
            ],
            [
                'category_keywords' => ['hamburg'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'hamburger_restaurant', 'priority' => 30],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'hamburguesa', 'priority' => 30],
                ],
            ],
            [
                'category_keywords' => ['pizza'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'pizza_restaurant', 'priority' => 30],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'pizzeria', 'priority' => 30],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'pizza', 'priority' => 30],
                ],
            ],
            [
                'category_keywords' => ['cafe', 'cafeter', 'coffee'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'cafe', 'priority' => 30],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'coffee_shop', 'priority' => 30],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'cafeteria', 'priority' => 30],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'café', 'priority' => 30],
                ],
            ],
            [
                'category_keywords' => ['postre', 'helad', 'pan', 'bakery'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'bakery', 'priority' => 35],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'ice_cream_shop', 'priority' => 35],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'panaderia', 'priority' => 35],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'heladeria', 'priority' => 35],
                ],
            ],
            [
                'category_keywords' => ['marisco', 'seafood'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'seafood_restaurant', 'priority' => 35],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'mariscos', 'priority' => 35],
                ],
            ],
            [
                'category_keywords' => ['sushi'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'sushi_restaurant', 'priority' => 35],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'sushi', 'priority' => 35],
                ],
            ],
            [
                'category_keywords' => ['bar', 'cantina'],
                'rules' => [
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_GOOGLE_TYPE, 'match_value' => 'bar', 'priority' => 40],
                    ['rule_type' => PlaceCategoryRule::RULE_TYPE_NAME_KEYWORD, 'match_value' => 'cantina', 'priority' => 40],
                ],
            ],
        ];
    }
}
