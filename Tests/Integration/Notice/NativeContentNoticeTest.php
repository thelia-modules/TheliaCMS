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

namespace TheliaCMS\Tests\Integration\Notice;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Model\ContentQuery;
use Thelia\Test\Trait\LogsInAsAdmin;
use Thelia\Tools\TokenProvider;
use TheliaCMS\Notice\NativeContentConsoleNotice;
use TheliaCMS\Notice\NativeContentNotice;
use TheliaCMS\Notice\NativeContentNoticeController;
use TheliaCMS\Page\Admin\CmsPageAdminController;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * A shop that already had contents when the CMS arrived is told they stay in
 * Folders and that nothing is converted: on the console of the command that
 * brings the module in, and on the Pages screen until somebody closes it.
 */
final class NativeContentNoticeTest extends CmsIntegrationTestCase
{
    use LogsInAsAdmin;

    private const string LINE = 'stay in Folders';

    private ?string $previousDismissal = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousDismissal = TheliaCMS::getConfigValue(NativeContentNotice::DISMISSED_SETTING);
        TheliaCMS::setConfigValue(NativeContentNotice::DISMISSED_SETTING, '0');

        $factory = $this->createFixtureFactory();
        $factory->content($factory->folder());
    }

    protected function tearDown(): void
    {
        TheliaCMS::setConfigValue(NativeContentNotice::DISMISSED_SETTING, $this->previousDismissal ?? '0');
        $this->logoutAdmin();

        parent::tearDown();
    }

    public function testTheDemoImportEndsOnTheNotice(): void
    {
        self::assertStringContainsString(self::LINE, $this->terminate('thelia:demo:import'));
    }

    public function testOtherCommandsSayNothing(): void
    {
        self::assertSame('', $this->terminate('cache:clear'));
        self::assertSame('', $this->terminate('thelia:demo:import', Command::FAILURE), 'A command that failed has other things to say.');
    }

    public function testTheCommandActivatingTheModuleEndsOnTheNotice(): void
    {
        $dispatcher = new EventDispatcher();
        NativeContentConsoleNotice::writeWhenTheCommandEnds($dispatcher);

        self::assertStringContainsString(self::LINE, $this->terminate('module:activate', dispatcher: $dispatcher));
    }

    public function testAShopWithoutContentIsToldNothing(): void
    {
        ContentQuery::create()->find()->delete();

        self::assertNull($this->getService(NativeContentNotice::class)->consoleLine());
        self::assertSame('', $this->terminate('thelia:demo:import'));
        self::assertNull($this->getService(NativeContentNotice::class)->bannerCount());
    }

    public function testThePagesScreenShowsTheNoticeUntilItIsClosed(): void
    {
        $this->loginAsAdminInSession();
        $request = $this->adminRequest('/admin/cms/pages');

        self::assertStringContainsString('data-testid="cms-native-content-notice"', $this->pagesScreen($request));

        $token = $this->getService(TokenProvider::class)->assignToken();
        $dismiss = $this->adminRequest('/admin/cms/pages/native-content-notice/dismiss', 'POST', ['_token' => $token]);
        $response = $this->getService(NativeContentNoticeController::class)->dismiss($dismiss);

        self::assertTrue($response->isRedirection());
        self::assertStringNotContainsString('data-testid="cms-native-content-notice"', $this->pagesScreen($request));
    }

    public function testClosingTheNoticeTakesTheTokenOfTheBackOffice(): void
    {
        $this->loginAsAdminInSession();
        $this->getService(TokenProvider::class)->assignToken();

        try {
            $this->getService(NativeContentNoticeController::class)->dismiss(
                $this->adminRequest('/admin/cms/pages/native-content-notice/dismiss', 'POST', ['_token' => 'forged']),
            );
            self::fail('The notice was closed without the token.');
        } catch (TokenAuthenticationException) {
            self::assertFalse($this->getService(NativeContentNotice::class)->isDismissed());
        }
    }

    private function terminate(string $commandName, int $exitCode = Command::SUCCESS, ?EventDispatcherInterface $dispatcher = null): string
    {
        $output = new BufferedOutput();
        $event = new ConsoleTerminateEvent(new Command($commandName), new ArrayInput([]), $output, $exitCode);

        ($dispatcher ?? $this->getService(EventDispatcherInterface::class))->dispatch($event, ConsoleEvents::TERMINATE);

        return $output->fetch();
    }

    /**
     * @param array<string, string> $body
     */
    private function adminRequest(string $path, string $method = 'GET', array $body = []): Request
    {
        $stack = $this->getService(RequestStack::class);
        $session = $stack->getMainRequest()?->hasSession() ? $stack->getMainRequest()->getSession() : new Session(new MockArraySessionStorage());

        $request = Request::create($path, $method, $body);
        $request->setSession($session);
        $stack->push($request);

        return $request;
    }

    private function pagesScreen(Request $request): string
    {
        return (string) $this->getService(CmsPageAdminController::class)->list($request)->getContent();
    }
}
