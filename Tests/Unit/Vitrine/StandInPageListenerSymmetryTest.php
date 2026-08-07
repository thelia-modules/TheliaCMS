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

namespace TheliaCMS\Tests\Unit\Vitrine;

use PHPUnit\Framework\TestCase;

/**
 * Everything the 404 listener writes on a request before rendering has to be
 * undone when the render fails, otherwise the theme answers its own page while
 * the canonical, the hreflang tags and the breadcrumb name a page nobody was
 * shown.
 *
 * Read from the source rather than exercised: reaching the fallback would mean
 * a render that throws, and the renderer falls back to the template shipped
 * with the module instead of failing. So a test driving the listener cannot get
 * there, and the risk is not a wrong value — it is a line added on one side and
 * forgotten on the other.
 */
final class StandInPageListenerSymmetryTest extends TestCase
{
    private const string LISTENER_FILE = __DIR__.'/../../../Vitrine/NotFoundPageListener.php';

    public function testEverythingWrittenBeforeRenderingIsUndoneWhenItFails(): void
    {
        $written = $this->keysTouchedBy('serveAs');
        $undone = $this->keysTouchedBy('restoreView');

        self::assertNotEmpty($written, 'The method that names the page served is expected to be readable.');

        foreach ($written as $key) {
            self::assertContains(
                $key,
                $undone,
                \sprintf('serveAs() writes %s and restoreView() leaves it behind on a request the theme answers.', $key),
            );
        }
    }

    /**
     * The request keys a method names.
     *
     * Names rather than calls: `restoreView()` walks a map of key to previous
     * value, so the key never appears as the argument of `set()`. What matters
     * is that every key one side touches is spelled out by the other.
     *
     * @return list<string>
     */
    private function keysTouchedBy(string $method): array
    {
        self::assertSame(
            1,
            preg_match(
                \sprintf('#private function %s\(.*?\n {4}\}#s', preg_quote($method, '#')),
                $this->read(self::LISTENER_FILE),
                $matches,
            ),
            \sprintf('%s() is expected to be readable as a whole.', $method),
        );

        // A key is either a literal starting with an underscore, the way Symfony
        // names the ones it owns, or a constant named after what it is — the
        // values written under those keys are constants too, and reading them as
        // keys would make this test complain about the view name.
        preg_match_all("#'_[a-z_]+'|(?:self|TheliaCMS)::[A-Z_]+_(?:PARAMETER|ATTRIBUTE)#", $matches[0], $keys);

        return array_values(array_unique($keys[0]));
    }

    private function read(string $file): string
    {
        $source = file_get_contents($file);

        self::assertIsString($source, \sprintf('%s is expected to be readable.', $file));

        return $source;
    }
}
