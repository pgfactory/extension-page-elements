<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\PageFactory;
use PgFactory\MarkdownPlus\Permission;
use function PgFactory\PageFactory\isBot;
use function PgFactory\PageFactory\preparePath;
use function PgFactory\PageFactory\loadFile;
use function PgFactory\PageFactory\writeFileLocking;

class CountVisits
{
    public static $inx = 1;

    /**
     * Macro rendering method
     * @param array $args
     * @param string $argStr
     * @return string
     */
    public function render($args): string
    {
        $prefix = $args['prefix'].' ';
        $suffix = ' '.$args['suffix'];
        $show = $args['show'];
        if (is_string($show)) {
            $show = Permission::evaluate($show);
        }
        if (str_contains($prefix, '%since%')) {
            $t = strtotime(@file_get_contents(VISITS_SINCE_FILE));
            $since = date('d-m-Y', $t);
            $prefix = str_replace('%since%', $since, $prefix);
        }
        if (str_contains($suffix, '%since%')) {
            $t = strtotime(@file_get_contents(VISITS_SINCE_FILE));
            $since = date('d-m-Y', $t);
            $suffix = str_replace('%since%', $since, $suffix);
        }
        $visits = $this->countVisits($args);
        if ($show) {
            return "$prefix$visits$suffix";
        }
        return '';
    } // render


    /**
     * @return int|mixed|string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function countVisits(array $args): int
    {
        $ipsToIgnore = PageFactory::$config['visitCounterIgnoreIPs']??'';
        $file = VISITS_FILE;
        if (isBot()) {
            $file = VISITS_BOTS_FILE;
        }
        if (($args['pageId']??false)) {
            $pgId = $args['pageId'];
            $doCount = false;
        } else {
            $dontCount = ($args['dontCount']??false);
            if (is_string($dontCount)) {
                $dontCount = Permission::evaluate($dontCount);
            }
            $doCount = !$dontCount;
            $pgId = page()->id();
        }
        $clientIp = $this->getClientIP(true);
        if (!file_exists($file)) {
            preparePath($file);
            file_put_contents($file, "$pgId: 0");
            file_put_contents(VISITS_SINCE_FILE, date('Y-m-d H:i:s'));
            $count = 0;
        } else {
            $counters = loadFile($file);
            if (isset($counters[$pgId])) {
                $count = $counters[$pgId]++;
            } else {
                $count = $counters[$pgId] = 1;
            }
            if (!str_contains($ipsToIgnore, $clientIp) && $doCount) { // home
                writeFileLocking($file, $counters);
            }
        }
        return $count;
    } // countVisits


    /**
     * @param $normalize
     * @return array|false|string
     */
    private function getClientIP($normalize = false)
    {
        $ip = getenv('HTTP_CLIENT_IP')?:
            getenv('HTTP_X_FORWARDED_FOR')?:
                getenv('HTTP_X_FORWARDED')?:
                    getenv('HTTP_FORWARDED_FOR')?:
                        getenv('HTTP_FORWARDED')?:
                            getenv('REMOTE_ADDR');

        if ($normalize) {
            $elems = explode('.', $ip);
            foreach ($elems as $i => $e) {
                $elems[$i] = str_pad($e, 3, "0", STR_PAD_LEFT);
            }
            $ip = implode('.', $elems);
        }
        return $ip;
    } // getClientIP

} // CountVisits
