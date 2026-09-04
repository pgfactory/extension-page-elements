<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro
 */

use PgFactory\MarkdownPlus\Permission;

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'nSlots' => ['Number of writable fields to render', 1],
            'type' => ['[text|textarea] Choice between single and multiple line input.', 'text'],
            'preset' => ['If defined and no value has been entered yet, this string is injected.', null],
            'file' => ['[strong] Where to store data (default: \~data/writable/{pageId}.json).', null],
            'label' => ['Label saved along with data in data file. For internal documentation only, '.
                'can be freely defined.', null],
            'permission' => ['[anybody|group|users] Defines, who will be able to modify writable widget.', 'anyone'],
            'editPermission' => ['Synonyme for "permission".', 'anyone'],
            'showButton' => ['If true, a small button appears while editing the field.', true],
            'placeholder' => ['Placeholder shown as long as the field is empty. If defined as an array, '.
                'values are assigned to corresponding fields.', null],
            'markdown' => ['If true and not in edit mode, then the content is markdown compiled before rendered', false],
            'preview' => ['If true and markdown is enabled, then the compiled output is shown following the editing area.', false],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a writable element (input tag). Text that users write into that field is permanently stored 
and further on visible to all.
Optionally, multiple fields as well as textarea fields can be created in one go.

EOT,
    ];

    // parse arguments, handle help and showSource:
    if (is_string($res = TransVars::initMacro(__FILE__, $config, $args))) {
        return $res;
    } else {
        list($options, $sourceCode, $inx) = $res;
        $str = $sourceCode;
    }

    $pageId = page()->id();
    $permission = Permission::evaluate($options['permission']);
    if ($permission) {
        $accessPermission = 'write';
        kirby()->session()->set("pfy.$pageId.accessPermission", $accessPermission);
    }
    $writableGroupName = "writable-group-$inx";
    $groupLabel = $options['label'] ?: '';
    $writableGroupName = preg_replace('/\W/', '_', $writableGroupName);
    $file = $options['file'] ?: "~data/writable/$pageId.json";
    $sessDbFileKey = "db:$pageId:$writableGroupName:file";
    kirby()->session()->set($sessDbFileKey, Utils::resolvePath($file));
    $db = new DataStore($file, [
        'masterFileRecKeyType' => 'key',
        'obfuscateRecKeys' => false,
    ]);

    $data = $db->data();
    $rec = $data[$writableGroupName]??[];
    if (!isset($rec['label']) && $groupLabel) {
        $rec['label'] = $groupLabel;
        $db->updateRec($rec, $writableGroupName, flush:true);
    }

    $textarea = ($options['type']??false) === 'textarea';
    $preset = $options['preset'] ?: '';
    if ($textarea) {
        $preset = str_replace('\\n', "\n", $preset);
    }
    if ($placeholders = ($options['placeholder']??'')) {
        if (is_string($placeholders)) {
            $placeholders = array_fill(0,$options['nSlots'], $placeholders);
        } elseif (is_array($placeholders)) {
            $placeholders = array_values($placeholders);
        } else {
            $placeholders = [];
        }
    }
    $btnStyle = ($options['showButton']??true) ? '': ' style="display: none"';

    // assemble output:
    for ($i=1; $i<=$options['nSlots']; $i++) {
        $name = $i;
        $val = ($rec[$name]??'') ?: $preset;
        $val = str_replace("'", '&#39;', $val);
        $valAttr = $val ? " value='$val'" : '';
        if ($permission) {
            $placeholder = $placeholders[$i-1]??'';
            $placeholderAttr = $placeholder ? " placeholder='$placeholder'" : '';
            if ($textarea) {
                $str .= <<<EOT

<div class='pfy-writable-textarea-widget pfy-writable-widget-$i pfy-auto-grow '>
<textarea name='$name'$placeholderAttr>$val</textarea>
<div class="pfy-mini-button"$btnStyle>✓</div>
</div><!-- /pfy-writable-textarea-widget -->

EOT;
                if ($options['markdown'] && $options['preview']) {
                    $val = TransVars::compile($val);
                    $str .= <<<EOT

<div class="pfy-writable-widget-rendered">$val</div>

EOT;
                }

            } else {
                $str .= <<<EOT

<div class='pfy-writable-widget pfy-writable-widget-$i'>
<input type='text' name='$name'$valAttr$placeholderAttr>
<div class="pfy-mini-button"$btnStyle>✓</div>
</div><!-- /pfy-writable-textarea-widget -->

EOT;
            }

        } elseif ($options['markdown']) {
            $val = TransVars::compile($val);
            $str .= <<<EOT

<div class="pfy-writable-widget-rendered">$val</div>

EOT;

        } else {
                        $str .= <<<EOT

<div class='pfy-writable-widget pfy-writable-widget-$i'>
<div class="pfy-writable-widget-inner">$val</div>
</div><!-- /pfy-writable-textarea-widget -->

EOT;
        }

    }

    $str = <<<EOT

<div class="pfy-writable-widget-wrapper pfy-writable-widget-wrapper-$inx" data-writable-src="$inx" data-writable-group="$writableGroupName">
$str
</div>

EOT;

    Assets::addAssets('WRITABLE');

    return $str;
};
