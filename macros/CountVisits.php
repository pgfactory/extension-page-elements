<?php

/*
 * 
 */

namespace Usility\PageFactory;

// const MY_IPS            = 'xxx';
const MY_IPS            = '083.150.045.221';
const VISITS_FILE       = PFY_LOGS_PATH.'visits.yaml';
const VISITS_SINCE_FILE = PFY_LOGS_PATH.'visits-since.txt';
const VISITS_BOTS_FILE  = PFY_LOGS_PATH.'visits_bots.txt';


$macroConfig =  [
    'parameters' => [
        'show'      => ['[true|loggedin] Whether to show result or record it silently.', false],
        'prefix'    => ['What to put in front of result', ''],
    ],
    'summary' => <<<EOT
Counts visits from non-bots, also excludes visits from my home.
EOT,
    'mdCompile' => false,
    'assetsToLoad' => '',
];



class CountVisits extends Macros
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
        $prefix = $args['prefix'];
        $show = $args['show'];
        if ($show[0]??'' === 'l') {
            $show = isLoggedinOrLocalhost();
        }
        $visits = $this->countVisits();
        if ($show) {
            return "$prefix$visits";
        }
        // mylog("IP: $clientIp");
        return '';
    } // render


    private function countVisits()
    {
        $file = VISITS_FILE;
        if (isBot()) {
            $file = VISITS_BOTS_FILE;
        }
        $pgId = page()->id();
        if (!file_exists($file)) {
            file_put_contents($file, "$pgId: 0");
            file_put_contents(VISITS_SINCE_FILE, date('Y-m-d H:i:s'));
            $count = 0;
        } else {
            $clientIp = $this->getClientIP(true);
            $counters = loadFile($file);
            if (isset($counters[$pgId])) {
                $count = $counters[$pgId]++;
            } else {
                $count = $counters[$pgId] = 1;
            }
            if (!str_contains(MY_IPS, $clientIp)) { // home
                writeFileLocking($file, $counters);
            }
        }
        return $count;
    } // countVisits


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

}




// ==================================================================
$macroConfig['macroObj'] = new $thisMacroName($this->pfy);  // <- don't modify
return $macroConfig;
