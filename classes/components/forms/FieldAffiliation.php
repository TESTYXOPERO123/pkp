<?php
/**
 * @file classes/components/form/FieldAffiliation.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldAffiliation
 *
 * @ingroup classes_controllers_form
 *
 * @brief A field for author affiliations.
 */

namespace PKP\components\forms;

class FieldAffiliation extends Field
{
    /** @copydoc Field::$component */
//    public $component = 'field-affiliation';
    public $component = 'field-text';

    /** @var string What should the <input type=""> be? */
    public $inputType = 'text';

    /** @var bool Whether the user should have to click a button to edit the field */
    public $optIntoEdit = false;

    /** @var string The label of the button added by self::$optIntoEdit */
    public $optIntoEditLabel = '';

    /** @var string Accepts: `small`, `normal` or `large` */
    public $size = 'normal';

    /** @var string A prefix to display before the input value */
    public $prefix = '';

    /**
     * @copydoc Field::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['inputType'] = $this->inputType;
        $config['optIntoEdit'] = $this->optIntoEdit;
        $config['optIntoEditLabel'] = $this->optIntoEditLabel;
        $config['size'] = $this->size;
        $config['prefix'] = $this->prefix;

        return $config;
    }
}
