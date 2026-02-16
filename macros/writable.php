<?php
namespace PgFactory\PageFactory;

/*
 * PageFactory Macro (and Twig Function)
 */

use PgFactory\MarkdownPlus\Permission;

return function ($args = '')
{
    $funcName = basename(__FILE__, '.php');
    // Definition of arguments and help-text:
    $config = [
        'options' => [
            'nSlots' => ['Number of writable fields to render', 1],
            'type' => ['[text|textarea] Choice between single and multiple line input', 'text'],
            'file' => ['[strong] Where to store data (default: \~data/writable/{pageId}.json).', null],
            'label' => ['Label saved along with data in data file. For internal documentation only, '.
                'can be freely defined.', null],
            'permission' => ['[anybody|group|users] Defines, who will be able to modify writable widget.', 'anyone'],
            'editPermission' => ['Synonyme for "permission".', 'anyone'],
            'showButton' => ['If true, a small button appears while editing the field.', true],
            'placeholder' => ['Placeholder shown as long as the field is empty. If defined as an array, '.
                'values are assigned to corresponding fields.', null],
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

    $permission = Permission::evaluate($options['permission']);
    $writableGroupName = "writable-group-$inx";
    $groupLabel = $options['label'] ?: '';
    $writableGroupName = preg_replace('/\W/', '_', $writableGroupName);
    $pageId = page()->id();
    $file = $options['file'] ?: "~data/writable/$pageId.json";
    $sessDbFileKey = "db:$pageId:$writableGroupName:file";
    kirby()->session()->set($sessDbFileKey, Utils::resolvePath($file));
    $db = new DataStore($file, [
        'masterFileRecKeyType' => 'index',
        'obfuscateRecKeys' => false,
    ]);

    $data = $db->data();
    $rec = $data[$writableGroupName]??[];
    if (!isset($rec['label']) && $groupLabel) {
        $rec['label'] = $groupLabel;
        $db->updateRec($rec, $writableGroupName, flush:true);
    }

    $textarea = ($options['type']??false) === 'textarea';
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
        $val = $rec[$name]??'';
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
</div>

EOT;

            } else {
                $str .= <<<EOT

<div class='pfy-writable-widget pfy-writable-widget-$i'>
<input type='text' name='$name'$valAttr$placeholderAttr>
<div class="pfy-mini-button"$btnStyle>✓</div>
</div>

EOT;
            }

        } else {
            $str .= <<<EOT

<div class='pfy-writable-widget pfy-writable-widget-$i'>
<div class="pfy-writable-widget-inner">$val</div>
</div>

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
