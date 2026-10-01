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

namespace TheliaCMS\Tests\Integration\Security;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsForm;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Page\Admin\CmsPageWriter;

/**
 * The rules every route of the CMS back office follows, read from the router
 * rather than from a list kept by hand, so a route added later is held to them
 * the day it is added:
 *
 * - a request that is not a read is refused without the token of the back office;
 * - a route answering a GET is a screen, and the screens are listed here: a
 *   write reachable with a GET could be triggered by an image or a link;
 * - every form of the screens that posts carries the token, which never shows
 *   in a URL.
 */
final class WriteRoutesTest extends AdminScreenTestCase
{
    private const string PREFIX = '/admin/cms';

    /** Out of the back office on purpose, CmsAdminGuard answers 404 for them. */
    private const array HIDDEN_PREFIXES = ['/admin/cms/scripts', '/admin/cms/settings/styles'];

    /**
     * The routes answering a GET. Each one only reads: it renders a screen, an
     * export, or the data a script of the editor asks for. A form screen
     * answers GET and POST, the POST being the submission of its form.
     */
    private const array SCREENS = [
        'admin.cms.blocks.builder',
        'admin.cms.blocks.create',
        'admin.cms.blocks.edit',
        'admin.cms.blocks.list',
        'admin.cms.forms.create',
        'admin.cms.forms.edit',
        'admin.cms.forms.field_save',
        'admin.cms.forms.list',
        'admin.cms.forms.submissions',
        'admin.cms.forms.submissions_export',
        'admin.cms.media.edit',
        'admin.cms.media.list',
        'admin.cms.menus.create',
        'admin.cms.menus.edit',
        'admin.cms.menus.entry_save',
        'admin.cms.menus.list',
        'admin.cms.pages.builder',
        'admin.cms.pages.create',
        'admin.cms.pages.edit',
        'admin.cms.pages.list',
        'admin.cms.pages.trash',
        'admin.cms.partials.sources.blocks',
        'admin.cms.partials.sources.folders',
        'admin.cms.partials.sources.forms',
        'admin.cms.partials.sources.images',
        'admin.cms.partials.sources.menus',
        'admin.cms.scripts.create',
        'admin.cms.scripts.edit',
        'admin.cms.scripts.list',
        'admin.cms.settings.edit',
        'admin.cms.settings.styles.edit',
        'admin.cms.templates.list',
        'openstudio_page_builder_image_list',
    ];

    /** Placeholder values that match no record, so a refused write had nothing to touch. */
    private const array SAMPLE_PARAMETERS = [
        'direction' => 'up',
        'format' => 'csv',
    ];

    public function testEveryWriteIsRefusedWithoutTheToken(): void
    {
        $checked = 0;

        foreach ($this->routes() as $name => $route) {
            if ($this->isHidden($route)) {
                continue;
            }

            foreach ($this->writeMethods($route) as $method) {
                $url = $this->sampleUrl($route);

                self::assertSame(403, $this->send($method, $url), \sprintf('%s %s (%s) answers without the token.', $method, $url, $name));
                self::assertSame(403, $this->send($method, $url, [], self::XHR), \sprintf('%s %s (%s) answers a script without the token.', $method, $url, $name));
                ++$checked;
            }
        }

        self::assertGreaterThan(30, $checked, 'The writes of the module are found.');
    }

    public function testATokenIsWhatTellsAWriteApart(): void
    {
        // The same requests with the token get past the guard: the 403 above
        // comes from the token and from nothing else.
        $token = $this->tokenOfTheScripts($this->screen('/admin/cms/pages'));

        foreach ($this->routes() as $name => $route) {
            if ($this->isHidden($route)) {
                continue;
            }

            foreach ($this->writeMethods($route) as $method) {
                $url = $this->sampleUrl($route);

                self::assertNotSame(403, $this->send($method, $url, [], [...self::XHR, 'HTTP_X_CSRF_TOKEN' => $token]), \sprintf('%s %s (%s) refuses the token.', $method, $url, $name));
            }
        }
    }

    public function testNoWriteAnswersAGet(): void
    {
        $answeringGet = [];

        foreach ($this->routes() as $name => $route) {
            $methods = $route->getMethods();

            self::assertNotSame([], $methods, \sprintf('%s answers every method: it must say which.', $name));

            if (\in_array('GET', $methods, true)) {
                $answeringGet[] = $name;
            }
        }

        sort($answeringGet);

        self::assertSame(self::SCREENS, $answeringGet, 'Only the screens answer a GET; a write must be a POST, a PUT, a PATCH or a DELETE.');
    }

    public function testEveryFormThatPostsCarriesTheTokenAndNoUrlDoes(): void
    {
        $page = $this->createPage('Token screens');
        $trashed = $this->createPage('Token trashed');
        $this->getService(CmsPageWriter::class)->moveToTrash($trashed);

        $menu = (new CmsMenu())->setCode('token-screens-menu');
        $menu->setLocale($this->locale())->setTitle('Token screens menu');
        $menu->save();

        $block = (new CmsBlock())->setCode('token-screens-block');
        $block->setLocale($this->locale())->setTitle('Token screens block');
        $block->save();

        $form = (new CmsForm())->setCode('token-screens-form');
        $form->setLocale($this->locale())->setTitle('Token screens form');
        $form->save();

        $screens = [
            '/admin/cms/pages',
            '/admin/cms/pages/new',
            '/admin/cms/pages/'.$page->getId(),
            '/admin/cms/pages/'.$page->getId().'/builder',
            '/admin/cms/pages/trash',
            '/admin/cms/menus',
            '/admin/cms/menus/new',
            '/admin/cms/menus/'.$menu->getId(),
            '/admin/cms/blocks',
            '/admin/cms/blocks/new',
            '/admin/cms/blocks/'.$block->getId(),
            '/admin/cms/blocks/'.$block->getId().'/builder',
            '/admin/cms/forms',
            '/admin/cms/forms/new',
            '/admin/cms/forms/'.$form->getId(),
            '/admin/cms/forms/'.$form->getId().'/submissions',
            '/admin/cms/media',
            '/admin/cms/templates',
            '/admin/cms/settings',
        ];

        $offending = [];
        $posting = 0;

        foreach ($screens as $url) {
            $screen = $this->screen($url);
            $html = (string) $this->client->getResponse()->getContent();

            if (1 === preg_match('/[?&](amp;)?_token=/', $html)) {
                $offending[] = \sprintf('%s puts the token in a URL', $url);
            }

            $screen->filter('form')->each(function (Crawler $form) use ($url, &$offending, &$posting): void {
                if (!$this->posts($form)) {
                    return;
                }

                ++$posting;

                if (1 !== $form->filter('input[name="_token"]')->count()) {
                    $offending[] = \sprintf('%s: the form posting to %s carries no token', $url, (string) $form->attr('action'));
                }
            });
        }

        self::assertGreaterThan(30, $posting, 'The forms of the screens are found.');
        self::assertSame([], $offending);
    }

    private function posts(Crawler $form): bool
    {
        if ('post' === strtolower((string) $form->attr('method'))) {
            return true;
        }

        // A GET form whose button posts it elsewhere.
        return \in_array('post', array_map('strtolower', $form->filter('[formmethod]')->extract(['formmethod'])), true);
    }

    /**
     * @return array<string, Route>
     */
    private function routes(): array
    {
        $router = $this->getService('router');
        \assert($router instanceof RouterInterface);

        $routes = [];

        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $controller = $route->getDefault('_controller');

            if (\is_string($controller) && str_starts_with($controller, 'TheliaCMS\\') && str_starts_with($route->getPath(), self::PREFIX)) {
                $routes[$name] = $route;
            }
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function writeMethods(Route $route): array
    {
        return array_values(array_diff($route->getMethods(), ['GET', 'HEAD', 'OPTIONS']));
    }

    private function isHidden(Route $route): bool
    {
        foreach (self::HIDDEN_PREFIXES as $hidden) {
            if (str_starts_with($route->getPath(), $hidden)) {
                return true;
            }
        }

        return false;
    }

    private function sampleUrl(Route $route): string
    {
        return (string) preg_replace_callback(
            '/\{(\w+)\}/',
            static fn (array $match): string => self::SAMPLE_PARAMETERS[$match[1]] ?? '999999999',
            $route->getPath(),
        );
    }
}
