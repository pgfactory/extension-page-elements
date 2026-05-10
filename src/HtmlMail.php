<?php

namespace PgFactory\PageFactoryElements;

use DOMDocument;
use DOMXPath;
use Gt\CssXPath\Translator;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\TransVars;
use PHPMailer\PHPMailer\PHPMailer;
use function PgFactory\PageFactory\mylog;
use function PgFactory\PageFactory\unshieldStr;

const PFY_HTMLMAIL_DEFAULT_STYLES = <<<EOT
.pfy-htmlmail-outer-wrapper { font-family: Arial, sans-serif; }
.pfy-htmlmail-inner-wrapper { font-size:12pt; }
.pfy-htmlmail-inner-wrapper td { font-size:12pt;padding: 0.2em 1.5em 0.2em 0; }

EOT;

class HtmlMail
{
    private const NBSP_PLACEHOLDER = '##NBSP##';
    private const CID_IMAGE_PATTERN = '/<img .*? data-srcpath=[\'"](.*?)[\'"] \s* data-url=[\'"](.*?)[\'"] .*? src=["\']cid:([^"\']+)/xms';

    /**
     * @param string $markdown
     * @param string $css
     * @param array $data
     * @param string $plaintext
     * @param bool $forPreview
     * @return array
     * @throws \Exception
     */
    public static function compileForMail(string $markdown, string $css = '', array $data = [], string $plaintext = '', bool $forPreview = false, bool $prettyWrapper = true): array
    {
        $css = $css ?: PFY_HTMLMAIL_DEFAULT_STYLES;

        if (!$plaintext) {
            $plaintext = self::stripMarkdownPlus($markdown);
            $plaintext = self::cleanupPlaintext($plaintext);
        }
        if (preg_match('/\n==== [A-Z]+\n/s', "\n$markdown")) {
            list($plaintext1, $markdown, $css1) = self::parseSections($markdown);
            $css .= $css1;
            if ($plaintext1) {
                $plaintext = $plaintext1;
            }
        }
        $images = [];

        $lang = PageFactory::$lang;
        $markdown = self::handleLinks($markdown);
        $templateOptions = [
            'element' => $markdown,
            'markdown' => true,
        ];

        $html = TemplateCompiler::compile($data, $templateOptions);
        $html = preg_replace('/<!--.*?-->/', '', $html); // remove comments

        if ($prettyWrapper) {
            $html = self::fixMdpLayoutTables($html);

            $html = <<<EOT
<div lang='$lang'>
    <table class='pfy-htmlmail-outer-wrapper' role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f4f4f4;">
        <tr>
            <td align="center">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="background-color:#ffffff;width:96%;max-width:800px;margin-top:15px;margin-bottom:15px;padding:15px">
                    <tr>
                        <td>
                        <div class='pfy-htmlmail-inner-wrapper' style="width:100%;max-width:800px;">
$html
                        </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
EOT;
        }

        $html = self::applyInlineStyles($html, $css);

        if ($forPreview) {
            $html = self::handleImagesForPreview($html);

        } else {
            list($html, $images) = self::handleImagesForMail($html);
            if ($prettyWrapper) {
                $html = self::wrapForMail($html, $lang);
            }
        }

        return [$html, $plaintext, $images];
    } // compileForMail


    /**
     * @param string $plaintext
     * @return string
     */
    public static function cleanupPlaintext(string $plaintext): string
    {
        $plaintext = unshieldStr($plaintext);
        $plaintext = strip_tags($plaintext);
        $plaintext = str_replace(['&nbsp;', '&nbg;', "\r\n", "\n\r", '\\', '→', '⇒'], [' ', ' ', "\n", "\n", '', '->', '=>'], $plaintext);
        $plaintext = preg_replace("/\n{2,}/", "\n\n", $plaintext);
        $plaintext = preg_replace(['/\{\{\s*(img|vgap).*?}}/'], [''], $plaintext);
        if (preg_match_all('/\{\{\s* link\( (.*?) \).*?}}/x', $plaintext, $m)) {
            foreach ($m[1] as $i => $linkText) {
                $a = explode(',', $linkText);
                $linkText = $a[0];
                $text = $a[1] ?? '';
                // FIX: removed empty alternative '|' that matched at every position
                $linkText = preg_replace('/(mailto:|tel:|sms:)/', '', $linkText);
                $linkText = preg_replace(['/^["\']/', '/["\']$/'], '', $linkText);
                if ($text) {
                    $linkText = "$text ($linkText)";
                }
                $plaintext = str_replace($m[0][$i], $linkText, $plaintext);
            }
        }
        $plaintext = trim($plaintext);
        return $plaintext;
    } // cleanupPlaintext


    /**
     * @param string $html
     * @return string
     */
    private static function handleImagesForPreview(string $html): string
    {
        if (!str_contains($html, 'cid:')) {
            return $html;
        }
        if (preg_match_all(self::CID_IMAGE_PATTERN, $html, $m)) {
            foreach ($m[3] as $i => $cid) {
                $path = $m[1][$i];
                $url = $m[2][$i];
                $html = preg_replace("| data-srcpath=['\"]{$path}['\"]|", '', $html);
                $html = preg_replace("| data-url=['\"]{$url}['\"]|", '', $html);
                $html = preg_replace("|src=['\"]cid:{$cid}['\"]|", "src='$url'", $html);
            }
        }
        return $html;
    } // handleImagesForPreview


    /**
     * @param string $html
     * @return array
     */
    private static function handleImagesForMail(string $html): array
    {
        $images = [];
        if (!str_contains($html, 'cid:')) {
            return [$html, $images];
        }
        if (preg_match_all(self::CID_IMAGE_PATTERN, $html, $m)) {
            foreach ($m[3] as $i => $cid) {
                $path = $m[1][$i];
                $url = $m[2][$i];
                $images[$cid]['path'] = $path;
                $images[$cid]['url']  = $url;
                $html = preg_replace("| data-srcpath=['\"]{$path}['\"]|", '', $html);
                $html = preg_replace("| data-url=['\"]{$url}['\"]|", '', $html);
            }
        }
        return [$html, $images];
    } // handleImagesForMail


    /**
     * @param string $html
     * @param string $lang
     * @return string
     */
    private static function wrapForMail(string $html, string $lang): string
    {
        $html = <<<EOT
<!DOCTYPE html>
<html lang='$lang'>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4;">
$html
</body>
</html>

EOT;
        return $html;
    } // wrapForMail


    /**
     * @param string $html
     * @param string $css
     * @return string
     */
    public static function applyInlineStyles(string $html, string $css): string
    {
        // Parse CSS rules into an associative array
        $cssRules = self::parseCss($css);

        list($dom, $xpath) = self::loadHtmlDom($html);

        // Loop through each CSS rule and apply it to the relevant elements
        foreach ($cssRules as $selector => $declarations) {
            // Find elements matching the CSS selector
            $selector = new Translator($selector);
            $nodes = $xpath->query($selector);

            if (is_object($nodes)) {
                foreach ($nodes as $node) {
                    // Combine the existing inline styles with new ones
                    $currentStyle = $node->getAttribute('style');
                    if (preg_match('/(?<!-)align:\s*(\w+);?/', $declarations, $m)) {
                        $declarations = str_replace($m[0], '', $declarations);
                        $node->setAttribute('align', $m[1]);
                    }
                    if ($declarations) {
                        $newStyle = $currentStyle . '; ' . $declarations;
                        $newStyle = ltrim($newStyle, '; ');
                        $node->setAttribute('style', $newStyle);
                    }
                }
            }
        }

        return self::extractBodyHtml($dom);
    } // applyInlineStyles


    /**
     * @param string $html
     * @return string
     */
    public static function fixMdpLayoutTables(string $html): string
    {
        if (!$html) {
            return '';
        }

        list($dom, $xpath) = self::loadHtmlDom($html);

        $theads = $xpath->query('//thead');

        foreach ($theads as $thead) {
            $ths = $xpath->query('.//th', $thead);
            $allEmpty = true;

            foreach ($ths as $th) {
                $content = trim($th->textContent);
                if ($content !== '') {
                    $allEmpty = false;
                    break;
                }
            }

            if ($allEmpty && $ths->length > 0) {
                $table = $thead->parentNode;
                if ($table->nodeName === 'table') {
                    $table->setAttribute('role', 'presentation');
                    $table->setAttribute('border', '0');
                    $table->setAttribute('cellpadding', '0');
                    $table->setAttribute('cellspacing', '0');
                }
                $table->setAttribute('class', 'pfy-layout-table');
                $thead->parentNode->removeChild($thead);
            }
        }

        return self::extractBodyHtml($dom);
    } // fixMdpLayoutTables


    /**
     * @param string $markdown
     * @return string
     */
    private static function handleLinks(string $markdown): string
    {
        if (preg_match_all('/\{\{ \s* link\( (.*?) \)/x', $markdown, $matches)) {
            // FIX: replace full match to avoid duplicating icon:false on repeated link texts
            foreach ($matches[0] as $i => $fullMatch) {
                $linkText = $matches[1][$i];
                if (!str_contains($linkText, 'icon:')) {
                    $replacement = str_replace($linkText, $linkText . ', icon:false', $fullMatch);
                    $markdown = str_replace($fullMatch, $replacement, $markdown);
                }
            }
        }
        return $markdown;
    } // handleLinks


    /**
     * Removes all markdown formatting, HTML and PHP tags from a string
     *
     * @param string $mdStr The string to be shortened
     * @return string The shortened string
     */
    private static function stripMarkdownPlus(string $mdStr): string
    {
        $mdStr = preg_replace([
            '/\*\*(.*?)\*\*/',    // Bold **text**
            '/\*(.*?)\*/',        // Italic *text* or _text_
            '/_(.*?)_/',        // Italic _text_
            '/#+(.*?)\n/',        // Headers # Header
            '/~~(.*?)~~/',    // Strikethrough ~~text~~
            '/`(.*?)`/',        // Inline code `code`
        ], "$1", $mdStr);
        $mdStr = preg_replace([
            '/\[(.*?)]\((.*?)\)/', // Links [text](url)
            '/!\[(.*?)]\((.*?)\)/', // Images ![alt](url)
        ], "$1 ($2)", $mdStr);

        $mdStr = str_replace(["\r\n","\n\r"], "\n", $mdStr);
        $mdStr = preg_replace(["/\|---.*/","/\|===.*/",'/\|\s/', '/\\\ /'], '', $mdStr);
        $mdStr = preg_replace(["/\n@@@.*?\n/ms", '/\{:.*?}/'], ["\n", ''], $mdStr);
        $mdStr = str_replace(' BR ', "\n", $mdStr);
        $mdStr = strip_tags($mdStr);
        $mdStr = preg_replace("/\n{2,}/ms", "\n\n", $mdStr);
        $mdStr = str_replace(['&nbsp;'], [' '], $mdStr);
        $mdStr = trim($mdStr);
        return $mdStr;
    } // stripMarkdownPlus


    /**
     * @param string $css
     * @return array
     */
    private static function parseCss(string $css): array
    {
        $rules = [];
        $css = trim($css);

        // Split CSS into individual rules by curly braces
        preg_match_all('/([^{]+) \{ ([^}]+) }/x', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            // Clean up the selector and declarations
            $selector = trim($match[1]);
            $declarations = trim($match[2]);

            // Clean up and format the declarations
            $declarations = preg_replace('/\s*;\s*/', ';', $declarations); // Remove unnecessary spaces
            $rules[$selector] = $declarations;
        }

        return $rules;
    } // parseCss


    /**
     * @param string $text
     * @return array  [plaintext, html/markdown, css]
     */
    public static function parseSections(string $text): array
    {
        $result = [];

        // Normalize line endings
        $text = str_replace("\r\n", "\n", trim($text));

        // Split on lines that start with "==== "
        $parts = preg_split('/\n====[ \t]*(.+)\n/', "\n$text", -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($parts) === 1) {
            // FIX: return indexed array consistent with the caller's list() destructuring
            return [trim($parts[0]), '', ''];
        }

        // First element is the plaintext
        $result['plaintext'] = trim(array_shift($parts));

        // Process remaining pairs: title, content, title, content, ...
        for ($i = 0; $i < count($parts); $i += 2) {
            $title = trim($parts[$i]);
            $content = isset($parts[$i + 1]) ? trim($parts[$i + 1]) : '';
            $result[$title] = $content;
        }
        // FIX: added parentheses to fix ?? / . operator precedence
        return [
            $result['plaintext'] ?? '',
            ($result['HTML'] ?? '') . ($result['MARKDOWN'] ?? ''),
            $result['CSS'] ?? '',
        ];
    } // parseSections


    /**
     * @param array $props
     * @return void
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public static function sendMail(array $props): void
    {
        $props += [
            'to' => '',
            'cc' => '',
            'from' => TransVars::getVariable('webmaster_email'),
            'fromName' => false,
            'subject' => '',
            'body' => '',
        ];
        $logComment = $props['logComment'] ?? '';

        $subject = $props['subject'];
        $body = $props['body'];

        if (str_contains($subject, '{{')) {
            $subject = TransVars::translate($subject);
        }

        $images = [];
        if (is_string($body)) {
            if (preg_match('/\n==== [A-Z]+\n/s', "\n$body")) {
                if (str_contains($body, '{{')) {
                    $body = TransVars::translate($body);
                }
                // FIX: static call instead of unnecessary instantiation
                list($html, $text, $images) = self::compileForMail($body);
                $props['body'] = [];
                $props['body']['text'] = $text;
                $props['body']['html'] = $html;
            }
        } else {
            if ($body['html'] ?? false) {
                if ($body['text'] && str_contains($body['text'], '{{')) {
                    $props['body']['text'] = TransVars::translate($body['text']);
                }
                if ($body['html'] && str_contains($body['html'], '{{')) {
                    $props['body']['html'] = TransVars::translate($body['html']);
                }
            }
        }

        // FIX: class name case to match the import (PHPMailer 6.x)
        $mailer             = new PHPMailer();
        $mailer->From       = $props['from'];
        $mailer->FromName   = $props['fromName'];
        $mailer->CharSet    = 'UTF-8';
        $mailer->Subject    = $subject;

        // FIX: avoid variable shadowing; trim addresses
        $to                 = $props['to'];
        if (is_string($to) && str_contains($to, ',')) {
            foreach (explode(',', $to) as $addr) {
                $mailer->addAddress(trim($addr));
            }
        } elseif (is_array($to)) {
            foreach ($to as $addr) {
                $mailer->addAddress($addr);
            }
            $props['to'] = implode(', ', $to);
        } else {
            $mailer->addAddress($to);
        }

        // body:
        if (is_string($props['body'] ?? false)) {
            $logText = $props['body'];
            $mailer->Body = $logText;

        } elseif ($html = $props['body']['html'] ?? '') {
            $mailer->isHTML(true);
            $html = str_replace(["&lt;", "&gt;"], ["<", ">"], htmlentities($html, ENT_NOQUOTES, 'UTF-8', false));
            $mailer->Body = $html;
            if ($props['body']['text'] ?? false) {
                $logText = $props['body']['text'];
            } else {
                $logText = strip_tags($html);
                $logText = preg_replace("/(\n\s*)+/ms", "\\n", $logText);
                $logText = preg_replace("/\s+/", " ", $logText);
                $logText = str_replace("\\n", "\n", $logText);
                $logText = html_entity_decode($logText);
            }
            $mailer->AltBody = $logText;

            if (PageFactory::$dev) {
                $logText .= "\n--- HTML ---\n$html\n--- END HTML ---";
            }
        } else {
            if ($props['body']['text'] ?? false) {
                $logText = $props['body']['text'];
            } else {
                $logText = "-- no text --";
            }
            $mailer->AltBody = $logText;
        }

        // FIX: embed images returned by compileForMail (were previously discarded)
        foreach ($images as $cid => $imgData) {
            $mailer->addEmbeddedImage($imgData['path'], $cid, basename($imgData['path']));
        }

        // attachments:
        if ($props['attachments'] ?? false) {
            foreach ($props['attachments'] as $rec) {
                if (is_array($rec)) {
                    $mailer->addEmbeddedImage($rec['file'], $rec['cid'], basename($rec['file']));
                } else {
                    $mailer->addAttachment($rec);
                }
            }
        }

        $mailer->send();

        $subjectLabel =  TransVars::getVariable('pfy-htmlmail-preview-subject');

        if ($logComment) {
            $logText = "$logComment {$props['to']}:\n$subjectLabel: {$subject}\n$logText";
        } else {
            $logText = "email sent to {$props['to']}:\n$subjectLabel: {$subject}\n$logText";
        }
        mylog($logText, 'mail-log.txt');
    } // sendMail


    /**
     * Loads HTML into a DOMDocument with proper UTF-8 handling.
     *
     * @param string $html
     * @return array  [DOMDocument, DOMXPath]
     */
    private static function loadHtmlDom(string $html): array
    {
        $html = str_replace('&nbsp;', self::NBSP_PLACEHOLDER, $html);

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // Prepend XML encoding declaration for proper UTF-8 handling
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Remove the XML processing instruction node
        foreach ($dom->childNodes as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $dom->removeChild($node);
                break;
            }
        }

        return [$dom, new DOMXPath($dom)];
    } // loadHtmlDom


    /**
     * Extracts the inner HTML of the body element from a DOMDocument.
     *
     * @param DOMDocument $dom
     * @return string
     */
    private static function extractBodyHtml(DOMDocument $dom): string
    {
        $innerHTML = '';
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            foreach ($body->childNodes as $child) {
                $innerHTML .= $dom->saveHTML($child);
            }
        } else {
            $innerHTML = $dom->saveHTML();
        }
        $innerHTML = str_replace(self::NBSP_PLACEHOLDER, '&nbsp;', $innerHTML);
        return $innerHTML;
    } // extractBodyHtml

} // HtmlMail
