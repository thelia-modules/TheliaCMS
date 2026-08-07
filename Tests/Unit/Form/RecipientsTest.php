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

namespace TheliaCMS\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Form\Recipients;

/**
 * A form whose recipient is mistyped sends nowhere and says nothing, which is
 * the worst way for a contact form to fail.
 */
final class RecipientsTest extends TestCase
{
    public function testReadsAddressesSeparatedByCommas(): void
    {
        self::assertSame(
            ['sales@example.org', 'hello@example.org'],
            Recipients::parse('sales@example.org, hello@example.org'),
        );
    }

    public function testAlsoAcceptsSemicolonsAndSpaces(): void
    {
        self::assertSame(
            ['one@example.org', 'two@example.org'],
            Recipients::parse('one@example.org; two@example.org'),
        );
    }

    public function testLeavesOutWhatIsNotAnAddress(): void
    {
        self::assertSame(['good@example.org'], Recipients::parse('good@example.org, not-an-address'));
    }

    public function testNamesWhatItLeftOutSoTheBackOfficeCanSayWhichOne(): void
    {
        self::assertSame(['not-an-address'], Recipients::rejected('good@example.org, not-an-address'));
        self::assertSame([], Recipients::rejected('good@example.org'));
    }

    public function testSendsToOneAddressOnceEvenIfItIsWrittenTwice(): void
    {
        self::assertSame(['one@example.org'], Recipients::parse('one@example.org, one@example.org'));
    }

    public function testHasNoRecipientWhenNothingWasWritten(): void
    {
        self::assertSame([], Recipients::parse(null));
        self::assertSame([], Recipients::parse('   '));
    }

    public function testStopsAtTheMaximumNumberOfRecipients(): void
    {
        $written = implode(',', array_map(static fn (int $i): string => "person{$i}@example.org", range(1, 30)));

        self::assertCount(Recipients::MAX, Recipients::parse($written));
    }
}
