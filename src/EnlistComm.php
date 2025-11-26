<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\Utils;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\mylog;

class EnlistComm
{
    private static object $db;
    private static string $emailFromName = '';

    /**
     * @param object $db
     * @return void
     */
    public static function setDb(object $db): void
    {
        self::$db = $db;
    } // setEmailFromName


    /**
     * @param string $name
     * @return void
     */
    public static function setEmailFromName(string $name): void
    {
        self::$emailFromName = $name;
    } // setEmailFromName


    /**
     * @param string|bool $to
     * @param array $dataRec
     * @param string $mode
     * @param string $widgetKey
     * @param string $nameActivated
     * @return void
     * @throws \Exception
     */
    public static function sendNotification(string|bool $to, array $dataRec, string $mode, string $widgetKey, array $delectedRec = []): void
    {
        $widgetDescr = self::$db->getWidgetDescr($widgetKey);
        $title = $widgetDescr['title'] ?: '';
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
            '%listId%'  => $widgetKey ? "[$widgetKey] $title": $title,
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
        $props = [
            'to' => $to,
            'fromName' => self::$emailFromName,
            'subject' => $subject,
            'body' => $body,
        ];

        Utils::sendMail($props);
    } // sendNotification


    /**
     * @param string|bool $to
     * @param string $title
     * @param string $names
     * @return void
     * @throws \Exception
     */
    public static function sendNotificationOfListCollapse(string|bool $to, string $widgetKey, string $title, string $names): void
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
        $body = str_replace('%activated%', $names, $body);

        $replace = [
            '%title%'       => $title,
            '%listId%'  => $widgetKey ? "[$widgetKey] $title": $title,
            '%host%'        => PFY_HOST_URL,
            '%hostUrl%'     => PFY_HOST_URL,
            '%page%'        => self::pageLink(),
            '%pageUrl%'     => self::pageLink(),
        ];

        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($subject));
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            TransVars::resolveVariables($body));

        $props = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ];

        Utils::sendMail($props);
    } // sendNotification


    /**
     * @param array $newDataRec
     * @param string $title
     * @return bool
     */
    public static function sendConfirmation(array $newDataRec, string $mode, string $widgetKey): void
    {
        $widgetDescr = self::$db->getWidgetDescr($widgetKey);
        $title = $widgetDescr['title'] ?: $widgetKey;
        $newDataRec['title'] = $title;
        TransVars::setTempVariables($newDataRec);
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
            '%name%'        => $newDataRec['Name'],
            '%email%'       => $newDataRec['Email'],
            '%title%'       => $title,
            '%listId%'      => $widgetKey,
            '%host%'        => PFY_HOST_URL,
            '%hostUrl%'     => PFY_HOST_URL,
            '%page%'        => self::pageLink(),
            '%pageUrl%'     => self::pageLink(),
        ];
        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            $subject);
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            $body);

        if (str_contains($subject, '%')) {
            $subject = TransVars::resolveShortFormVariables($subject);
        }
        if (str_contains($body, '%')) {
            $body = TransVars::resolveShortFormVariables($body);
        }


        //ToDo: email with ics attachment
        $props = [
            'to' => $newDataRec['Email'],
            'subject' => $subject,
            'body' => $body,
            'attachment' => false,
        ];

        TransVars::purgeTempVariables();
        Utils::sendMail($props);
    } // sendConfirmation


    /**
     * @param array $rec
     * @param string $widgetKey
     * @return void
     * @throws \Exception
     */
    public static function sendActivatedConfirmation(array $rec, string $widgetKey): void
    {
        $widgetDescr = self::$db->getWidgetDescr($widgetKey);
        $title = $widgetDescr['title'] ?: $widgetKey;
        TransVars::setTempVariables($rec);
        $subject = TransVars::resolveVariables('{{ pfy-enlist-activated-visitor-confirmation-subject }}');
        // if generic subject is set, override default:
        if ($genericSubject = TransVars::getVariable('pfy-enlist-subject')) {
            $subject = $genericSubject;
        }
        $body = TransVars::resolveVariables('{{ pfy-enlist-activated-visitor-confirmation-message }}');

        $replace = [
            '%name%'        => $rec['Name'],
            '%email%'       => $rec['Email'],
            '%title%'       => $title,
            '%listId%'      => $widgetKey,
            '%host%'        => PFY_HOST_URL,
            '%hostUrl%'     => PFY_HOST_URL,
            '%page%'        => self::pageLink(),
            '%pageUrl%'     => self::pageLink(),
        ];
        $subject = str_replace(
            array_keys($replace),
            array_values($replace),
            $subject);
        $body = str_replace(
            array_keys($replace),
            array_values($replace),
            $body);

        if (str_contains($subject, '%')) {
            $subject = TransVars::resolveShortFormVariables($subject);
        }
        if (str_contains($body, '%')) {
            $body = TransVars::resolveShortFormVariables($body);
        }


        $props = [
            'to' => $rec['Email'],
            'subject' => $subject,
            'body' => $body,
        ];
        Utils::sendMail($props);
        mylog("Newly activated reserve slot notified: {$rec['Name']} {$rec['Email']}", 'enlist-log.txt');
        TransVars::purgeTempVariables();
    } // sendActivatedConfirmation


    /**
     * @return string
     */
    private static function pageLink(): string
    {
        return page()->url();
    } // pageLink
} // Enlist