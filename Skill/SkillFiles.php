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

namespace TheliaCMS\Skill;

use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * The skills shipped as files: `skills/<code>/SKILL.md` in the module, and
 * the same layout in the active theme and its parents.
 *
 * A file opens with a front matter — `name`, `description`, `always` — and
 * the body follows. The code is the name of the directory. A theme skill
 * with the code of a module skill replaces it: the theme knows its site.
 */
final readonly class SkillFiles
{
    public function __construct(
        private TemplateHelperInterface $templates,
        private string $moduleSkillsDirectory = __DIR__.'/../skills',
    ) {
    }

    /**
     * @return list<Skill> in the order they are shipped: the module's, then the theme's
     */
    public function all(): array
    {
        $skills = [];
        $position = 0;

        foreach (self::readDirectory($this->moduleSkillsDirectory, Skill::ORIGIN_MODULE, $position) as $skill) {
            $skills[$skill->code] = $skill;
        }

        foreach (array_reverse($this->themeDirectories()) as $directory) {
            foreach (self::readDirectory($directory.'/skills', Skill::ORIGIN_THEME, $position) as $skill) {
                $skills[$skill->code] = $skill;
            }
        }

        return array_values($skills);
    }

    /**
     * @return array{name: string, description: string, always: bool, body: string}
     *
     * @throws \InvalidArgumentException when the file has no readable front matter
     */
    public static function parse(string $content, string $file): array
    {
        if (1 !== preg_match('/\A---\s*\n(.*?)\n---\s*\n(.*)\z/s', $content, $match)) {
            throw new \InvalidArgumentException(\sprintf('%s does not open with a front matter between two "---" lines.', $file));
        }

        $front = [];

        foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
            if (1 === preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $pair)) {
                $front[$pair[1]] = trim($pair[2], " \t\"'");
            }
        }

        if ('' === ($front['name'] ?? '') || '' === ($front['description'] ?? '')) {
            throw new \InvalidArgumentException(\sprintf('%s must declare a name and a description in its front matter.', $file));
        }

        return [
            'name' => $front['name'],
            'description' => $front['description'],
            'always' => \in_array(strtolower($front['always'] ?? 'false'), ['true', 'yes', '1'], true),
            'body' => trim($match[2]),
        ];
    }

    /**
     * @return list<Skill>
     */
    private static function readDirectory(string $directory, string $origin, int &$position): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob(rtrim($directory, '/').'/*/SKILL.md') ?: [];
        sort($files);
        $skills = [];

        foreach ($files as $file) {
            $parsed = self::parse((string) file_get_contents($file), $file);
            $code = basename(\dirname($file));

            $skills[] = new Skill(
                code: $code,
                name: $parsed['name'],
                description: $parsed['description'],
                body: $parsed['body'],
                origin: $origin,
                position: ++$position,
                always: $parsed['always'],
                shippedBody: $parsed['body'],
            );
        }

        return $skills;
    }

    /**
     * @return list<string> the active theme first, then its parents
     */
    private function themeDirectories(): array
    {
        try {
            $theme = $this->templates->getActiveFrontTemplate();
        } catch (\Throwable) {
            return [];
        }

        return array_map(
            static fn (TemplateDefinition $template): string => $template->getAbsolutePath(),
            [$theme, ...array_values($theme->getParentList() ?? [])],
        );
    }
}
