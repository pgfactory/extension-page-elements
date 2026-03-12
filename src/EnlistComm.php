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
    } // setDb


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
     * @param array $deletedRec
     * @return void
     * @throws \Exception
     */
    public static function sendNotification(string|bool $to, array $dataRec, string $mode, string $widgetKey, array $deletedRec = []): void
    {
        $widgetDescr = self::$db->getWidgetDescr($widgetKey);
        $title = $widgetDescr['title'] ?: '';
        $to = self::resolveTo($to);

        $nameActivated = $dataRec['Name'];
        $nameDeleted = $deletedRec['Name'] ?? '';
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

        $subject = self::overrideSubject($subject);
        $replace = self::buildReplacements($title, $widgetKey ? "[$widgetKey] $title" : $title, $dataRec);
        [$subject, $body] = self::applyReplacements($subject, $body, $replace);

        Utils::sendMail([
            'to' => $to,
            'fromName' => self::$emailFromName,
            'subject' => $subject,
            'body' => $body,
        ]);
    } // sendNotification


    /**
     * @param string|bool $to
     * @param string $widgetKey
     * @param string $title
     * @param string $names
     * @return void
     * @throws \Exception
     */
    public static function sendNotificationOfListCollapse(string|bool $to, string $widgetKey, string $title, string $names): void
    {
        $title = str_replace("\n", ' ', $title);
        $to = self::resolveTo($to);

        $subject = TransVars::resolveVariables('{{ pfy-enlist-collapse-notification-subject }}');
        $subject = self::overrideSubject($subject);
        $body = TransVars::resolveVariables('{{ pfy-enlist-collapse-notification-message }}');
        $body = str_replace('%activated%', $names, $body);

        $replace = self::buildReplacements($title, $widgetKey ? "[$widgetKey] $title" : $title);
        [$subject, $body] = self::applyReplacements($subject, $body, $replace);

        Utils::sendMail([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ]);
    } // sendNotificationOfListCollapse


    /**
     * @param array $newDataRec
     * @param string $mode
     * @param string $widgetKey
     * @return void
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
        } else {
            TransVars::purgeTempVariables();
            return;
        }

        $subject = self::overrideSubject($subject);
        $replace = self::buildReplacements($title, $widgetKey, $newDataRec);
        [$subject, $body] = self::applyReplacements($subject, $body, $replace);

        if (str_contains($subject, '%')) {
            $subject = TransVars::resolveShortFormVariables($subject);
        }
        if (str_contains($body, '%')) {
            $body = TransVars::resolveShortFormVariables($body);
        }

        Utils::sendMail([
            'to' => $newDataRec['Email'],
            'subject' => $subject,
            'body' => $body,
            'attachment' => false,
        ]);
        TransVars::purgeTempVariables();
    } // sendConfirmation


    /**
     * @param array $rec
     * @param string $widgetKey
     * @return void
     * @throws \Exception
     */
    public static function sendActivatedConfirmation(array $rec, string $widgetKey): void
    {
        self::sendConfirmation($rec, 'activated', $widgetKey);
        mylog("Newly activated reserve slot notified: {$rec['Name']} {$rec['Email']}", 'enlist-log.txt');
    } // sendActivatedConfirmation


    /**
     * Resolves the recipient address: true maps to webmaster, dev mode can override.
     * @param string|bool $to
     * @return string
     */
    private static function resolveTo(string|bool $to): string
    {
        if ($to === true) {
            $to = PageFactory::$webmasterEmail;
        }
        if (PageFactory::$dev && ($mailOverride = kirby()->option('pgfactory.pagefactory.emailDevModeOverride'))) {
            $to = $mailOverride;
        }
        return $to;
    } // resolveTo


    /**
     * Builds the placeholder replacement array.
     * @param string $title
     * @param string $listId
     * @param array $dataRec
     * @return array
     */
    private static function buildReplacements(string $title, string $listId, array $dataRec = []): array
    {
        $replace = [
            '%title%'   => $title,
            '%listId%'  => $listId,
            '%host%'    => PFY_HOST_URL,
            '%hostUrl%' => PFY_HOST_URL,
            '%page%'    => self::pageLink(),
            '%pageUrl%' => self::pageLink(),
        ];
        if (!empty($dataRec['Name'])) {
            $replace['%name%'] = $dataRec['Name'];
        }
        if (!empty($dataRec['Email'])) {
            $replace['%email%'] = $dataRec['Email'];
        }
        return $replace;
    } // buildReplacements


    /**
     * Applies placeholder replacements and resolves TransVars.
     * @param string $subject
     * @param string $body
     * @param array $replace
     * @return array [subject, body]
     */
    private static function applyReplacements(string $subject, string $body, array $replace): array
    {
        $keys = array_keys($replace);
        $values = array_values($replace);
        $subject = str_replace($keys, $values, TransVars::resolveVariables($subject));
        $body = str_replace($keys, $values, TransVars::resolveVariables($body));
        return [$subject, $body];
    } // applyReplacements


    /**
     * Returns a generic subject override if configured, otherwise the given subject.
     * @param string $subject
     * @return string
     */
    private static function overrideSubject(string $subject): string
    {
        if ($genericSubject = TransVars::getVariable('pfy-enlist-subject')) {
            return $genericSubject;
        }
        return $subject;
    } // overrideSubject


    /**
     * @return string
     */
    private static function pageLink(): string
    {
        return page()->url();
    } // pageLink
} // EnlistComm
