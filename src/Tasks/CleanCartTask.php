<?php

namespace XD\EventTickets\Tasks;

use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\InputInterface;
use XD\EventTickets\Model\Reservation;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;

/**
 * Class CleanCartTask
 * Cleanup discarded tasks
 *
 * @package XD\EventTickets
 */
class CleanCartTask extends BuildTask
{
    protected string $title = 'Cleanup cart task';

    protected static string $description = 'Cleanup discarded ticket shop carts';

    private static $dependencies = [
        'Logger' => '%$' . LoggerInterface::class,
    ];

    public function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->run($input, $output);
        return 0;
    }


    public function run(InputInterface $input, PolyOutput $output): int
    {
        if (!Director::is_cli()) echo '<pre>';
        $this->logger->debug("Start cleaning");

        /** @var Reservation $reservation */
        foreach (Reservation::get()->filter(['Status' => Reservation::STATUS_CART]) as $reservation) {
            if ($reservation->isDiscarded()) {
                $this->logger->debug("Delete reservation #{$reservation->ID}");
                $reservation->delete();
            }
        }

        // Update status on stalled payments
        foreach (Reservation::get()->filter(['Status' => Reservation::STATUS_PENDING]) as $reservation) {
            if ($reservation->isStalledPayment()) {
                $this->logger->debug("Set reservation #{$reservation->ID} to payment failed ");
                $reservation->Status = Reservation::STATUS_PAYMENT_FAILED;
                $reservation->write();
            }
        }

        $this->logger->debug("Done cleaning");
        if (!Director::is_cli()) echo '</pre>';

        return 0;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }
}
