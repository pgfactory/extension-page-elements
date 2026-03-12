<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\PageFactory;
use PgFactory\MarkdownPlus\Permission;
use function PgFactory\PageFactory\isBot;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\writeFileLocking;

class CountVisits
{
    /**
     * Macro rendering method
     * @param array $args
     * @return string
     */
    public function render(array $args): string
    {
        list($since, $visits) = $this->countVisits($args);

        $prefix = $args['prefix'] !== '' ? $args['prefix'] . ' ' : '';
        $suffix = $args['suffix'] !== '' ? ' ' . $args['suffix'] : '';
        $show = $args['show'];
        if (is_string($show)) {
            $show = Permission::evaluate($show);
        }
        if (str_contains($prefix, '%since%')) {
            $prefix = str_replace('%since%', $since, $prefix);
        }
        if (str_contains($suffix, '%since%')) {
            $suffix = str_replace('%since%', $since, $suffix);
        }
        if ($show) {
            return "$prefix$visits$suffix";
        }
        return '';
    } // render


    /**
     * @param array $args
     * @return array
     * @throws \Exception
     */
    private function countVisits(array $args): array
    {
        $ipsToIgnore = PageFactory::$config['visitCounterIgnoreIPs'] ?? '';
        $file = isBot() ? VISITS_BOTS_FILE : VISITS_FILE;

        if (!empty($args['pageId'])) {
            $pgId = $args['pageId'];
            $doCount = false;
        } else {
            $dontCount = $args['dontCount'] ?? false;
            if (is_string($dontCount)) {
                $dontCount = Permission::evaluate($dontCount);
            }
            $doCount = !$dontCount;
            $pgId = page()->id();
        }

        // handle case of page invoked with GET parameters (only first one used):
        if (!empty($_GET)) {
            $key = array_key_first($_GET);
            $val = $_GET[$key];
            $pgId .= "&$key=$val";
        }
        list($since, $counters) = $this->getSinceTime($file, $pgId);

        $clientIp = $this->getClientIP(true);

        $count = 1;
        $p = strpos($counters, "\n$pgId: ");
        if ($p !== false) {
            $p += strlen($pgId) + 3;
            $p2 = strpos($counters, "\n", $p);
            $s1 = substr($counters, 0, $p);
            $count = intval(substr($counters, $p, $p2 - $p)) + 1;
            $s2 = substr($counters, $p2);
            $counters = "$s1$count$s2";
        } else {
            $counters .= "$pgId: 1\n";
        }
        if (!str_contains($ipsToIgnore, $clientIp) && $doCount) {
            writeFileLocking($file, $counters);
        }
        return [$since, $count];
    } // countVisits


    /**
     * @param bool $normalize
     * @return string
     */
    private function getClientIP(bool $normalize = false): string
    {
        $ip = getenv('HTTP_CLIENT_IP') ?:
            getenv('HTTP_X_FORWARDED_FOR') ?:
                getenv('HTTP_X_FORWARDED') ?:
                    getenv('HTTP_FORWARDED_FOR') ?:
                        getenv('HTTP_FORWARDED') ?:
                            getenv('REMOTE_ADDR');

        if ($normalize && $ip) {
            $elems = explode('.', $ip);
            foreach ($elems as $i => $e) {
                $elems[$i] = str_pad($e, 3, "0", STR_PAD_LEFT);
            }
            $ip = implode('.', $elems);
        }
        return $ip ?: '';
    } // getClientIP


    /**
     * @param string $file
     * @param string $pgId
     * @return array
     * @throws \Exception
     */
    private function getSinceTime(string $file, string $pgId): array
    {
        if (!file_exists($file)) {
            preparePath($file);
            $content = 'since: ' . date('Y-m-d H:i:s') . "\n\n$pgId: 0\n";
            writeFileLocking($file, $content);
        }
        $content = file_get_contents($file);
        if (preg_match("|^since: (.*)|", $content, $m)) {
            $t = strtotime($m[1]);
        } else {
            $content = 'since: ' . date('Y-m-d H:i:s') . "\n\n$pgId: 0\n";
            writeFileLocking($file, $content);
            $t = filemtime($file);
        }
        $since = date('d-m-Y', $t);
        return [$since, $content];
    } // getSinceTime

} // CountVisits
