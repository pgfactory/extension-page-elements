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
        $postfix = ' '.$args['postfix'];
        $show = $args['show'];
        if (is_string($show)) {
            $show = Permission::evaluate($show);
        }
        $visits = $this->countVisits();
        if ($show) {
            return "$prefix$visits$postfix";
        }
        return '';
    } // render


    /**
     * @return int|mixed|string
     * @throws \Kirby\Exception\InvalidArgumentException
     */
    private function countVisits()
    {
        $ipsToIgnore = PageFactory::$config['visitCounterIgnoreIPs']??'';
        $file = VISITS_FILE;
        if (isBot()) {
            $file = VISITS_BOTS_FILE;
        }
        $pgId = page()->id();
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
            if (!str_contains($ipsToIgnore, $clientIp)) { // home
                writeFileLocking($file, $counters);
            }
        }
        //mylog("IP: $clientIp");
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
