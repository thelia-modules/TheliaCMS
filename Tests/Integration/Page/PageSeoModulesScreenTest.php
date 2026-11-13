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

namespace TheliaCMS\Tests\Integration\Page;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Template\TemplateDefinition;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\PageTypeWriter;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;
use TheliaCMS\TheliaCMS;

/**
 * The SEO a module adds to a page, on the hooks the catalogue screens call in
 * their SEO tab (`tab-seo.bottom`, which SEOne answers with its robots, H1 and
 * structured data): a tab of its own, whose panel stands out of the settings
 * form, each card bringing a form its module saves. A module stands in for
 * SEOne here, so the test does not depend on it.
 */
final class PageSeoModulesScreenTest extends AdminScreenTestCase
{
    private const string TYPE = 'seo-modules-model';

    private const string EVENT = 'hook.%s.tab-seo.bottom';

    /** @var callable(HookRenderEvent): void */
    private $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = static function (HookRenderEvent $event): void {
            $event->add(\sprintf(
                '<form method="post" action="/admin/seo" data-testid="a-seo-module"><input name="object_id" value="%d"><input name="object_type" value="%s"></form>',
                (int) $event->getArgument('id'),
                htmlspecialchars((string) $event->getArgument('type')),
            ));
        };
        $this->events()->addListener(\sprintf(self::EVENT, TemplateDefinition::BACK_OFFICE), $this->listener);
    }

    protected function tearDown(): void
    {
        $this->events()->removeListener(\sprintf(self::EVENT, TemplateDefinition::BACK_OFFICE), $this->listener);

        parent::tearDown();
    }

    public function testASavedPageShowsTheSeoOfTheModulesOutOfTheSettingsForm(): void
    {
        $page = $this->createPage('SEO des modules', published: false);

        $screen = $this->screen(\sprintf('/admin/cms/pages/%d', $page->getId()));

        self::assertSame($this->say('Search engines (modules)'), trim($screen->filter('[data-testid="cms-page-tab-seo-modules"]')->text()));
        self::assertSame('#tab-seo-modules', $screen->filter('[data-testid="cms-page-tab-seo-modules"]')->attr('data-bs-target'));
        $module = $screen->filter('#tab-seo-modules [data-testid="a-seo-module"]');
        self::assertSame(1, $module->count(), 'The card of the module, in the panel.');
        self::assertSame((string) $page->getId(), $module->filter('input[name="object_id"]')->attr('value'));
        self::assertSame(TheliaCMS::PAGE_VIEW, $module->filter('input[name="object_type"]')->attr('value'), 'Named as the view of the site, the one SEOne reads.');
        self::assertSame(0, $screen->filter('form#cms-page-settings-form [data-testid="a-seo-module"]')->count(), 'Never inside the settings form: a form in a form is dropped by the browser.');
        self::assertSame($this->say('Each card is saved by its own button. The Save of the page saves the settings of the page only.'), trim($screen->filter('#tab-seo-modules [data-testid="cms-page-seo-modules-help"]')->text()), 'The panel says which button saves what.');
    }

    public function testANewPageAndAModelShowNoSeoOfTheModules(): void
    {
        self::assertSame(0, $this->screen('/admin/cms/pages/new')->filter('[data-testid="cms-page-tab-seo-modules"], #tab-seo-modules')->count(), 'No id to attach it to.');

        $this->getService(PageTypeWriter::class)->add(self::TYPE);
        $writer = $this->getService(CmsPageWriter::class);
        $model = $writer->createModel(self::TYPE, $this->locale(), 'Modèle');

        self::assertSame(0, $this->screen(\sprintf('/admin/cms/pages/%d', $model->getId()))->filter('[data-testid="cms-page-tab-seo-modules"], #tab-seo-modules')->count(), 'A model is never published.');
    }

    private function events(): EventDispatcherInterface
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);

        return $dispatcher;
    }

    private function say(string $message): string
    {
        $translator = $this->getService(TranslatorInterface::class);

        return $translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
