<?php

namespace PgFactory\PageFactoryElements;

use DOMDocument;
use DOMXPath;
use Gt\CssXPath\Translator;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\TransVars;
use PHPMailer\PHPMailer\PHPMailer;
use function PgFactory\PageFactory\mylog;

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
        $plaintext = $markdown;
        if (preg_match('/\n==== [A-Z]+\n/s', $markdown)) {
            list($plaintext, $markdown, $css) = self::parseSections($markdown);
        }
        $html = '';
        $images = [];

        if ($markdown) {
            $html = TransVars::compile($markdown);
            $html = TransVars::resolveShortFormVariables($html);
            $html = preg_replace('/<!--.*?-->/', '', $html); // remove comments

            if ($forPreview) {
                if (str_contains($html, 'cid:')) {
                    if (preg_match_all('/<img .*? data-srcpath=\'(.*?)\' \s* data-url=\'(.*?)\' .*? src=["\']cid:([^"\']+)/xms', $html, $m)) {
                        foreach ($m[3] as $i => $cid) {
                            $path = $m[1][$i];
                            $url = $m[2][$i];
                            $html = str_replace(" data-srcpath='$path'", '', $html);
                            $html = str_replace(" data-url='$url'", '', $html);
                            $html = str_replace("src='cid:$cid'", "src='$url'", $html);
                        }
                    }
                }

                $html = "<div class='outer-wrapper'>\n$html</div>";

            } else {
                if (str_contains($html, 'cid:')) {
                    if (preg_match_all('/<img .*? data-srcpath=\'(.*?)\' \s* data-url=\'(.*?)\' .*? src=["\']cid:([^"\']+)/xms', $html, $m)) {
                        foreach ($m[3] as $i => $cid) {
                            $path = $m[1][$i];
                            $url = $m[2][$i];
                            $images[$cid]['path'] = $path;
                            $images[$cid]['url']  = $url;
                            $html = str_replace(" data-srcpath='$path'", '', $html);
                            $html = str_replace(" data-url='$url'", '', $html);
                        }
                    }
                }
                $html = "<html><body class='outer-wrapper'>\n$html</body></html>";
            }
            $css = $css ?: PFY_HTMLMAIL_DEFAULT_STYLES;
            $html = self::applyInlineStyles($html, $css);
        }
        $plaintext = TransVars::resolveShortFormVariables($plaintext);
        $plaintext = self::stripFormatting($plaintext);

        return [$html, $plaintext, $images];
    } // compileForMail


    /**
     * @param $html
     * @param $css
     * @return array|string|string[]|null
     */
    public static function applyInlineStyles($html, $css) {
        // Parse CSS rules into an associative array
        $cssRules = self::parseCss($css);

        // Load the HTML content into a DOMDocument object
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true); // Suppress warnings due to malformed HTML
        $dom->loadHTML($html);
        libxml_clear_errors();

        // Loop through each CSS rule and apply it to the relevant elements
        foreach ($cssRules as $selector => $declarations) {
            // Find elements matching the CSS selector
            $xpath = new DOMXPath($dom);
            $selector = new Translator($selector);
            $nodes = $xpath->query($selector);

            if (is_object($nodes)) {
                foreach ($nodes as $node) {
                    // Combine the existing inline styles with new ones
                    $currentStyle = $node->getAttribute('style');
                    $newStyle = $currentStyle . '; ' . $declarations;
                    $newStyle = ltrim($newStyle, '; ');
                    $node->setAttribute('style', $newStyle);
                }
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
        return $innerHTML;
    } // applyInlineStyles


    /**
     * Removes all markdown formatting, HTML and PHP tags from a string
     *
     * @param string $mdStr The string to be shortened
     * @return string The shortened string
     */
    private static function stripFormatting(string $mdStr): string
    {
        $mdStr = preg_replace([
            '/{{.*?}}/s',
            '/{:.*?}/s',
            '/\*\*(.*?)\*\*/',    // Bold **text**
            '/\*(.*?)\*/',        // Italic *text* or _text_
            '/_(.*?)_/',        // Italic _text_
            '/#(.*?)\n/',        // Headers # Header
            '/~~(.*?)~~/',    // Strikethrough ~~text~~
            '/`(.*?)`/',        // Inline code `code`
            '/>\s(.*?)\n/',       // Blockquotes > text
        ], "$1", $mdStr);
        $mdStr = preg_replace([
            '/\[(.*?)]\((.*?)\)/', // Links [text](url)
            '/!\[(.*?)]\((.*?)\)/', // Images ![alt](url)
        ], "$1 ($2)", $mdStr);

        $mdStr = str_replace("\r\n", "\n", $mdStr);
        $mdStr = strip_tags($mdStr);
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
        preg_match_all('/([^{]+)\{([^}]+)}/', $css, $matches, PREG_SET_ORDER);

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
        $parts = preg_split('/\n====[ \t]*(.+)\n/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

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
    public static function sendMail(array $props): void
    {
        $mailer             = new phpmailer();
        $mailer->From       = $props['from'];
        $mailer->FromName   = $props['fromName'];
        $mailer->Subject    = $subject = $props['subject'];
        $mailer->AddAddress($props['to']);

        // body:
        if ($html = $props['body']['html']??'') {
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
            }
            if (PageFactory::$dev) {
                $logText .= "\n--- HTML ---\n$html\n--- END HTML ---";
            }
        } else {
            if ($props['body']['text']??false) {
                $logText = $props['body']['text'];
            } else {
                $logText = "-- no text --";
            }
        }

        // attachments:
        if ($props['attachments']) {
            foreach ($props['attachments'] as $rec) {
                if (is_array($rec)) {
                    $mailer->AddEmbeddedImage($rec['file'], $rec['cid'], basename($rec['file']));
                } else {
                    $mailer->AddAttachment($rec);
                }
            }
        }

        $mailer->Send();

        $logText = "email sent to {$props['to']}\n{$subject}\n$logText";
        mylog($logText, 'mail-log.txt');
    } // sendMail


} // HtmlMail