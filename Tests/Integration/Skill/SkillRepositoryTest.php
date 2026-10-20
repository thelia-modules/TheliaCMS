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

namespace TheliaCMS\Tests\Integration\Skill;

use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\TemplateHelperInterface;
use TheliaCMS\Model\CmsSkillQuery;
use TheliaCMS\Skill\SkillFiles;
use TheliaCMS\Skill\SkillRepository;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The shipped files are sown once, the site edits the rows, and a file that
 * changes afterwards updates what the site never touched.
 */
final class SkillRepositoryTest extends CmsIntegrationTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/theliacms-skills-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/alpha', 0o777, true);
        mkdir($this->directory.'/beta', 0o777, true);
        file_put_contents($this->directory.'/alpha/SKILL.md', "---\nname: alpha\ndescription: First.\nalways: true\n---\nAlpha rules.");
        file_put_contents($this->directory.'/beta/SKILL.md', "---\nname: beta\ndescription: Second.\n---\nBeta rules.");

        // Rows of the module's own skills, sown by another test, would mix in.
        CmsSkillQuery::create()->deleteAll();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*/SKILL.md') ?: [] as $file) {
            unlink($file);
            rmdir(\dirname($file));
        }

        @rmdir($this->directory);

        parent::tearDown();
    }

    public function testTheShippedFilesAreSownOnceAndReadInOrder(): void
    {
        $repository = $this->repository();

        $skills = $repository->all();

        self::assertSame(['alpha', 'beta'], array_map(static fn ($skill) => $skill->code, $skills));
        self::assertTrue($skills[0]->always);
        self::assertFalse($skills[1]->always);
        self::assertSame('Alpha rules.', $skills[0]->body);
        self::assertSame('module', $skills[0]->origin);

        $repository->all();
        self::assertSame(2, CmsSkillQuery::create()->count(), 'Reading again sows nothing twice.');
    }

    public function testTheSiteEditsASkillAndAChangedFileDoesNotOverwriteIt(): void
    {
        $repository = $this->repository();
        $repository->all();

        $edited = $repository->save('alpha', 'Alpha rules, rewritten by the site.', enabled: true);

        self::assertTrue($edited->isEdited());

        file_put_contents($this->directory.'/alpha/SKILL.md', "---\nname: alpha\ndescription: First, revised.\nalways: true\n---\nAlpha rules, version 2.");
        file_put_contents($this->directory.'/beta/SKILL.md', "---\nname: beta\ndescription: Second.\n---\nBeta rules, version 2.");

        $alpha = $repository->find('alpha');
        $beta = $repository->find('beta');

        self::assertSame('Alpha rules, rewritten by the site.', $alpha?->body, 'What the site wrote is kept.');
        self::assertSame('Alpha rules, version 2.', $alpha?->shippedBody, 'What the file now says is kept next to it.');
        self::assertSame('First, revised.', $alpha?->description);
        self::assertSame('Beta rules, version 2.', $beta?->body, 'A skill the site never touched follows its file.');

        self::assertSame('Alpha rules, version 2.', $repository->restore('alpha')->body);
    }

    public function testADisabledSkillIsNotReadByTheGenerator(): void
    {
        $repository = $this->repository();
        $repository->all();
        $repository->save('beta', 'Beta rules.', enabled: false);

        self::assertSame(['alpha'], array_map(static fn ($skill) => $skill->code, $repository->enabled()));
    }

    public function testASkillTheFilesNeverShippedCannotBeRestored(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository()->restore('nope');
    }

    private function repository(): SkillRepository
    {
        return new SkillRepository(
            new SkillFiles($this->getService(TemplateHelperInterface::class), $this->directory),
            $this->getService(SecurityContext::class),
        );
    }
}
