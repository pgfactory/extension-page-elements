<?php

namespace PgFactory\PageFactory;

const MAP_SEARCH_ICON_PATH = 'https://search.ch/map/images/icons/21/';
const LOCAL_ICON_PATH = '~/assets/icons/';

class MapSearch
{
    private $inx;
    private $customPOIIcon;
    private string $localIconsPath;
    private string $localIconsUrl;

    public function __construct()
    {
        $this->localIconsPath = Utils::resolvePath(LOCAL_ICON_PATH);
        $this->localIconsUrl = Utils::resolveUrls(LOCAL_ICON_PATH);
    }


    /**
     * @param array $options
     * @return string
     * @throws \Kirby\Exception\Exception
     */
    public function render(array $options): string
    {
        $this->inx = $options['inx'];

        $options = $this->parseOptions($options);

        $out = "<div id='{$options['id']}' class='pfy-swissmap-container' data-map-inx='$this->inx'></div>\n";

        if ($this->inx === 1) {
            Page::addJs('const pfyMaps = [];');
            Assets::addAssets('MAP_SEARCH');
        }

        $centerpois = ($options['centerpois'] ?? true) ? "\n    centerpois:true," : '';

        $map = "pfyMaps[{$this->inx}]";

        $jq = <<<EOT

$map = new SearchChMap({
    container: '{$options['id']}',
    center: {$options['location']},
    zoomlevel: {$options['zoom']},
    {$options['attributes']}$centerpois
});{$options['customPOIs']}


EOT;

        Page::addJsReady($jq);

        $this->renderCss($options);

        return $out;
    } // render


    private function renderCss($options): void
    {
        $cssRules = '';
        $minHeight = ($options['minHeight'] ?? false) ?: '200px';
        $cssRules = "min-height: $minHeight;";
        $height = $options['height'] ?? false;
        if ($height) {
            $cssRules .= "height: $height;";
        }
        if ($cssRules) {
            Page::addCss("#{$options['id']} { $cssRules }");
        }
    } // renderCss


    private function parseOptions(array $options): array
    {
        $res = [];

        $res['id'] = ($options['id']??false) ?: "pfy-swissmap-container-{$this->inx}";
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
        $res['location'] = $location;

        $res['zoom'] = ($options['zoom']??false) ?: 6;

        $mapType = '';
        if (($options['mapType'] ?? false) && in_array($options['mapType'], ['aerial', 'street', 'satellite'])) {
            if ($options['mapType'] === 'satellite') {
                $options['mapType'] = 'aerial';
            }
            $mapType = "\ttype: '{$options['mapType']}',\n";
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

        // POIgroups:
        $poigroups = $options['poigroups'] ?? null;
        if ($poigroups === false) {
            $poigroups = "\tpoigroups: '-',\n"; // show no POIgroups at all
        } elseif ($poigroups !== null) {
            $poigroups = "\tpoigroups: '$poigroups',\n";
        } else {
            $poigroups = '';
        }

        $this->customPOIIcon = ($options['customPOIIcon']??false) ?: '';
        $customPOIs = ($options['customPOIs']??false) ?: '';
        if ($customPOIs) {
            $customPOIs = $this->handleCustomPOIs($customPOIs);
        }
        $res['customPOIs'] = $customPOIs;

        $drawing = ($options['drawing']??false) ?: '';
        if ($drawing) {
            $drawing = "\tdrawing: '$drawing',\n";
        }

        $marker = ($options['marker'] ?? true) ? 'true' : 'false';
        $marker = "marker: $marker,\n";

        $gestureHandling = ($options['gestureHandling']??false) ?: '';
        if ($gestureHandling) {
            $gestureHandling = "\tgestureHandling: '$gestureHandling',\n";
        }
        $res['attributes'] = "$marker$mapType$route$controls$poigroups$drawing$gestureHandling";

        $res['centerpois'] = $options['centerpois'] ?? true;
        $res['height'] = $options['height'] ?? false;
        $res['minHeight'] = $options['minHeight'] ?? false;

        return $res;
    } // parseOptions


    /**
     * @param string $str
     * @return string
     */
    private function getLocation(string $str): string
    {
        // 'street number zip city'
        // '1.00, 2.00'
        // '[1.00, 2.00]'
        //
        if (preg_match('/^\s* \[? \s* (\d+\.?\d*) \s*,\s* (\d+\.?\d*) \s* ]? \s*$/x', $str, $m)) {
            $str = "[{$m[1]}, {$m[2]}]";
        } else {
            $str = "'$str'";
        }

        return $str;
    } // getLocation


    /**
     * @param string $customPOIs
     * @return string
     * @throws \Exception
     */
    private function handleCustomPOIs(mixed $customPOIs): string
    {
        $jq = '';
        $map = "pfyMaps[{$this->inx}]";

        if (is_array($customPOIs)) {
            $location = $customPOIs['center'] ?? '';
            $title = $customPOIs['title'] ?? '';
            $description = $customPOIs['description'] ?? ($customPOIs['html'] ?? '');
            $poiIcon = $customPOIs['icon'] ?? $this->customPOIIcon;
            $jq .= <<<EOT
    
        $map.addPOI(new SearchChPOI({ 
            center: $location, 
            title:'$title', 
            html:'$description', 
            icon:'$poiIcon' 
        }));
    
EOT;

        } elseif (is_string($customPOIs)) {
            if (preg_match('/^(\w+):(.*)/', $customPOIs, $m)) {
                if ($m[1] === 'file') {
                    $file = Utils::resolvePath($m[2]);
                    if (file_exists($file)) {
                        $db = new DataStore($file);
                        $recs = $db->read();
                        foreach ($recs as $rec) {
                            if (isset($rec[0])) {
                                $location = trim($this->getLocation($rec[0]), '"\'');
                                if (preg_match('/^\[([\d.]+)\s*,\s*([\d.]+)]$/', $location, $m)) {
                                    $location = "[$m[1], $m[2]]";
                                } else {
                                    $location = "'$location'";
                                }
                                $title = trim($rec[1], '"\'');
                                $description = trim($rec[2], '"\'');
                                $poiIcon = isset($rec[3]) ? trim($rec[3], '"\'') : $this->customPOIIcon;
                            } else {
                                $location = $rec['center'] ?? '';
                                if (preg_match('/([\d.]+)\s*,\s*([\d.]+)/', $location, $m)) {
                                    $location = "[$m[1], $m[2]]";
                                } else {
                                    $location = "'$location'";
                                }
                                $title = trim($rec['title'] ?? '', '"\'');
                                $description = trim($rec['html'] ?? '', '"\'');
                                $poiIcon = ($rec['icon'] ?? false) ?: $this->customPOIIcon;
                            }
                            if ($poiIcon) {
                                if (!fileExt($poiIcon)) {
                                    $poiIcon .= '.png';
                                }
                                if (!str_contains($poiIcon, '/')) {
                                    if (file_exists($this->localIconsPath . $poiIcon)) {
                                        $poiIcon = $this->localIconsUrl . $poiIcon;
                                    } else {
                                        $poiIcon = MAP_SEARCH_ICON_PATH . $poiIcon;
                                    }
                                }
                                $poiIcon = "icon:'$poiIcon'";
                            }
                            $jq .= <<<EOT
    
        $map.addPOI(new SearchChPOI({ 
            center: $location, 
            title:'$title', 
            html:'$description', 
            $poiIcon
        }));
    
EOT;
                        }
                    }
                }
            }
        }
        return $jq;
    } // handleCustomPOIs


    public function listIcons(): string
    {
        $poiIcons = [
            'anzeigen.png',
            'apotheke.png',
            'autoverlad.png',
            'autoverlad_g.png',
            'autoverlad_r.png',
            'bad.png',
            'bar.png',
            'baustelle.png',
            'berg.png',
            'bergbahn.png',
            'bus.png',
            'business.png',
            'business_p.png',
            'cafe.png',
            'camping.png',
            'feuerstelle.png',
            'freibad.png',
            'fusspass.png',
            'gastro.png',
            'gebaeude.png',
            'geldautomat.png',
            'geo.png',
            'gesperrt.png',
            'gondelbahn.png',
            'grill.png',
            'haltestelle.png',
            'hotel.png',
            'immo.png',
            'kino.png',
            'kirche.png',
            'kultur.png',
            'ladestation.png',
            'ladestation_g.png',
            'ladestation_o.png',
            'ladestation_r.png',
            'luftseilbahn.png',
            'maplink.png',
            'meteoradar.png',
            'mobility.png',
            'museum.png',
            'p+rail.png',
            'p0.png',
            'p1.png',
            'p100.png',
            'p2.png',
            'p3.png',
            'p4.png',
            'p5.png',
            'p6.png',
            'p7.png',
            'p8.png',
            'p9.png',
            'park_and_rail.png',
            'parkandrail.png',
            'parkhaus.png',
            'parkhaus_g.png',
            'parkhaus_o.png',
            'parkhaus_r.png',
            'parking.png',
            'parkplatz.png',
            'pass.png',
            'person.png',
            'polizei.png',
            'post.png',
            'post_g.png',
            'post_r.png',
            'restaurant.png',
            'route_bus.png',
            'route_car.png',
            'route_change.png',
            'route_end.png',
            'route_ship.png',
            'route_start.png',
            'route_tram.png',
            'route_via.png',
            'route_walk.png',
            'route_zug.png',
            'schienenbahn.png',
            'schiff.png',
            'schule.png',
            'scooter.png',
            'scooter_g.png',
            'scooter_o.png',
            'scooter_r.png',
            'service.png',
            'sesselbahn.png',
            'shop.png',
            'skilift.png',
            'spielplatz.png',
            'spital.png',
            'stau.png',
            'tankstelle.png',
            'tel.png',
            'tel_business.png',
            'tel_private.png',
            'temp_1.png',
            'temp_10.png',
            'temp_11.png',
            'temp_12.png',
            'temp_13.png',
            'temp_14.png',
            'temp_15.png',
            'temp_16.png',
            'temp_17.png',
            'temp_18.png',
            'temp_19.png',
            'temp_2.png',
            'temp_20.png',
            'temp_21.png',
            'temp_22.png',
            'temp_23.png',
            'temp_24.png',
            'temp_25.png',
            'temp_26.png',
            'temp_27.png',
            'temp_28.png',
            'temp_29.png',
            'temp_3.png',
            'temp_30.png',
            'temp_31.png',
            'temp_32.png',
            'temp_33.png',
            'temp_34.png',
            'temp_35.png',
            'temp_36.png',
            'temp_37.png',
            'temp_38.png',
            'temp_39.png',
            'temp_4.png',
            'temp_40.png',
            'temp_41.png',
            'temp_42.png',
            'temp_5.png',
            'temp_6.png',
            'temp_7.png',
            'temp_8.png',
            'temp_9.png',
            'theater.png',
            'tour.png',
            'tour_alpin.png',
            'tour_inlineskating.png',
            'tour_jogging.png',
            'tour_mountainbike.png',
            'tour_nordicwalking.png',
            'tour_ort.png',
            'tour_ski.png',
            'tour_velo.png',
            'tour_wanderung.png',
            'tour_winterwanderung.png',
            'tram.png',
            'tunnel_g.png',
            'tunnel_r.png',
            'umleitung.png',
            'velo.png',
            'velo_g.png',
            'velo_o.png',
            'velo_r.png',
            'veloland.png',
            'verkehr.png',
            'verwaltung.png',
            'viasuisse.png',
            'vinfo.png',
            'vpass.png',
            'vpass_g.png',
            'vpass_r.png',
            'wasserfall.png',
            'webcam.png',
            'wikipedia.png',
            'winter.png',
            'zug.png',
        ];
        $out = '';
        foreach ($poiIcons as $icon) {
            $iconName = basename($icon, '.png');
            $out .= "<li>$iconName: <img src='".MAP_SEARCH_ICON_PATH."$icon' width='21' height='21' alt='$icon' /></li>\n";
        }
        $out = "<h2>MapSearch Icons</h2>\n<ul class='pfy-mapsearch-icons'>\n$out</ul>\n";
        $css = <<<EOT
.pfy-mapsearch-icons {
    list-style: none;
}
.pfy-mapsearch-icons li {
    display: inline-block;
    width: 12em;
}
EOT;
        Page::addCss($css);

        return $out;
    } // listIcons

} // MapSearch