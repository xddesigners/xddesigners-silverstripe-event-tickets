# Event tickets for SilverStripe
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/TheBnl/event-tickets/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/TheBnl/event-tickets/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/TheBnl/event-tickets/badges/build.png?b=master)](https://scrutinizer-ci.com/g/TheBnl/event-tickets/build-status/master)
[![Latest Stable Version](https://poser.pugx.org/bramdeleeuw/silverstripe-event-tickets/version)](https://packagist.org/packages/bramdeleeuw/silverstripe-event-tickets)


Add a ticket office to your silverstripe site. Payments handled trough the SilverStripe Omnipay module.

## Installation

Install the module trough composer

```bash
composer require bramdeleeuw/silverstripe-event-tickets
```

Add the nessesary extensions to the DataObject you want to sell tickets on. This can be an Event, for example with an [events module](https://github.com/xddesigners/silverstripe-events). Or you can add the tickets to a special tickets Page, when, for example, you are selling tickets for an festival where one ticket grants access to multiple events.

```yml
# the object that sells tickets
XD\Events\Model\EventPage:
 extensions:
   - XD\EventTickets\Extensions\TicketExtension
# the ticket controller
XD\Events\Model\EventPageController:
 extensions:
   - XD\EventTickets\Extensions\TicketControllerExtension
   - XD\EventTickets\Checkout\Steps\RegisterStep
   - XD\EventTickets\Checkout\Steps\SummaryStep
   - XD\EventTickets\Checkout\Steps\SuccessStep
```

On the extended Object you need to expose a couple of methods that we use to add data to the ticket.

```php
public function getEventTitle()
{
    return $this->owner->Title;
}

public function getEventStartDate()
{
    return $this->owner->dbObject('StartDate');
}

public function getEventAddress()
{
    $this->owner->getFullAddress();
}
```

### Maintainers

[Remy Vaartjes](remy@xd.nl) - XD designers

Thanks to [Bram de Leeuw](https://broarm.nl/) for working on the development of this module.
