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

use Thelia\Model\RewritingUrlQuery;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The address the edit screen proposes for a page, before the form is saved.
 *
 * The slug field asks the server, which computes the address the page would get
 * and writes nothing: a published address moves when the editor saves, never
 * while they type.
 */
final class AddressProposalScreenTest extends AdminScreenTestCase
{
    private const string ROUTE = '/admin/cms/pages/address-proposal';

    private const string FIXED_ROUTE = '/admin/cms/pages/fixed-address-check';

    public function testTheEditScreenTellsTheSlugFieldWhereToAsk(): void
    {
        $page = $this->createPage('Atelier de reliure');

        $field = $this->screen(\sprintf('/admin/cms/pages/%d', $page->getId()))->filter('input[name="cms_page[slug]"]');

        self::assertSame(1, $field->count());
        self::assertSame(self::ROUTE, parse_url((string) $field->attr('data-proposal-url'), \PHP_URL_PATH));
        self::assertSame((string) $page->getId(), $field->attr('data-page-id'));
        self::assertSame('cms_page_title', $field->attr('data-title-field'), 'The script finds the title field by the id Symfony gives it.');
        self::assertSame('cms_page_parent', $field->attr('data-parent-field'));
    }

    public function testAProposalFollowsTheNewTitleAndWritesNothing(): void
    {
        $page = $this->createPage('Atelier de dorure');

        $answer = $this->propose(['page' => (string) $page->getId(), 'title' => 'Atelier de dorure à la feuille', 'slug' => 'atelier-de-dorure']);

        self::assertSame(200, $answer['status']);
        self::assertSame('atelier-de-dorure', $answer['body']['segment'], 'The slug typed wins over the title.');
        self::assertSame('atelier-de-dorure', $answer['body']['address']);
        self::assertFalse($answer['body']['suffixed']);
        self::assertSame('atelier-de-dorure-a-la-feuille', $answer['body']['title_segment'], 'The screen can tell the slug no longer follows the title.');
        self::assertNull(RewritingUrlQuery::create()->findOneByUrl('atelier-de-dorure-a-la-feuille'), 'Nothing is written.');
    }

    public function testAProposalSaysWhenTheAddressIsTaken(): void
    {
        $this->createPage('Atelier de marqueterie');

        $answer = $this->propose(['page' => '0', 'title' => 'Atelier de marqueterie']);

        self::assertSame('atelier-de-marqueterie-2', $answer['body']['address']);
        self::assertSame('atelier-de-marqueterie', $answer['body']['segment']);
        self::assertTrue($answer['body']['suffixed']);
    }

    public function testAReservedSlugIsRefusedWithAMessage(): void
    {
        $answer = $this->propose(['page' => '0', 'title' => 'Administration', 'slug' => 'admin']);

        self::assertSame(422, $answer['status']);
        self::assertStringContainsString('reserved', (string) $answer['body']['error']);
    }

    public function testNothingIsProposedWithoutATitleOrASlug(): void
    {
        self::assertSame(422, $this->propose(['page' => '0', 'title' => ' '])['status']);
    }

    public function testAProposalForAPageThatDoesNotExistIsNotFound(): void
    {
        self::assertSame(404, $this->propose(['page' => '999999999', 'title' => 'Fantôme'])['status']);
    }

    /**
     * A reserved slug saved from the form is shown on the field, as a fixed
     * address another page holds is: the editor fixes it there.
     */
    public function testSavingAReservedSlugIsRefusedOnTheField(): void
    {
        $page = $this->createPage('Atelier de gravure');
        $url = \sprintf('/admin/cms/pages/%d', $page->getId());

        $form = $this->screen($url)->filter('form[name="cms_page"]')->form();
        $form['cms_page[slug]'] = 'admin';

        self::assertSame(200, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertStringContainsString('reserved', $this->client->getCrawler()->filter('.invalid-feedback, .form-error-message')->text(''));
        self::assertSame('atelier-de-gravure', $page->getRewrittenUrl($this->locale()));
    }

    public function testTheFixedAddressFieldIsToldWhereToCheck(): void
    {
        $page = $this->createPage('Atelier de vitrail');

        $field = $this->screen(\sprintf('/admin/cms/pages/%d', $page->getId()))->filter('input[name="cms_page[fixedUrl]"]');

        self::assertSame(self::FIXED_ROUTE, parse_url((string) $field->attr('data-check-url'), \PHP_URL_PATH));
        self::assertSame((string) $page->getId(), $field->attr('data-page-id'));
    }

    /**
     * A fixed address is kept as typed: it is checked as the save checks it,
     * free, its own, or held by another page, and nothing is written.
     */
    public function testAFixedAddressIsCheckedWithoutBeingWritten(): void
    {
        $page = $this->createPage('Atelier de tapisserie');
        $other = $this->createPage('Atelier de broderie');

        $free = $this->propose(['page' => (string) $page->getId(), 'address' => '/ancien-site/tapisserie.html'], self::FIXED_ROUTE);

        self::assertSame(200, $free['status']);
        self::assertSame('ancien-site/tapisserie.html', $free['body']['address'], 'The address is normalized as on save.');
        self::assertNull(RewritingUrlQuery::create()->findOneByUrl('ancien-site/tapisserie.html'), 'Nothing is written.');

        $own = $this->propose(['page' => (string) $page->getId(), 'address' => 'atelier-de-tapisserie'], self::FIXED_ROUTE);

        self::assertSame(200, $own['status'], 'The address the page already answers on is its own.');

        $taken = $this->propose(['page' => (string) $page->getId(), 'address' => (string) $other->getRewrittenUrl($this->locale())], self::FIXED_ROUTE);

        self::assertSame(422, $taken['status']);
        self::assertStringContainsString('already the address', (string) $taken['body']['error']);

        self::assertSame(422, $this->propose(['page' => '0', 'address' => 'admin/tapisserie'], self::FIXED_ROUTE)['status'], 'A reserved path is refused.');
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function propose(array $parameters, string $route = self::ROUTE): array
    {
        $token = $this->tokenOfTheScripts($this->screen('/admin/cms/pages'));
        $status = $this->send('POST', $route, $parameters, [...self::XHR, 'HTTP_X_CSRF_TOKEN' => $token]);
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);

        return ['status' => $status, 'body' => \is_array($body) ? $body : []];
    }
}
