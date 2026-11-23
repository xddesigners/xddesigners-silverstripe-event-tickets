<?php

namespace XD\EventTickets\Forms;

use SilverStripe\Omnipay\GatewayInfo;
use XD\EventTickets\Checkout\PaymentProcessor;
use XD\EventTickets\Fields\PaymentGatewayField;
use XD\EventTickets\Fields\SummaryField;
use XD\EventTickets\Fields\TermsAndConditionsField;
use XD\EventTickets\Model\Reservation;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Omnipay\Exception\InvalidConfigurationException;
use SilverStripe\Omnipay\Exception\InvalidStateException;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\ORM\DataObject;

/**
 * Class SummaryForm
 *
 * @package XD\EventTickets
 */
class SummaryForm extends FormStep
{
    /**
     * Attendee fields that may be changed via Summary[<attendeeID>][<field>] on the summary step.
     * @config
     */
    private static $summary_editable_fields = [];

    public $summaryStep;

    public function __construct($controller, $name, Reservation $reservation)
    {

        $fields = FieldList::create(
            SummaryField::create('Summary', '', $this->reservation = $reservation, true),
            TextareaField::create('Comments', _t(__CLASS__ . '.Comments', 'Comments')),
            PaymentGatewayField::create()
        );

        $paymentLabel = $reservation->Total == 0
            ? _t(__CLASS__ . '.ReserveTicket', 'Reserve tickets')
            : _t(__CLASS__ . '.ContinueToPayment', 'Continue to payment');

        $actions = FieldList::create(
            FormAction::create('makePayment', $paymentLabel)
        );

        $required = [];
        if (($termsPage = SiteConfig::current_site_config()->TermsPage()) && $termsPage->exists()) {
            $fields->push(TermsAndConditionsField::create('AgreeToTermsAndConditions', $termsPage));
            $required[] = 'AgreeToTermsAndConditions';
        }

        $validator = RequiredFieldsValidator::create($required);

        parent::__construct($controller, $name, $fields, $actions, $validator);

        // check if there is an error message and show it
        if ($error = $this->getPaymentErrorMessage()) {
            $this->setMessage($error, 'error');
        }

        // Update the summary form with extra fields
        $this->extend('updateSummaryForm');
    }

    /**
     * Handle the ticket form registration
     *
     * @param array $data
     * @param SummaryForm $form
     * @return HTTPResponse
     * @throws ValidationException
     * @throws InvalidConfigurationException
     * @throws InvalidStateException
     */
    public function makePayment(array $data, SummaryForm $form)
    {

        // If the summary is changed and email receivers are set
        if (isset($data['Summary']) && is_array($data['Summary'])) {
            foreach ($data['Summary'] as $attendeeID => $fields) {
                $attendee = $form->reservation->Attendees()->byID((int) $attendeeID);
                if (!$attendee || !is_array($fields)) {
                    continue;
                }
                // Only explicitly allowed fields may be edited on the summary step (default: none). Arbitrary
                // setField() allowed changing MemberID, TicketStatus, CheckedIn etc.
                $editable = (array) self::config()->get('summary_editable_fields');
                foreach ($fields as $field => $value) {
                    if (is_string($field) && in_array($field, $editable, true) && is_scalar($value)) {
                        $attendee->setField($field, $value);
                    }
                }
                $attendee->write();
            }
        }

        $form->saveInto($form->reservation);
        // If comments are added
        //if (isset($data['Comments'])) {
        //    $form->reservation->Comments = $data['Comments'];
        //}

        // Hook trough where optional extra field data can be saved on the reservation
        $this->extend('updateReservationBeforePayment', $form->reservation, $data, $form);

        // A paid or cancelled reservation can't be paid again (used to push PAID back to PENDING)
        if (!in_array($form->reservation->Status, [Reservation::STATUS_CART, Reservation::STATUS_PENDING, Reservation::STATUS_PAYMENT_FAILED], true)) {
            return $this->getController()->redirect($this->getController()->Link('success'));
        }

        // The gateway is decided on the server: a paid order must use a real (non-Manual) supported gateway; the
        // posted value is only honoured when it is one of those. Free orders are completed manually.
        $posted = isset($data['Gateway']) && is_string($data['Gateway']) ? $data['Gateway'] : '';
        $paidGateways = array_values(array_diff(array_keys(GatewayInfo::getSupportedGateways(false)), ['Manual']));
        if ($form->reservation->Total > 0) {
            $gateway = in_array($posted, $paidGateways, true) ? $posted : ($paidGateways[0] ?? null);
            if (!$gateway) {
                $form->sessionMessage(_t(__CLASS__ . '.NoGateway', 'Betalen is op dit moment niet mogelijk.'), 'bad');
                return $this->getController()->redirectBack();
            }
        } else {
            $gateway = 'Manual';
        }

        $form->reservation->changeState('PENDING');
        $form->reservation->Gateway = $gateway;
        $form->reservation->write();

        $paymentProcessor = new PaymentProcessor($this->reservation);
        $paymentProcessor
            ->createPayment($gateway)
            ->setSuccessUrl($this->getController()->Link($this->nextStep))
            ->setFailureUrl($this->getController()->Link($this->summaryStep))
            ->write();

        $paymentProcessor->setGateWayData(array('transactionId' => $this->reservation->ReservationCode));

        $response = $paymentProcessor->createServiceFactory();
        $this->extend('beforeNextStep', $data, $form, $response);
        return $response->redirectOrRespond();
    }


    /**
     * Get the last error message from the payment attempts
     *
     * @return bool|string
     */
    public function getPaymentErrorMessage()
    {
        /** @var Payment $lastPayment */
        // Get the most recent payment
        if (!$lastPayment = $this->reservation->Payments()->sort('ID', 'DESC')->first()) {
            return false;
        }

        // Find the gateway error
        $lastErrorMessage = null;
        $errorMessages = $lastPayment->Messages()->exclude('Message', '')->sort('ID', 'DESC');
        foreach ($errorMessages as $errorMessage) {
            if ($this->isPaymentErrorMessage($errorMessage)) {
                $lastErrorMessage = $errorMessage;
                break;
            }
        }

        // If no error is found return
        if (!$lastErrorMessage) {
            return false;
        }

        return _t("{$lastErrorMessage->Gateway}.{$lastErrorMessage->Code}", $lastErrorMessage->Message);
    }

    /**
     * Whether a payment message reports an error. silverstripe-omnipay 6.1+ stores every message as one
     * PaymentMessage class with its kind in `Type` (e.g. `PurchaseError`, `CompletePurchaseError`); older versions
     * used a subclass per kind, with GatewayErrorMessage for errors.
     */
    protected function isPaymentErrorMessage(DataObject $message): bool
    {
        $type = (string) $message->getField('Type');
        if ($type !== '') {
            return str_ends_with($type, 'Error');
        }

        return is_a($message, 'SilverStripe\\Omnipay\\Model\\Message\\GatewayErrorMessage');
    }

    public function setSummaryStep($step)
    {
        $this->summaryStep = $step;
    }
}
