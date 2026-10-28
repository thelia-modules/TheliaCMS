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

namespace TheliaCMS\Tests\Integration\Media;

use Symfony\Component\DomCrawler\Crawler;
use Thelia\Core\Security\AccessManager;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Model\LibraryImageQuery;
use TheliaLibrary\Model\Map\LibraryImageTableMap;

/**
 * The describe queue: the images of a list that say nothing in the language,
 * one at a time, answered and gone.
 */
final class MediaDescribeScreenTest extends AdminScreenTestCase
{
    private const string QUEUE = '/admin/cms/media/describe';

    public function testADescribedImageLeavesTheQueueAndTheNextOneTakesItsPlace(): void
    {
        $first = $this->image('Première');
        $second = $this->image('Deuxième');
        $this->image('Troisième');
        $this->image('Déjà décrite', alt: 'Une chaise');

        $screen = $this->screen(self::QUEUE.'?order=oldest');
        self::assertSame($first->getId(), $this->shownId($screen));
        self::assertStringContainsString('1', $screen->filter('[data-testid="cms-media-describe-position"]')->text());

        self::assertSame(302, $this->answer($screen, ['alt' => 'Un fauteuil vert']));
        self::assertSame('Un fauteuil vert', $this->reloaded($first)->setLocale($this->locale())->getAlt());

        $next = $this->screen((string) $this->client->getResponse()->headers->get('Location'));
        self::assertSame($second->getId(), $this->shownId($next), 'The next image is at the position of the one described.');
    }

    public function testItIsDecorativeIsAnAnswerOfItsOwn(): void
    {
        $image = $this->image('Séparateur');

        self::assertSame(302, $this->answer($this->screen(self::QUEUE), ['alt' => 'ignoré'], decorative: true));

        $saved = $this->reloaded($image)->setLocale($this->locale());
        self::assertSame(1, (int) $saved->getDecorative());
        self::assertNull($saved->getAlt(), 'A decorative image is published with an empty alternative text.');
    }

    public function testAnEmptyAnswerIsRefusedAndTheImageStays(): void
    {
        $image = $this->image('Sans réponse');

        $screen = $this->screen(self::QUEUE);
        self::assertSame(200, $this->answer($screen, ['alt' => '  ']));
        self::assertSame($image->getId(), $this->shownId(new Crawler((string) $this->client->getResponse()->getContent(), 'http://localhost')));
        self::assertNull($this->reloaded($image)->setLocale($this->locale())->getAlt());
    }

    public function testTheQueueFollowsTheListItWasOpenedFromAndCanSkip(): void
    {
        $library = $this->getService(CmsMediaLibrary::class);
        $armchair = $this->image('Fauteuil');
        $library->retag($armchair, ['Fauteuils']);
        $otherArmchair = $this->image('Autre fauteuil');
        $library->retag($otherArmchair, ['Fauteuils']);
        $this->image('Chaise');

        $screen = $this->screen(self::QUEUE.'?tag=Fauteuils&order=oldest');
        self::assertSame($armchair->getId(), $this->shownId($screen));

        $skip = (string) $screen->filter('[data-testid="cms-media-describe-skip"]')->attr('href');
        $skipped = $this->screen($skip);
        self::assertSame($otherArmchair->getId(), $this->shownId($skipped), 'The chair is not in the queue of the armchairs.');
        self::assertSame(0, $skipped->filter('[data-testid="cms-media-describe-skip"]')->count(), 'The last image has nothing to skip to.');
        self::assertStringContainsString('tag=Fauteuils', (string) $skipped->filter('[data-testid="cms-media-back"]')->attr('href'));
    }

    public function testOnceEverythingIsDescribedTheQueueLeadsBackToTheList(): void
    {
        $this->image('Décrite', alt: 'Une table');

        $this->client->request('GET', self::QUEUE.'?tag=Inconnue&order=title');

        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/admin/cms/media?', $location);
        self::assertStringContainsString('order=title', $location);
    }

    public function testTheListOffersTheQueueOnlyWhenSomethingIsToDescribe(): void
    {
        $image = $this->image('Décrite', alt: 'Une table');
        self::assertSame(0, $this->screen('/admin/cms/media')->filter('[data-testid="cms-media-describe-start"]')->count());

        $this->image('À décrire');
        self::assertSame(1, $this->screen('/admin/cms/media')->filter('[data-testid="cms-media-describe-start"]')->count());
        self::assertNotNull($image->getId());
    }

    public function testWithoutTheRightToChangeMediaNothingIsSaved(): void
    {
        $image = $this->image('Protégée');
        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::MEDIA => [AccessManager::VIEW],
        ]));

        self::assertSame(403, $this->answer($this->screen(self::QUEUE), ['alt' => 'Une tentative']));
        self::assertNull($this->reloaded($image)->setLocale($this->locale())->getAlt());
    }

    /**
     * Posts the form of the screen as the editor would, with what it carries.
     *
     * @param array<string, string> $fields
     */
    private function answer(Crawler $screen, array $fields, bool $decorative = false): int
    {
        $node = $screen->filter('[data-testid="cms-media-describe-form"]');
        self::assertSame(1, $node->count(), 'The screen shows the form.');

        $form = $node->form();
        $values = $form->getPhpValues();

        foreach ($fields as $name => $value) {
            $values['media_description'][$name] = $value;
        }

        if ($decorative) {
            $values['media_description']['decorative'] = '1';
        }

        return $this->send('POST', (string) $node->attr('action'), $values);
    }

    private function shownId(Crawler $screen): ?int
    {
        $link = $screen->filter('a[href*="/admin/cms/media/"]')->reduce(
            static fn (Crawler $a): bool => 1 === preg_match('#/admin/cms/media/\d+#', (string) $a->attr('href')),
        );

        return 0 === $link->count() ? null : (int) preg_replace('#^.*/admin/cms/media/(\d+).*$#', '$1', (string) $link->first()->attr('href'));
    }

    private function reloaded(LibraryImage $image): LibraryImage
    {
        LibraryImageTableMap::clearInstancePool();

        $found = LibraryImageQuery::create()->findPk($image->getId());
        self::assertNotNull($found);

        return $found;
    }

    private function image(string $title, ?string $alt = null): LibraryImage
    {
        $image = (new LibraryImage())->setFileName(bin2hex(random_bytes(6)).'.jpg')->setWidth(800)->setHeight(600)->setDecorative(0);
        $image->setLocale($this->locale())->setTitle($title)->setAlt($alt);
        $image->save();

        $this->getService(CmsMediaLibrary::class)->tag($image);

        return $image;
    }
}
