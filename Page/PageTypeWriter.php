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

namespace TheliaCMS\Page;

use Propel\Runtime\Connection\ConnectionInterface;
use TheliaCMS\Model\CmsPageTypeDefinition;
use TheliaCMS\Model\CmsPageTypeDefinitionQuery;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Security\CmsResources;

/**
 * Every change to the page types goes through here, with the rules that keep
 * a page from ending up with a type the site does not have.
 */
final readonly class PageTypeWriter
{
    public function __construct(
        private PageTypeRepository $types,
        private CmsActivityLog $activityLog,
    ) {
    }

    /**
     * Adds a type unless the site has it already.
     *
     * @throws \InvalidArgumentException when the code is not a valid one
     */
    public function add(string $code, ?ConnectionInterface $connection = null): void
    {
        if (!PageTypeCode::isValid($code)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid page type code.', $code));
        }

        $type = CmsPageTypeDefinitionQuery::create()->filterByCode($code)->findOneOrCreate($connection);

        if (!$type->isNew()) {
            return;
        }

        $type->save($connection);
        $this->activityLog->record('CREATE', (int) $type->getId(), \sprintf('Page type "%s" added', $code), CmsResources::SETTINGS);
    }

    /**
     * @throws \LogicException        for a type the site always keeps, or a code that is not one
     * @throws PageTypeInUseException when a page, binned or not, still has the type
     */
    public function delete(string $code): void
    {
        if (!PageTypeCode::isValid($code) || !PageTypeCode::isDeletable($code)) {
            throw new \LogicException(\sprintf('The page type "%s" cannot be deleted.', $code));
        }

        $pages = $this->types->countPagesOfType($code);

        if (0 < $pages) {
            throw new PageTypeInUseException($code, $pages);
        }

        $type = CmsPageTypeDefinitionQuery::create()->findOneByCode($code);

        if ($type instanceof CmsPageTypeDefinition) {
            $type->delete();
            $this->activityLog->record('DELETE', 0, \sprintf('Page type "%s" deleted', $code), CmsResources::SETTINGS);
        }
    }
}
