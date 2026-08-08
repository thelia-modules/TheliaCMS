<?php

namespace TheliaCMS\Model;

use TheliaCMS\Model\Base\CmsPageContent as BaseCmsPageContent;
use TheliaCMS\Storage\EncodesSupplementaryCharacters;

/**
 * Skeleton subclass for representing a row from the 'cms_page_content' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CmsPageContent extends BaseCmsPageContent
{
    use EncodesSupplementaryCharacters;

    /**
     * @return list<string>
     */
    protected function stylesheetColumns(): array
    {
        return ['DraftCss', 'PublishedCss'];
    }

}
