<?php

namespace XD\EventTickets\Forms\GridField;

use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

/**
 * Class GuestListGridFieldConfig
 *
 * @author Bram de Leeuw
 * @package XD\EventTickets
 */
class ReservationGridFieldConfig extends GridFieldConfig_RecordEditor
{
    public function __construct($itemsPerPage = null)
    {
        parent::__construct($itemsPerPage);
        $this->removeComponentsByType(new GridFieldAddNewButton('buttons-before-left'));
    }
}
