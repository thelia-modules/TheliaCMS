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

use Thelia\Core\Security\AccessManager;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Security\CmsResources;

/**
 * Uploading an image from the page form adds it to the CMS library: it takes
 * the right to create media, the one the media screen asks for, and not only
 * the right to change pages.
 */
final class ImageUploadPermissionTest extends AdminScreenTestCase
{
    private const string UPLOAD_FIELD = 'input[name="cms_page[imageUpload]"]';

    public function testAnEditorWithoutTheMediaRightIsNotOfferedTheUpload(): void
    {
        $page = $this->createPage('Page sans téléversement');

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW, AccessManager::UPDATE],
        ]));

        $screen = $this->screen(\sprintf('/admin/cms/pages/%d', $page->getId()));

        self::assertSame(1, $screen->filter('form[name="cms_page"]')->count(), 'The page form is not on the screen.');
        self::assertSame(0, $screen->filter(self::UPLOAD_FIELD)->count());
    }

    public function testAnEditorWithTheMediaRightIsOfferedTheUpload(): void
    {
        $page = $this->createPage('Page avec téléversement');

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW, AccessManager::UPDATE],
            CmsResources::MEDIA => [AccessManager::VIEW, AccessManager::CREATE],
        ]));

        $screen = $this->screen(\sprintf('/admin/cms/pages/%d', $page->getId()));

        self::assertSame(1, $screen->filter(self::UPLOAD_FIELD)->count());
    }
}
