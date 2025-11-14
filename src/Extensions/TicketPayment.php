<?php

namespace XD\EventTickets\Extensions;

use XD\EventTickets\Model\Reservation;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\ValidationException;

/**
 * Class TicketPayment
 *
 * @author Bram de Leeuw
 * @property TicketPayment|Payment $owner
 *
 * @property int ReservationID
 */
class TicketPayment extends Extension
{
    private static $has_one = array(
        'Reservation' => Reservation::class,
    );

    /**
     * Fix issue manual gateway doesn't call onCaptured hook
     *
     * @param ServiceResponse $response
     * @throws ValidationException
     */
    public function onAuthorized(ServiceResponse $response)
    {
        if ($response->getPayment()->Gateway === 'Manual') {
            if (($reservation = Reservation::get()->byID($this->owner->ReservationID)) && $reservation->exists()) {
                /** @var Reservation $reservation */
                $reservation->complete();
            }
        }
    }

    /**
     * Complete the order on a successful transaction
     *
     * @param ServiceResponse $response
     * @throws ValidationException
     */
    public function onCaptured(ServiceResponse $response)
    {
        /** @var Reservation $reservation */
        if (($reservation = Reservation::get()->byID($this->owner->ReservationID)) && $reservation->exists()) {
            $reservation->complete();
        }
    }
}
