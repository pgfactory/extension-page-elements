<?php

namespace PgFactory\PageFactory;


class MapSearch
{
    private $inx;
    private $customPOIIcon;


    public function render(array $options): string
    {
        $this->inx = $options['inx'];

        $id = ($options['id']??false) ?: "pfy-swissmap-container-{$this->inx}";
        $location = ($options['location']??false) ?: '';
        $long = ($options['long']??false) ?: '';
        $lat = ($options['lat']??false) ?: '';

        if ($long && ($l = $this->getLocation($long))) {
            $location = $l;
        } elseif ($long && $lat) {
            $location = "[$long, $lat]";
        } else {
            $location = $this->getLocation($location);
        }

        if ($options['metersPerPixel']??false) {
            $options['zoom'] = $options['metersPerPixel'];
        }
        $zoom = ($options['zoom']??false) ?: 0;

        if (($options['mapType']??false) && (strpos(",aerial,street,satellite,", ",{$options['mapType']},") !== false)) {
            if ($options['mapType'] === 'satellite') {
                $options['mapType'] = 'aerial';
            }
            $mapType = "\ttype: '{$options['mapType']}',\n";

        } else {
            $mapType = '';
        }

        $from = ($options['from']??false) ?: '';
        $to = ($options['to']??false) ?: '';
        $route = '';
        if ($from && $to) {
            $from = $this->getLocation($from);
            $to = $this->getLocation($to);
            $route = <<<EOT
        from: $from,
        to: $to,

EOT;
        }

        $controls = ($options['controls']??false) ?: '';
        if ($controls) {
            $controls = "\tcontrols: '$controls',\n";
        }

        $poigroups = ($options['poigroups']??false) ?: '';
        if ($poigroups) {
            $poigroups = "\tpoigroups: '$poigroups',\n";
        }

        $this->customPOIIcon = ($options['customPOIIcon']??false) ?: '';
        $customPOIs = ($options['customPOIs']??false) ?: '';
        if ($customPOIs) {
            $customPOIs = $this->handleCustomPOIs($customPOIs);
        }

        $drawing = ($options['drawing']??false) ?: '';
        if ($drawing) {
            $drawing = "\tdrawing: '$drawing',\n";
        }

        $marker = isset($options['marker']) ? ($options['marker']?'true': 'false') : 'true';
        $marker = "marker: $marker,\n";

        $gestureHandling = ($options['gestureHandling']??false) ?: '';
        if ($gestureHandling) {
            $gestureHandling = "\tgestureHandling: '$gestureHandling',\n";
        }

        $out = "<div id='$id' class='pfy-swissmap-container'></div>\n";

        if ($this->inx === 1) {
            PageFactory::$pg->addAssets('https://search.ch/map/api/map.js');
        }

        $minHight = ($options['minHeight']??false) ?: '200px';
        $cssRules = "min-height: $minHight;";
        if (($options['height']??false)) {
            $cssRules .= "height: {$options['height']};";
        }
        PageFactory::$pg->addCss("#$id { $cssRules }");

        $map = "map{$this->inx}";

        $jq = <<<EOT

$map = new SearchChMap({
    container: '$id',
    center: $location,
    zoom: $zoom,
    $marker$mapType$route$controls$poigroups$drawing$gestureHandling
});$customPOIs


EOT;

        PageFactory::$pg->addJq($jq);

        return $out;
    } // render




    private function getLocation(string $str): string
    {
        // 'street number zip city'
        // '1.00, 2.00'
        // '[1.00, 2.00]'
        //
        if (preg_match('/^\s* \[? \s* (\d+\.?\d*) \s*,\s* (\d+\.?\d*) \s* \]? \s*$/x', $str, $m)) {
            $str = "[{$m[1]}, {$m[2]}]";
        } else {
            $str = "'$str'";
        }

        return $str;
    } // getLocation



    private function handleCustomPOIs(string $customPOIs): string
    {
        $jq = '';
        $map = "map{$this->inx}";

        if (preg_match('/^(\w+):(.*)/', $customPOIs, $m)) {
            if ($m[1] === 'file') {
                $file = resolvePath($m[2], true);
                if (file_exists($file)) {
                    $db = new DataSet($file);
                    $recs = $db->read();
                    foreach ($recs as $rec) {
                        $location = trim($this->getLocation($rec[0]),'"\'');
                        $title = trim($rec[1],'"\'');
                        $description = trim($rec[2],'"\'');
                        $poiIcon = isset($rec[3]) ? trim($rec[3],'"\''): $this->customPOIIcon;
                        $jq .= <<<EOT

    $map.addPOI(new SearchChPOI({ 
        center:'$location', 
        title:'$title', 
        html:'$description', 
        icon:'$poiIcon' 
    }));

EOT;
                    }
                }
            }
        }
        return $jq;
    } // handleCustomPOIs

} // MapSearch