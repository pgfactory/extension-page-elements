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

const PFY_HTMLMAIL_DEFAULT_STYLES = '.outer-wrapper { font-family: Arial, sans-serif; }';
class HtmlMail
{
    /**
     * @param string $markdown
     * @param string $css
     * @param bool $forPreview
     * @return array
     * @throws \Exception
     */
    public static function compileForMail(string $markdown, string $css = '', bool $forPreview = false): array
    {
        $css = $css ?: PFY_HTMLMAIL_DEFAULT_STYLES;
        
        $plaintext = $markdown;
        if (preg_match('/\n==== [A-Z]+\n/s', "\n$markdown")) {
            list($plaintext, $markdown, $css) = self::parseSections($markdown);
        }
        $images = [];

        $lang = PageFactory::$lang;
        $markdown = self::handleLinks($markdown);
        $html = TransVars::compile($markdown);
        $html = TransVars::resolveShortFormVariables($html);
        $html = preg_replace('/<!--.*?-->/', '', $html); // remove comments

        $html = self::fixMdpLayoutTables($html);

        $html = <<<EOT
<div lang='$lang'>
    <table class='outer-wrapper' role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="background-color: #ffffff;">
                    <tr>
                        <td style="padding: 40px 30px;">
                        <div class='inner-wrapper'>
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

        $html = self::applyInlineStyles($html, $css);

        if ($forPreview) {
            $html = self::handleImagesForPreview($html);

        } else {
            list($html, $images) = self::handleImagesForMail($html);
            $html = self::wrapForMail($html, $lang);
        }

        $plaintext = unshieldStr(TransVars::translate($plaintext));
        $plaintext = self::stripFormatting($plaintext);

        return [$html, $plaintext, $images];
    } // compileForMail


    private static function handleImagesForPreview(string $html): string
    {
        if (str_contains($html, 'cid:')) {
            if (preg_match_all('/<img .*? data-srcpath=[\'"](.*?)[\'"] \s* data-url=[\'"](.*?)[\'"] .*? src=["\']cid:([^"\']+)/xms', $html, $m)) {
                foreach ($m[3] as $i => $cid) {
                    $path = $m[1][$i];
                    $url = $m[2][$i];
                    $html = preg_replace("| data-srcpath=['\"]{$path}['\"]|", '', $html);
                    $html = preg_replace("| data-url=['\"]{$url}['\"]|", '', $html);
                    $html = preg_replace("|src=['\"]cid:{$cid}['\"]|", "src='$url'", $html);
                }
            }
        }
        return $html;
    } // handleImagesForPreview


    private static function handleImagesForMail(string $html): array
    {
        $images = [];
        if (str_contains($html, 'cid:')) {
            if (preg_match_all('/<img .*? data-srcpath=[\'"](.*?)[\'"] \s* data-url=[\'"](.*?)[\'"] .*? src=["\']cid:([^"\']+)/xms', $html, $m)) {
                foreach ($m[3] as $i => $cid) {
                    $path = $m[1][$i];
                    $url = $m[2][$i];
                    $images[$cid]['path'] = $path;
                    $images[$cid]['url']  = $url;
                    $html = preg_replace("| data-srcpath=['\"]{$path}['\"]|", '', $html);
                    $html = preg_replace("| data-url=['\"]{$url}['\"]|", '', $html);
                }
            }
        }
        return [$html, $images];
    } // handleImagesForMail


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
     * @param $html
     * @param $css
     * @return array|string|string[]|null
     */
    public static function applyInlineStyles($html, $css) {
        // Parse CSS rules into an associative array
        $cssRules = self::parseCss($css);

        $html = str_replace('&nbsp;', '##NBSP##', $html); // workaround for &nbsp;

        // Load the HTML content into a DOMDocument object
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true); // Suppress warnings due to malformed HTML
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

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

        $innerHTML = '';
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            foreach ($body->childNodes as $child) {
                $innerHTML .= $dom->saveHTML($child);
            }
        } else {
            // No body tag found, return entire HTML
            $innerHTML = $dom->saveHTML();
        }
        $innerHTML = mb_convert_encoding($innerHTML, 'ISO-8859-1', 'UTF-8');
        $innerHTML = str_replace('##NBSP##', '&nbsp;', $innerHTML);
        return $innerHTML;
    } // applyInlineStyles

    
    public static function fixMdpLayoutTables($html) {
        $html = str_replace('&nbsp;', '##NBSP##', $html); // workaround for &nbsp;

        // Load the HTML content into a DOMDocument object
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true); // Suppress warnings due to malformed HTML
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

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

        $body = $dom->getElementsByTagName('body')->item(0);
        $innerHTML = '';
        if ($body) {
            foreach ($body->childNodes as $child) {
                $innerHTML .= $dom->saveHTML($child);
            }
        }

        $innerHTML = mb_convert_encoding($innerHTML, 'ISO-8859-1', 'UTF-8');
        $innerHTML = str_replace('##NBSP##', '&nbsp;', $innerHTML);
        return $innerHTML;
    } // fixMdpLayoutTables


    private static function handleLinks(string $markdown): string
    {
        if (preg_match_all('/\{\{ \s* link\( (.*?) \)/x', $markdown, $matches)) {
            foreach ($matches[1] as $i => $linkText) {
                $linkText1 = $linkText;
                if (!str_contains($linkText, 'icon:')) {
                    $linkText1 .= ', icon:false';
                }
                $markdown = str_replace($linkText, $linkText1, $markdown);
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
    private static function stripFormatting(string $mdStr): string
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

        $mdStr = str_replace(["\r\n","\n\r",'<br>'], "\n", $mdStr);
        $mdStr = preg_replace(["/\|---.*/","/\|===.*/",'/\|\s?/', '/\\\ /'], '', $mdStr);
        $mdStr = preg_replace(["/\n@@@.*?\n/ms", '/\{:.*?}/'], ["\n", ''], $mdStr);
        $mdStr = str_replace(' BR ', "\n", $mdStr);
        $mdStr = strip_tags($mdStr);
        $mdStr = preg_replace("/\n{2,}/ms", "\n\n", $mdStr);
        $mdStr = str_replace(['&nbsp;'], [' '], $mdStr);
        $mdStr = trim($mdStr);
        return $mdStr;
    } // stripFormatting


    /**
     * @param $css
     * @return array
     */
    private static function parseCss($css) {
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
     * @return array
     */
    public static function parseSections(string $text): array
    {
        $result = [];

        // Normalize line endings
        $text = str_replace("\r\n", "\n", trim($text));

        // Split on lines that start with "==== "
        $parts = preg_split('/\n====[ \t]*(.+)\n/', "\n$text", -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($parts) === 1) {
            // No sections found, entire text is the plaintext
            $result['plaintext'] = trim($parts[0]);
            return $result;
        }

        // First element is the plaintext
        $result['plaintext'] = trim(array_shift($parts));

        // Process remaining pairs: title, content, title, content, ...
        for ($i = 0; $i < count($parts); $i += 2) {
            $title = trim($parts[$i]);
            $content = isset($parts[$i + 1]) ? trim($parts[$i + 1]) : '';
            $result[$title] = $content;
        }
        return [$result['plaintext']??'', $result['HTML']??''.$result['MARKDOWN']??'', $result['CSS'] ?? ''];
    } // parseSections


    /**
     * @param array $props
     * @return void
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public static function sendMail(array $props, $logComment = ''): void
    {
        $mailer             = new phpmailer();
        $mailer->From       = $props['from'];
        $mailer->FromName   = $props['fromName'];
        $mailer->CharSet    = "UTF-8";
        $subject    = $props['subject'];
        $mailer->Subject    = $subject;

        $subject            = $props['subject'];
        $to                 = $props['to'];
        if (is_string($to) && str_contains($to, ',')) {
            foreach (explode(',', $to) as $to) {
                $mailer->AddAddress($to);
            }
        } elseif (is_array($to)) {
            foreach ($to as $to1) {
                $mailer->AddAddress($to1);
            }
            $props['to'] = implode(', ', $to);
        } else {
            $mailer->AddAddress($to);
        }

        // body:
        if (is_string($props['body']??false)) {
            $logText = $props['body'];
            $mailer->Body = $logText;

        } elseif ($html = $props['body']['html']??'') {
            $mailer->IsHTML(true);
            $html = str_replace(["&lt;", "&gt;"], ["<", ">"], htmlentities($html, ENT_NOQUOTES, 'UTF-8', FALSE));
            $mailer->Body = $html;
            if ($props['body']['text']??false) {
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
            if ($props['body']['text']??false) {
                $logText = $props['body']['text'];
            } else {
                $logText = "-- no text --";
            }
            $mailer->AltBody = $logText;
        }

        // attachments:
        if ($props['attachments']??false) {
            foreach ($props['attachments'] as $rec) {
                if (is_array($rec)) {
                    $mailer->AddEmbeddedImage($rec['file'], $rec['cid'], basename($rec['file']));
                } else {
                    $mailer->AddAttachment($rec);
                }
            }
        }

        $mailer->Send();

        $subjectLabel =  TransVars::getVariable('pfy-htmlmail-preview-subject');

        if ($logComment) {
            $logText = "$logComment {$props['to']}:\n$subjectLabel: {$subject}\n$logText";
        } else {
            $logText = "email sent to {$props['to']}:\n$subjectLabel: {$subject}\n$logText";
        }
        mylog($logText, 'mail-log.txt');
    } // sendMail


} // HtmlMail