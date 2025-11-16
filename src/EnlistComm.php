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
     * @param array $dataRec
     * @param string $mode
     * @param string $title
     * @param string $nameActivated
     * @return void
     * @throws \Exception
     */
    public static function notifyOwner(string|bool $to, array $dataRec, string $mode, string $title, array $delectedRec = []): void
    {
        $title = str_replace("\n", ' ', $title);
        if ($to === true) {
            $to = PageFactory::$webmasterEmail;
        }
        // dev mode -> override $to:
        if (PageFactory::$dev && ($mailOverride = kirby()->option('pgfactory.pagefactory.emailDevModeOverride'))) {
            $to = $mailOverride;
        }

        $nameActivated = $dataRec['Name'];
        $nameDeleted = $delectedRec['Name']??'';
        if ($mode === 'add') {
            $subject = '{{ pfy-enlist-add-notification-subject }}';
            $body = '{{ pfy-enlist-add-notification-message }}';

        } elseif ($mode === 'activated') {
            $subject = '{{ pfy-enlist-activated-notification-subject }}';
            $body = TransVars::getVariable('pfy-enlist-activated-notification-message');
            $body = str_replace('%activated%', $nameActivated, $body);

        } elseif ($mode === 'del&activated') {
            $subject = '{{ pfy-enlist-del-activated-notification-subject }}';
            $body = TransVars::getVariable('pfy-enlist-del-activated-notification-message');
            $body = str_replace(['%activated%', '%deleted%'], [$nameActivated, $nameDeleted], $body);

        } else {
            $subject = '{{ pfy-enlist-del-notification-subject }}';
            $body = '{{ pfy-enlist-del-notification-message }}';
        }
        // if generic subject is set, override default:
        if ($genericSubject = TransVars::getVariable('pfy-enlist-subject')) {
            $subject = $genericSubject;
        }
        $replace = [
            '%name%'    => $dataRec['Name'],
            '%email%'   => $dataRec['Email'],
            '%title%'   => $title,
            '%host%'    => PFY_HOST_URL,
            '%hostUrl%' => PFY_HOST_URL,
            '%page%'    => self::pageLink(),
            '%pageUrl%' => self::pageLink(),
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
     * @param string|bool $to
     * @param string $title
     * @param string $names
     * @return void
     * @throws \Exception
     */
    public static function notifyOwnerOfListCollapse(string|bool $to, string $title, string $names): void
    {
        $title = str_replace("\n", ' ', $title);
        if ($to === true) {
            $to = PageFactory::$webmasterEmail;
        }
        // dev mode -> override $to:
        if (PageFactory::$dev && ($mailOverride = kirby()->option('pgfactory.pagefactory.emailDevModeOverride'))) {
            $to = $mailOverride;
        }

        $subject = TransVars::resolveVariables('{{ pfy-enlist-collapse-notification-subject }}');
        // if generic subject is set, override default:
        if ($genericSubject = TransVars::getVariable('pfy-enlist-subject')) {
            $subject = $genericSubject;
        }
        $body = TransVars::resolveVariables('{{ pfy-enlist-collapse-notification-message }}');
        $body = str_replace('%deleted%', $names, $body);

        $replace = [
            '%title%'   => $title,
            '%host%'    => PFY_HOST_URL,
            '%hostUrl%' => PFY_HOST_URL,
            '%page%'    => self::pageLink(),
            '%pageUrl%' => self::pageLink(),
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
    public static function sendConfirmation(array $newDataRec, string $mode, string $title): void
    {
        $title = str_replace("\n", ' ', $title);
        if ($mode === 'add') {
            $subject = TransVars::resolveVariables('{{ pfy-enlist-add-visitor-confirmation-subject }}');
            $body = TransVars::resolveVariables('{{ pfy-enlist-add-visitor-confirmation-message }}');
            
        } elseif ($mode === 'del') {
            $subject = TransVars::resolveVariables('{{ pfy-enlist-del-visitor-confirmation-subject }}');
            $body = TransVars::resolveVariables('{{ pfy-enlist-del-visitor-confirmation-message }}');
            
        } elseif ($mode === 'activated') {
            $subject = TransVars::resolveVariables('{{ pfy-enlist-activated-visitor-confirmation-subject }}');
            $body = TransVars::resolveVariables('{{ pfy-enlist-activated-visitor-confirmation-message }}');
        }
        // if generic subject is set, override default:
        if ($genericSubject = TransVars::getVariable('pfy-enlist-subject')) {
            $subject = $genericSubject;
        }

        $replace = [
            '%name%'    => $newDataRec['Name'],
            '%email%'   => $newDataRec['Email'],
            '%title%'   => $title,
            '%host%'    => PFY_HOST_URL,
            '%hostUrl%' => PFY_HOST_URL,
            '%page%'    => self::pageLink(),
            '%pageUrl%' => self::pageLink(),
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
    public static function sendActivatedConfirmation(array $rec, string $title): void
    {
        $title = str_replace("\n", ' ', $title);
        $subject = TransVars::resolveVariables('{{ pfy-enlist-activated-visitor-confirmation-subject }}');
        // if generic subject is set, override default:
        if ($genericSubject = TransVars::getVariable('pfy-enlist-subject')) {
            $subject = $genericSubject;
        }
        $body = TransVars::resolveVariables('{{ pfy-enlist-activated-visitor-confirmation-message }}');
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
    } // sendActivatedConfirmation


    /**
     * @return string
     */
    private static function pageLink(): string
    {
        return page()->url();
    } // pageLink
} // Enlist