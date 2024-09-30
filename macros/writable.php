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
            'file' => ['[strong] Where to store data (default: \~data/writable/{pageId}.yaml).', null],
            'name' => ['Name of the writable group as saved in data file.', 'writable-group-'],
            'permission' => ['[anybody|group|users] Defines, who will be able to modify writable widget.', 'anyone'],
            'editPermission' => ['Synonyme for "permission".', 'anyone'],
            'placeholder' => ['Placeholder shown as long as the field is empty.', null],
        ],
        'summary' => <<<EOT

# $funcName()

Renders a writable element (input tag). Text that users write into that field is permanently stored 
furtheron visible to all.
Optionally, multiple fields can be created in one go.

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
    $writableGroupName = $options['name'].'_'.$inx;
    $writableGroupName = preg_replace('/\W/', '_', $writableGroupName);
    $pageId = PageFactory::$pageId;
    $file = $options['file'] ?: "~data/writable/$pageId.yaml";
    $sessDbKey = "db:$pageId:$writableGroupName:file";
    kirby()->session()->set($sessDbKey, $file);
    $db = new DataSet($file, [
        'masterFileRecKeyType' => '_reckey',
        'obfuscateRecKeys' => false,
    ]);

    $data = $db->data();
    $rec = $data[$writableGroupName]??[];

    $textarea = ($options['type']??false) === 'textarea';
    if ($placeholders = ($options['placeholder']??'')) {
        if (is_string($placeholders)) {
            $placeholders = array_fill(1,$options['nSlots'], $placeholders);
        }
    }

    // assemble output:
    for ($i=1; $i<=$options['nSlots']; $i++) {
        $val = $rec["writable_$i"]??'';
        $val = str_replace("'", '&#39;', $val);
        $valAttr = $val ? " value='$val'" : '';
        if ($permission) {
            $placeholder = $placeholders[$i]??'';
            $placeholderAttr = $placeholder ? " placeholder='$placeholder'" : '';
            if ($textarea) {
                $str .= <<<EOT

<div class='pfy-writable-textarea-widget pfy-writable-widget-$i pfy-auto-grow '>
<textarea name='writable_$i'$placeholderAttr>$val</textarea>
<div class="pfy-mini-button">✓</div>
</div>

EOT;

            } else {
                $str .= <<<EOT

<div class='pfy-writable-widget pfy-writable-widget-$i'>
<input type='text' name='writable_$i'$valAttr$placeholderAttr>
<div class="pfy-mini-button">✓</div>
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

    PageFactory::$pg->addAssets('WRITABLE');

    return $str;
};
