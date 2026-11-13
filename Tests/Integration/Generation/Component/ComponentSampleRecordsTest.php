<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaCMS\Tests\Integration\Generation\Component;

use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Category;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentRepository;
use TheliaCMS\Content\Site\SiteComponentUsage;
use TheliaCMS\Content\Site\SiteComponentValidator;
use TheliaCMS\Content\Site\SitePropReader;
use TheliaCMS\Front\BlockStyles;
use TheliaCMS\Generation\Ai\GatewayFailures;
use TheliaCMS\Generation\Component\ComponentGenerator;
use TheliaCMS\Generation\Component\ComponentPrompt;
use TheliaCMS\Generation\Component\ComponentRequest;
use TheliaCMS\Generation\Component\SampleRecords;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GeneratorSettings;
use TheliaCMS\Generation\ShopContext;
use TheliaCMS\Model\CmsForm;
use TheliaCMS\Skill\SkillRepository;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\Tests\Support\FakeAiGateway;

/**
 * What the generator of components is told of the records of the shop: the
 * products, categories, folders, forms and menus its sample may name, so the
 * example of a component that shows records shows some; and a sample naming
 * one the shop does not show goes back for correction.
 */
final class ComponentSampleRecordsTest extends CmsIntegrationTestCase
{
    private const string SECTION = '# Records of the shop for the sample';

    private FixtureFactory $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = new FixtureFactory($this->getPropelConnection());
    }

    public function testTheRecordsAreListedOneLineEachWithTheirKindIdAndName(): void
    {
        $category = $this->category('Chaussures de marche');
        $product = $this->product($category, 'Randonneuse', 'RANDO-01');
        $untitled = $this->product($category, null, 'BARE-01');
        $folder = $this->fixtures->folder(0, ['locale' => $this->locale(), 'title' => 'Nos conseils']);
        $form = (new CmsForm())->setCode('sample-contact');
        $form->save();

        $message = $this->prompt()->userMessage(new ComponentRequest('Les produits liés à côté d\'un texte.', $this->locale()));
        $section = substr($message, (int) strpos($message, self::SECTION));
        $section = substr($section, 0, (int) strpos($section, '# The component asked'));

        self::assertStringContainsString(\sprintf("\n- product %d: Randonneuse (%s)\n", $product->getId(), $product->getRef()), $section, 'A product is named by its title and its reference.');
        self::assertStringContainsString(\sprintf("\n- product %d: %s\n", $untitled->getId(), $untitled->getRef()), $section, 'A product without a title by its reference.');
        self::assertStringContainsString(\sprintf("\n- category %d: Chaussures de marche\n", $category->getId()), $section);
        self::assertStringContainsString(\sprintf("\n- folder %d: Nos conseils\n", $folder->getId()), $section);
        self::assertStringContainsString(\sprintf("\n- form %d: sample-contact\n", $form->getId()), $section, 'A form without a title by its code.');
        self::assertStringContainsString('takes the id of one of them of the kind its `record` names', $section, 'The rule is next to the list.');
        self::assertLessThan(strpos($message, '# The component asked'), strpos($message, self::SECTION), 'The list comes before the request.');
    }

    public function testAProductNotShownOnTheSiteIsNotOffered(): void
    {
        $category = $this->category('Rayon');
        $hidden = $this->product($category, 'Caché', 'HIDDEN-01', visible: 0);

        $offered = $this->getService(SampleRecords::class)->offered($this->locale());

        self::assertNotContains($hidden->getId(), array_map(static fn ($record): int => $record->id, array_filter($offered, static fn ($record): bool => 'product' === $record->record->value)));
    }

    public function testANameMissingInTheLanguageOfTheSampleIsTakenInTheDefaultLanguage(): void
    {
        $other = 'fr_FR' === Lang::getDefaultLanguage()->getLocale() ? 'en_US' : 'fr_FR';
        $category = $this->category('Rayon par défaut', Lang::getDefaultLanguage()->getLocale());

        $line = array_values(array_filter(
            $this->getService(SampleRecords::class)->offered($other),
            static fn ($record): bool => 'category' === $record->record->value && $record->id === $category->getId(),
        ));

        self::assertSame('Rayon par défaut', $line[0]->name ?? null);
    }

    public function testTheSystemPromptAsksTheSampleToNameListedRecords(): void
    {
        $rules = implode("\n", array_column($this->prompt()->system(), 'text'));

        self::assertStringContainsString('The sample names records the user message lists under "Records of the shop for the sample"', $rules);
        self::assertStringContainsString('only when the message lists none of that kind', $rules);
        self::assertStringContainsString('a reference outside a row is never `required`', $rules);
    }

    public function testASampleNamingARecordTheShopDoesNotShowIsNamedAsAProblem(): void
    {
        $category = $this->category('Rayon');
        $shown = $this->product($category, 'Montrée', 'SHOWN-01');
        $hidden = $this->product($category, 'Cachée', 'HIDDEN-02', visible: 0);
        $component = (new SiteComponentValidator())->validate(self::shelf([['id' => $shown->getId()], ['id' => $hidden->getId()], ['id' => 999999]], $category->getId()));

        $problems = $this->getService(SampleRecords::class)->unknown((new SitePropReader())->read($component->props), $component->sample);

        self::assertSame([
            \sprintf('The sample: "id" of row 2 of "products" names product %d, which the shop does not show. Take the id of one the message lists under "Records of the shop for the sample".', $hidden->getId()),
            'The sample: "id" of row 3 of "products" names product 999999, which the shop does not show. Take the id of one the message lists under "Records of the shop for the sample".',
        ], $problems, 'The category shown and the first product pass; a hidden product and a made-up one do not.');
    }

    public function testAMadeUpRecordGoesBackForCorrectionAndTheCorrectionIsKept(): void
    {
        $category = $this->category('Rayon');
        $product = $this->product($category, 'Montrée', 'SHOWN-02');
        $api = (new FakeAiGateway())
            ->answer(FakeAiGateway::response(ComponentPrompt::json(self::shelf([['id' => 999999]], null))))
            ->answer(FakeAiGateway::response(ComponentPrompt::json(self::shelf([['id' => $product->getId()]], null))));

        $result = $this->generator($api)->generate(new ComponentRequest('Une étagère de produits.', $this->locale()));

        self::assertSame(2, $result->turns);
        self::assertStringContainsString('names product 999999, which the shop does not show', $api->requests[1]->messages[2]->content);
        self::assertSame([['id' => $product->getId()]], $result->component?->sample['products']);
    }

    /**
     * @param list<array{id: int}> $products
     */
    private static function shelf(array $products, ?int $category): SiteComponent
    {
        return new SiteComponent(
            'SampleShelf',
            'Étagère',
            'A heading above products.',
            [
                ['name' => 'title', 'type' => 'text', 'label' => 'Titre'],
                ['name' => 'category', 'type' => 'reference', 'label' => 'Catégorie', 'record' => 'category'],
                ['name' => 'products', 'type' => 'items', 'label' => 'Produits', 'items' => [['name' => 'id', 'type' => 'reference', 'label' => 'Produit', 'record' => 'product', 'required' => true]]],
            ],
            ['title' => 'Pour vous', 'category' => $category, 'products' => $products],
            '<section class="shelf"><h2>{{ props.title }}</h2>{% for product in props.products %}<span>{{ product.id }}</span>{% endfor %}</section>',
        );
    }

    private function category(string $title, ?string $locale = null): Category
    {
        $category = $this->fixtures->category();
        $category->setLocale($locale ?? $this->locale())->setTitle($title)->setCreatedAt(new \DateTime('+1 day'))->save();

        return $category;
    }

    private function product(Category $category, ?string $title, string $ref, int $visible = 1): Product
    {
        $overrides = ['ref' => $ref.'-'.bin2hex(random_bytes(3)), 'visible' => $visible, 'createdAt' => new \DateTimeImmutable('+1 day')];

        if (null !== $title) {
            $overrides += ['title' => $title, 'locale' => $this->locale()];
        }

        $product = $this->fixtures->product($category, $this->fixtures->taxRule(), $this->fixtures->currency(), $overrides);

        return $product;
    }

    private function prompt(): ComponentPrompt
    {
        return new ComponentPrompt(
            $this->getService(SkillRepository::class),
            $this->getService(SectionCatalog::class),
            $this->getService(ShopContext::class),
            new BlockStyles(),
            records: $this->getService(SampleRecords::class),
        );
    }

    private function generator(FakeAiGateway $api): ComponentGenerator
    {
        return new ComponentGenerator(
            $api,
            $this->getService(GeneratorSettings::class),
            $this->prompt(),
            new SiteComponentValidator(),
            $this->getService(SiteComponentRepository::class),
            $this->getService(GenerationLog::class),
            new NullLogger(),
            new GatewayFailures($this->getService(TranslatorInterface::class)),
            new SiteComponentUsage(),
            $this->getService(SectionCatalog::class),
            records: $this->getService(SampleRecords::class),
        );
    }
}
