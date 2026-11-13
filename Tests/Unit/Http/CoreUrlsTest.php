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

namespace TheliaCMS\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Thelia\Tools\URL;
use TheliaCMS\Http\CoreUrls;

final class CoreUrlsTest extends TestCase
{
    private ?URL $previous;

    protected function setUp(): void
    {
        $this->previous = self::stored();
    }

    protected function tearDown(): void
    {
        self::store($this->previous);
    }

    public function testNoServiceBuiltYetIsNullRatherThanAnException(): void
    {
        self::store(null);

        self::assertNull(CoreUrls::instance());
    }

    public function testTheServiceOnceBuiltIsGiven(): void
    {
        $urls = (new \ReflectionClass(URL::class))->newInstanceWithoutConstructor();
        self::store($urls);

        self::assertSame($urls, CoreUrls::instance());
    }

    private static function stored(): ?URL
    {
        $instance = (new \ReflectionProperty(URL::class, 'instance'))->getValue();

        return $instance instanceof URL ? $instance : null;
    }

    private static function store(?URL $urls): void
    {
        (new \ReflectionProperty(URL::class, 'instance'))->setValue(null, $urls);
    }
}
