<?php

namespace XD\EventTickets\Forms\GridField;

use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Class TicketsGridFieldConfig
 *
 * @author Bram de Leeuw
 * @package XD\EventTickets
 */
class TicketsGridFieldConfig extends GridFieldConfig_RecordEditor
{
    public function __construct($itemsPerPage = null)
    {
        parent::__construct($itemsPerPage);
        $this->removeComponentsByType(new GridFieldAddNewButton());
        $this->addComponent(new GridFieldAddNewMultiClass());
        $this->addComponent(new GridFieldOrderableRows('Sort'));
        $this->extend('updateConfig');
    }
}
