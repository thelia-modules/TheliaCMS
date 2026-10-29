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

namespace TheliaCMS\Tests\Integration\Generation;

use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationTarget;
use TheliaCMS\Generation\Tool\GeneratorTools;
use TheliaCMS\Generation\Tool\SearchFormsTool;
use TheliaCMS\Model\CmsForm;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The forms of the site, as the model finds them: a contact page asks for
 * one, and its id has to come from here.
 */
final class SearchFormsToolTest extends CmsIntegrationTestCase
{
    public function testTheFormsOfTheSiteAreListedWithTheirId(): void
    {
        $contact = (new CmsForm())->setCode('contact-tool');
        $contact->setLocale($this->locale())->setTitle('Nous écrire');
        $contact->save();
        $quote = (new CmsForm())->setCode('quote-tool');
        $quote->setLocale($this->locale())->setTitle('Demande de devis');
        $quote->save();
        $request = $this->request();

        $tool = $this->getService(SearchFormsTool::class);
        $all = json_decode($tool->run(['query' => ''], $request), true, 8, \JSON_THROW_ON_ERROR);

        self::assertContains(['id' => (int) $contact->getId(), 'code' => 'contact-tool', 'title' => 'Nous écrire', 'fields' => 0], $all);
        self::assertContains(['id' => (int) $quote->getId(), 'code' => 'quote-tool', 'title' => 'Demande de devis', 'fields' => 0], $all);

        $some = json_decode($tool->run(['query' => 'devis'], $request), true, 8, \JSON_THROW_ON_ERROR);
        self::assertSame([(int) $quote->getId()], array_column($some, 'id'), 'A query matches the title or the code.');

        self::assertStringContainsString('No form matches', $tool->run(['query' => 'xyzzy'], $request));
        self::assertContains('search_forms', array_column($this->getService(GeneratorTools::class)->definitions(), 'name'), 'The tool is offered to the model.');
    }

    private function request(): GenerationRequest
    {
        $page = $this->createPage('Forms tool', published: false);

        return GenerationRequest::fromBrief(GenerationTarget::page($page, $this->locale()), $this->locale(), new Brief('A contact page.'));
    }
}
