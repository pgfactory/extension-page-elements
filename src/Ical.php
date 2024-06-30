<?php

namespace PgFactory\PageFactoryElements;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use DateTime;

class Ical
{
    public static function render(array $iCalOptions): string
    {
        $event = Event::create($iCalOptions['title']);
        $event->startsAt(new DateTime($iCalOptions['start']));
        $event->endsAt(new DateTime($iCalOptions['end']));

        if ($description = ($iCalOptions['description']??false)) {
            $event->description($description);
        }
        if ($organiser = ($iCalOptions['organiser']??false)) {
            $event->organizer($organiser);
        }
        if ($location = ($iCalOptions['location']??false)) {
            $event->address($location);
        }
        if ($iCalOptions['fullDay']??false) {
            $event->fullDay();
        }
        if ($uniqueIdentifier = ($iCalOptions['uniqueIdentifier']??false)) {
            $event->uniqueIdentifier($uniqueIdentifier);
        }
        if ($status = ($iCalOptions['status']??false)) {
            $event->status($status);
        }

        $cal = Calendar::create();
        $cal->event($event);
        return $cal->get();
    } // render

} // Ical