<?php

namespace TheliaCMS\Model;

use TheliaCMS\Model\Base\CmsFormField as BaseCmsFormField;
use TheliaCMS\Storage\EncodesSupplementaryCharacters;

/**
 * Skeleton subclass for representing a row from the 'cms_form_field' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class CmsFormField extends BaseCmsFormField
{
    use EncodesSupplementaryCharacters;

}
