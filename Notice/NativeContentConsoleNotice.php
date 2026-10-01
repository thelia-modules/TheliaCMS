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

namespace TheliaCMS\Notice;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Writes the native content notice at the end of the commands that bring the
 * CMS onto a shop holding contents.
 *
 * Written on the console output of the command itself, at the end of the
 * command, through the console events: nothing here assumes a request, and an
 * activation from the back office simply never reaches it (the Pages screen
 * shows the banner there).
 *
 * Two ways in, because of when the services of a module exist:
 * - `module:activate TheliaCMS` (and `module:post-activate-all`, which
 *   `bin/install` runs) build their container before the module is active, so
 *   no listener of this module is registered for them. `postActivation()` hands
 *   its dispatcher to `writeWhenTheCommandEnds()` instead;
 * - `thelia:demo:import`, which `bin/install --with-demo` runs once the module
 *   is active, is where an install gets its contents: this listener answers it.
 */
final readonly class NativeContentConsoleNotice
{
    /** Commands that fill a shop where the module is already active. */
    private const array CONTENT_IMPORTING_COMMANDS = ['thelia:demo:import'];

    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if (!\in_array($event->getCommand()?->getName(), self::CONTENT_IMPORTING_COMMANDS, true)) {
            return;
        }

        self::write($event);
    }

    /**
     * Writes the notice once, at the end of the command being run, when it
     * succeeds. Called from `TheliaCMS::postActivation()`.
     */
    public static function writeWhenTheCommandEnds(EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->addListener(ConsoleEvents::TERMINATE, static function (ConsoleTerminateEvent $event): void {
            self::write($event);
        });
    }

    private static function write(ConsoleTerminateEvent $event): void
    {
        if (Command::SUCCESS !== $event->getExitCode()) {
            return;
        }

        $line = (new NativeContentNotice())->consoleLine();

        if (null !== $line) {
            $event->getOutput()->writeln(\sprintf('<comment>%s</comment>', $line), OutputInterface::VERBOSITY_NORMAL);
        }
    }
}
