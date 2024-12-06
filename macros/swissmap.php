<?php
namespace PgFactory\PageFactory;

require_once __DIR__ . "/../src/MapSearch.php";

/*
 * PageFactory Macro (and Twig Function)
 */

return function ($options = '')
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
};




