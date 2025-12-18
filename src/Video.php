<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\explodeTrim;

class Video
{
    private static $inx = 0;

    public static function render($options)
    {
        self::$inx++;

        self::loadAssets();

        // variant youtube video:
        if ($options['youtube']??false) {
            return self::renderYoutube($options);

        } else {
            // variant locally stored video:
            return self::renderLocalVideo($options);
        }
    } //  render



    private static function renderLocalVideo($options): string
    {
        $html = '';
        $attributes = '';
        $inx = self::$inx;
        $title = $options['title'] ? " title='{$options['title']}'" : '';
        $class = $options['class'] ?: $options['wrapperClass'];
        $class = $class ? " $class" : '';
        $startAt = $options['startAt'] ? "#t={$options['startAt']}" : '';

        // determine source files:
        $file = $options['file'] ?: $options['src'];
        $src = '';
        // case 1: no comma-separated list, no extension or last car is '*':
        if (!str_contains($file, ',') && !(pathinfo($file, PATHINFO_EXTENSION)) || (substr($file, -1) === '*')) {
            $path = dirname($file) . '/';
            $file = Utils::resolvePath(rtrim($file, '*'));
            $files = [];
            foreach (PFY_SUPPORTED_VIDEO_FORMATS as $ext) {
                $f = "$file.$ext";
                if (file_exists($f)) {
                    $files[] = $path . basename($f);
                } else {
                    $dir = getDir("$file*.$ext");
                    foreach ($dir as $f) {
                        $files[] = $path . basename($f);
                    }
                }
            }
            // case 2: comma-separated list:
        } else {
            $files = explodeTrim(',', $file);
        }
        // run through all source files and render <source> tags:
        foreach ($files as $file) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            if (in_array($ext, PFY_SUPPORTED_VIDEO_FORMATS)) {
                $src .= "    <source src='$file$startAt' type='video/$ext'>\n";
            }
        }

        $styles = self::getStyles($options);

        // misc $attributes:
        if ($poster = ($options['poster'] ?? false)) {
            $attributes .= " poster=\"$poster\"";
        }
        if ($options['controls'] ?? false) {
            $attributes .= ' controls';
        }
        if ($options['autoplay'] ?? false) {
            $attributes .= ' autoplay muted';
        } elseif ($options['muted'] ?? false) {
            $attributes .= ' muted';
        }
        if ($options['loop'] ?? false) {
            $attributes .= ' loop';
        }

        // assemble output:
        if ($caption = ($options['caption'] ?? false)) {
            $html .= <<<EOT

<figure id="pfy-video-wrapper-$inx" class="pfy-video-wrapper$class"$styles>
  <video$attributes$title>
$src
    Your browser does not support the video tag.
  </video>
  <figcaption>$caption</figcaption>
</figure><!-- /pfy-video-wrapper -->

EOT;

        } else {
            $html .= <<<EOT

<div id="pfy-video-wrapper-$inx" class="pfy-video-wrapper$class"$style>
  <video$attributes$title>
$src
    Your browser does not support the video tag.
  </video>
</div><!-- /pfy-video-wrapper -->

EOT;
        }
        return $html;
    } // renderLocalVideo


    private static function renderYoutube($options)
    {
        $youtube = $options['youtube'];
        $inx = self::$inx;
        $title = $options['title'] ? " title='{$options['title']}'" : '';
        $class = $options['class'] ?: $options['wrapperClass'];
        $class = $class ? " $class" : '';
        $styles = self::getStyles($options);

        $startAt = $options['startAt'] ? "&amp;start={$options['startAt']}" : '';
        $html = <<<EOT
<iframe $styles src="https://www.youtube.com/embed/$youtube$startAt"$title allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>

EOT;

        // assemble output:
        if ($caption = ($options['caption'] ?? false)) {
            $html = <<<EOT

<figure id="pfy-video-wrapper-$inx" class="pfy-video-wrapper$class"$styles>
$html
  <figcaption>$caption</figcaption>
</figure><!-- /pfy-video-wrapper -->

EOT;

        } else {
            $html = <<<EOT

<div id="pfy-video-wrapper-$inx" class="pfy-video-wrapper$class"$styles>
$html
</div><!-- /pfy-video-wrapper -->

EOT;
        }

        return $html;
    } // renderYoutube


    private static function loadAssets(): void
    {
        if (self::$inx === 1) {
            Assets::addAssets([
                'media/plugins/pgfactory/pagefactory-pageelements/css/-video.css',
            ]);
        }
    } // loadAssets

    
    private static function getStyles($options): string
    {
        $style = '';
        if ($width = ($options['width'] ?? false)) {
            $style .= "width:$width;";
        }
        if ($height = ($options['height'] ?? false)) {
            $style .= "height:$height;";
            if (!$width) {
                $style .= "width:auto;";
            }
        }
        $style = $style ? ' style="' . $style . '"' : '';
        return $style;
    } // getStyles


} // Video