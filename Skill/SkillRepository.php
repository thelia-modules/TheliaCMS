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

use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Model\CmsSkill;
use TheliaCMS\Model\CmsSkillQuery;

/**
 * The skills of the site, as `cms_skill` holds them.
 *
 * The shipped files are the seed and the reference: the first read sows them,
 * every read afterwards updates `shipped_body` where a file changed, and the
 * body itself only when the site never rewrote it. A skill the files no longer
 * ship is kept: the site may have come to depend on it.
 */
final readonly class SkillRepository
{
    public function __construct(
        private SkillFiles $files,
        private SecurityContext $securityContext,
    ) {
    }

    /**
     * @return list<Skill> every skill, enabled or not, in position order
     */
    public function all(): array
    {
        $this->sow();

        return array_map(self::fromModel(...), iterator_to_array(
            CmsSkillQuery::create()->orderByPosition()->orderByCode()->find(),
            false,
        ));
    }

    /**
     * @return list<Skill> the skills the generator reads, in position order
     */
    public function enabled(): array
    {
        return array_values(array_filter($this->all(), static fn (Skill $skill): bool => $skill->enabled));
    }

    public function find(string $code): ?Skill
    {
        $this->sow();
        $model = CmsSkillQuery::create()->findOneByCode($code);

        return null === $model ? null : self::fromModel($model);
    }

    /**
     * @throws \InvalidArgumentException when the code names no skill
     */
    public function save(string $code, string $body, bool $enabled, ?int $position = null): Skill
    {
        $model = CmsSkillQuery::create()->findOneByCode($code);

        if (null === $model) {
            throw new \InvalidArgumentException(\sprintf('No skill is called "%s".', $code));
        }

        $model->setBody(trim($body))
            ->setEnabled($enabled ? 1 : 0)
            ->setUpdatedBy($this->securityContext->getAdminUser()?->getId());

        if (null !== $position) {
            $model->setPosition($position);
        }

        $model->save();

        return self::fromModel($model);
    }

    /**
     * Puts the shipped text back.
     *
     * @throws \InvalidArgumentException when the code names no skill, or one the files never shipped
     */
    public function restore(string $code): Skill
    {
        $model = CmsSkillQuery::create()->findOneByCode($code);

        if (null === $model || null === $model->getShippedBody()) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a shipped skill: there is nothing to restore.', $code));
        }

        $model->setBody($model->getShippedBody())
            ->setUpdatedBy($this->securityContext->getAdminUser()?->getId())
            ->save();

        return self::fromModel($model);
    }

    /**
     * Writes the shipped files into the table: new rows for new files, a new
     * `shipped_body` where a file changed, and the body too unless the site
     * rewrote it.
     */
    public function sow(): void
    {
        $existing = [];

        foreach (CmsSkillQuery::create()->find() as $model) {
            $existing[(string) $model->getCode()] = $model;
        }

        foreach ($this->files->all() as $shipped) {
            $model = $existing[$shipped->code] ?? null;

            if (null === $model) {
                (new CmsSkill())
                    ->setCode($shipped->code)
                    ->setName($shipped->name)
                    ->setDescription($shipped->description)
                    ->setBody($shipped->body)
                    ->setShippedBody($shipped->body)
                    ->setOrigin($shipped->origin)
                    ->setPosition($shipped->position)
                    ->setEnabled(1)
                    ->setAlways($shipped->always ? 1 : 0)
                    ->save();

                continue;
            }

            if ($model->getShippedBody() === $shipped->body && $model->getName() === $shipped->name && $model->getDescription() === $shipped->description) {
                continue;
            }

            $untouched = $model->getBody() === $model->getShippedBody();

            $model->setName($shipped->name)
                ->setDescription($shipped->description)
                ->setShippedBody($shipped->body)
                ->setAlways($shipped->always ? 1 : 0);

            if ($untouched) {
                $model->setBody($shipped->body);
            }

            $model->save();
        }
    }

    private static function fromModel(CmsSkill $model): Skill
    {
        return new Skill(
            code: (string) $model->getCode(),
            name: (string) $model->getName(),
            description: (string) $model->getDescription(),
            body: (string) $model->getBody(),
            origin: (string) $model->getOrigin(),
            position: (int) $model->getPosition(),
            enabled: 1 === $model->getEnabled(),
            always: 1 === $model->getAlways(),
            shippedBody: $model->getShippedBody(),
        );
    }
}
