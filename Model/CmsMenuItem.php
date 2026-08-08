<?php

namespace TheliaCMS\Model;

use TheliaCMS\Model\Base\CmsMenuItem as BaseCmsMenuItem;
use TheliaCMS\Storage\EncodesSupplementaryCharacters;

/**
 * Skeleton subclass for representing a row from the 'cms_menu_item' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CmsMenuItem extends BaseCmsMenuItem
{
    use EncodesSupplementaryCharacters;

}
