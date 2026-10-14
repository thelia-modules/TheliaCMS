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

namespace TheliaCMS\Block\Item;

use Thelia\Model\ModuleQuery;

/**
 * Which item types this site can give a block to: the core ones always, the
 * store only while the Dealer module is active. The answer is read once per
 * request: the hook asks on every catalogue screen.
 */
final class ItemTypes
{
    /** @var array<string, bool> */
    private array $availability = [];

    public function isAvailable(ItemType $type): bool
    {
        $module = $type->module();

        if (null === $module) {
            return true;
        }

        return $this->availability[$module] ??= 1 === (int) ModuleQuery::create()->findOneByCode($module)?->getActivate();
    }

    /**
     * @return list<ItemType>
     */
    public function available(): array
    {
        return array_values(array_filter(ItemType::cases(), $this->isAvailable(...)));
    }
}
