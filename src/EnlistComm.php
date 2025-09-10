<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\Utils;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\mylog;

class EnlistComm
{
    /**
     * @param string|bool $to
     * @param array $newDataRec
     * @param string $mode
     * @param string $title
     * @param string $nameActivated
     * @return void
     * @throws \Exception
     */
    public static function notifyOwner(string|bool $to, array $newDataRec, string $mode, string $title, string $nameActivated = ''): void
    {
        $title = str_replace("\n", ' ', $title);
        if ($to === true) {
            $to = PageFactory::$webmasterEmail;
        }

        if ($mode === 'add') {
            $subject = '{{ pfy-enlist-add-notification-subject }}';
            $body = '{{ pfy-enlist-add-notification-body }}';
        } elseif ($mode === 'activated') {
            $subject = '{{ pfy-enlist-activated-notification-subject }}';
            $body = TransVars::getVariable('pfy-enlist-activated-notification-body');
            $body = str_replace('%activated%', $nameActivated, $body);
        } else {
            $subject = '{{ pfy-enlist-del-notification-subject }}';
            $body = '{{ pfy-enlist-del-notification-body }}';
        }
        $replace = [
            '%name%' => $newDataRec['Name'],
            '%email%' => $newDataRec['Email'],
            '%title%' => $title,
            '%host%' => PFY_HOST_URL,
            '%page%' => self::pageLink(),
        ];
        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($subject));
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($body));

        Utils::sendMail($to, $subject, $body );
    } // notifyOwner


    /**
     * @param array $newDataRec
     * @param string $title
     * @return bool
     */
    public static function sendConfirmation(array $newDataRec, string $title): void
    {
        $title = str_replace("\n", ' ', $title);
        $subject = TransVars::resolveVariables('{{ pfy-enlist-visitor-confirmation-subject }}');
        $body = TransVars::resolveVariables('{{ pfy-enlist-visitor-confirmation-body }}');
        $replace = [
            '%name%' => $newDataRec['Name'],
            '%email%' => $newDataRec['Email'],
            '%title%' => $title,
            '%host%' => PFY_HOST_URL,
            '%hostUrl%' => PFY_HOST_URL,
            '%page%' => self::pageLink(),
        ];
        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            $subject);
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            $body);
 //ToDo: email with ics attachment
        Utils::sendMail($newDataRec['Email'], $subject, $body );
    } // sendConfirmation


    /**
     * @param array $rec
     * @param string $title
     * @return void
     * @throws \Exception
     */
    public static function notifyActivatedReserve(array $rec, string $title): void
    {
        $title = str_replace("\n", ' ', $title);
        $subject = TransVars::resolveVariables('{{ pfy-enlist-notify-activated-reserve-subject }}');
        $body = TransVars::resolveVariables('{{ pfy-enlist-notify-activated-reserve-body }}');
        $replace = [
            '%name%' => $rec['Name'],
            '%email%' => $rec['Email'],
            '%title%' => $title,
            '%host%' => PFY_HOST_URL,
            '%page%' => self::pageLink(),
        ];
        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            $subject);
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            $body);

        Utils::sendMail($rec['Email'], $subject, $body );
        mylog("Newly activated reserve slot notified: {$rec['Name']} {$rec['Email']}", 'enlist-log.txt');
    } // notifyActivatedReserve


    /**
     * @return string
     */
    private static function pageLink(): string
    {
        return page()->url();
    } // pageLink
} // Enlist