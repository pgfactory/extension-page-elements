<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

function swissmap($options = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config =  [
        'options' => [
            'lat' 	=> ["[float] Specifies latitude of center of map", ''],
            'location' 	=> ["Specifies what the map is to center on: either an address (e.g. 'bahnhofstr. 1, zürich' or ".
                "coordinates (e.g. '47.36751, 8.53988'), see https://map.search.ch/api/help#geocoding", null],
            'metersPerPixel' 	=> ["[512 .. 0.125] Defines the zoom level as 'meters per pixel'", null],
            'zoom' 	=> ["Synonyme for 'metersPerPixel'", null],
            'id' 	=> ["[string] Defines the ID to be applied to the map container", null],
            'minHeight' 	=> ["[string] ", '200px'],
            'mapType' 	=> ["[street|satellite] Specifies in what way the map shall be displayed initially", 'street'],
            'controls' 	=> ["[zoom,type,ruler,all] Specifies which controls are active", 'all'],
            'poigroups' 	=> ["Specifies which points-of-interests are displayed, see https://map.search.ch/api/classref#poigroups", null],
            'customPOIs' 	=> ["[comma seperated list on locations|'file:'] Defines points-of-interest to be displayed. ".
                "A single POI may be supplied as a comma-separated-list; multiple POIs via a .csv file. ".
                "Structure: 'location,title,description,icon'", null],
            'customPOIIcon' 	=> ["Defines the default icon to represent custom locations.", null],
            'drawing' 	=> ["Let's you add an overlay containing a drawing that has been defined beforehand. ".
                "The drawing is identified by an ID that you get from https://map.search.ch", null],
            'marker' 			=> ["[true,false] Specifies whether a marker is visible at the center of the map", true],
            'gestureHandling' 	=> ["[cooperative|greedy|auto] Spedifies how scroll-events are being treated, ".
                "see https://map.search.ch/api/classref#gestureHandling'", null],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a map using https://search.ch/map/

(available for Switzerland only)

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $options))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $html = $sourceCode;
    }

    // assemble output:
    $mapSearch = new MapSearch();
    $html .= $mapSearch->render($options);

    return $html;
}




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