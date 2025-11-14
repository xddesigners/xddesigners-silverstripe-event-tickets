<?php

namespace XD\EventTickets\Fields;

use SilverStripe\Core\Validation\ValidationResult;
use XD\EventTickets\Model\Buyable;
use XD\EventTickets\Model\Ticket;
use Composer\Installers\PPIInstaller;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormField;

use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Class TicketsField
 *
 * @package XD\EventTickets
 */
class TicketsField extends FormField
{

    protected $tickets;

    //protected $template = 'TicketsField';

    public function __construct($name, $title, DataList $tickets)
    {
        $this->tickets = $tickets;
        parent::__construct($name, $title);
    }

    /**
     * Set the ticket list
     *
     * @param DataList $tickets
     */
    public function setTickets(DataList $tickets)
    {
        $this->tickets = $tickets;
    }

    /**
     * Get the ticket list
     *
     * @return DataList
     */
    public function getTickets()
    {
        return $this->tickets;
    }

    /**
     * Get a list of editable tickets
     * These have an numeric input field
     *
     * @return ArrayList
     */
    private function getEditableTickets()
    {
        $tickets = ArrayList::create();
        foreach ($this->getTickets() as $ticket) {
            /** @var Ticket $ticket */
            $fieldName = $this->name . "[{$ticket->ID}][Amount]";
            $range = range($ticket->OrderMin, $ticket->OrderMax);

            $ticket->AmountField = DropdownField::create($fieldName, 'Amount', array_combine($range, $range))
                ->setHasEmptyDefault(true)
                ->setEmptyString(_t('TicketsField.EMPTY', 'Tickets'));

            // Set the first to hold the minimum
            if ($this->getTickets()->count() === 1) {
                $ticket->AmountField->setValue($ticket->OrderMin);
            }

            // $availability = $ticket->TicketPage()->getAvailability();
            $availability = $ticket->getAvailability();
            if ($availability < $ticket->OrderMax) {
                $disabled = range($availability + 1, $ticket->OrderMax);
                $ticket->AmountField->setDisabledItems(array_combine($disabled, $disabled));
            }

            if (!$ticket->getAvailable()) {
                $ticket->AmountField->setDisabled(true);
            }

            $this->extend('updateTicket', $ticket);
            $tickets->push($ticket);
        }
        return $tickets;
    }

    /**
     * Get the field customized with tickets and reservation
     *
     * @param array $properties
     *
     * @return DBHTMLText|string
     */
    public function Field($properties = [])
    {
        $context = $this;
        $properties['Tickets'] = $this->getEditableTickets();

        if (count($properties)) {
            $context = $context->customise($properties);
        }

        $this->extend('onBeforeRender', $context);
        $result = $context->renderWith($this->getTemplates());

        if (is_string($result)) {
            $result = trim($result);
        } else {
            if ($result instanceof DBField) {
                $result->setValue(trim($result->getValue()));
            }
        }

        return $result;
    }


    public function validate(): ValidationResult
    {
        $result = ValidationResult::create();

        // Throw an error when there are no tickets selected
        if (empty($this->value)) {
            $result->addFieldError($this->name, _t(
                'TicketsField.VALIDATION_EMPTY',
                'Select at least one ticket'
            ), 'validation');
            return $result;
        }

        // Get the availability
        $available = $this->getForm()->getController()->getAvailability();
        // get the sum of selected tickets
        $ticketCount = array_sum(array_map(function ($item) {
            return $item['Amount'];
        }, $this->value));

        // If the sum of tickets is 0 trow the same error as empty
        if ($ticketCount === 0) {
            $result->addFieldError($this->name, _t(
                'TicketsField.VALIDATION_EMPTY',
                'Select at least one ticket'
            ), 'validation');

            return $result;
        }

        // Check if the ticket is still available
        foreach ($this->value as $id => $amountArray) {
            if (!isset($amountArray['Amount']) || !($amountArray['Amount']) > 0) {
                continue;
            }

            $amount = $amountArray['Amount'];
            $buyable = Buyable::get_by_id($id);
            if ($buyable->getAvailability() < $amount) {
                $result->addFieldError($this->name, _t(
                    'TicketsField.VALIDATION_TO_MUCH',
                    'There are {ticketCount} tickets left',
                    null,
                    ['ticketCount' => $available]
                ), 'validation');

                return $result;
            }
        }

        return $result;
    }
}
