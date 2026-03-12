<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\explodeTrim;

class Video
{
    private static int $inx = 0;

    public static function render(array $options): string
    {
        self::$inx++;
        self::loadAssets();

        if ($options['youtube'] ?? false) {
            return self::renderYoutube($options);
        }
        return self::renderLocalVideo($options);
    } //  render


    private static function renderLocalVideo(array $options): string
    {
        $inx = self::$inx;
        $title = ($options['title'] ?? false) ? " title='{$options['title']}'" : '';
        $class = ($options['class'] ?? '') ?: ($options['wrapperClass'] ?? '');
        $class = $class ? " $class" : '';
        $startAt = ($options['startAt'] ?? false) ? "#t={$options['startAt']}" : '';
        $styles = self::getStyles($options);

        // determine source files:
        $file = ($options['file'] ?? '') ?: ($options['src'] ?? '');
        $files = self::resolveSourceFiles($file);

        // render <source> tags:
        $src = '';
        foreach ($files as $f) {
            $ext = pathinfo($f, PATHINFO_EXTENSION);
            if (in_array($ext, PFY_SUPPORTED_VIDEO_FORMATS)) {
                $src .= "    <source src='$f$startAt' type='video/$ext'>\n";
            }
        }

        // misc attributes:
        $attributes = '';
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

        $videoHtml = <<<EOT
  <video$attributes$title>
$src    Your browser does not support the video tag.
  </video>
EOT;

        return self::wrapContent($videoHtml, $inx, $class, $styles, $options['caption'] ?? false);
    } // renderLocalVideo


    private static function renderYoutube(array $options): string
    {
        $inx = self::$inx;
        $title = ($options['title'] ?? false) ? " title='{$options['title']}'" : '';
        $class = ($options['class'] ?? '') ?: ($options['wrapperClass'] ?? '');
        $class = $class ? " $class" : '';
        $styles = self::getStyles($options);

        $youtube = $options['youtube'];
        $startAt = ($options['startAt'] ?? false) ? "&amp;start={$options['startAt']}" : '';

        $iframeHtml = <<<EOT
  <iframe$styles src="https://www.youtube.com/embed/$youtube$startAt"$title allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
EOT;

        return self::wrapContent($iframeHtml, $inx, $class, $styles, $options['caption'] ?? false);
    } // renderYoutube


    /**
     * Resolves video source file(s) from a file path or comma-separated list.
     */
    private static function resolveSourceFiles(string $file): array
    {
        // case: no comma-separated list AND (no extension or last char is '*'):
        if (!str_contains($file, ',') && (!(pathinfo($file, PATHINFO_EXTENSION)) || substr($file, -1) === '*')) {
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
            return $files;
        }

        // case: comma-separated list or explicit file with extension:
        return explodeTrim(',', $file);
    } // resolveSourceFiles


    /**
     * Wraps inner HTML in a figure (with caption) or div wrapper.
     */
    private static function wrapContent(string $innerHtml, int $inx, string $class, string $styles, string|false $caption): string
    {
        if ($caption) {
            return <<<EOT

<figure id="pfy-video-wrapper-$inx" class="pfy-video-wrapper$class"$styles>
$innerHtml
  <figcaption>$caption</figcaption>
</figure><!-- /pfy-video-wrapper -->

EOT;
        }

        return <<<EOT

<div id="pfy-video-wrapper-$inx" class="pfy-video-wrapper$class"$styles>
$innerHtml
</div><!-- /pfy-video-wrapper -->

EOT;
    } // wrapContent


    private static function loadAssets(): void
    {
        if (self::$inx === 1) {
            Assets::addAssets([
                'media/plugins/pgfactory/pagefactory-pageelements/css/-video.css',
            ]);
        }
    } // loadAssets


    private static function getStyles(array $options): string
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
        return $style ? ' style="' . $style . '"' : '';
    } // getStyles


} // Video
