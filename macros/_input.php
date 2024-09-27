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
            'nSlots' => ['', 1],
            'type' => ['', 'text'],
            'file' => ['', null],
            'name' => ['', 'input-group-'],
            'permission' => ['[anybody|group|users] Defines, who will be able to modify input widget.', 'anyone'],
            'editPermission' => ['Synonyme for "permission".', 'anyone'],
        ],
        'summary' => <<<EOT

# $funcName()

ToDo: describe purpose of function
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
    $inputGroupName = $options['name'].$inx;
    $pageId = PageFactory::$pageId;
    $file = $options['file'] ?: "~data/input/$pageId.yaml";
    $sessDbKey = "db:$pageId:$inputGroupName:file";
    kirby()->session()->set($sessDbKey, $file);
    $db = new DataSet($file, [
        'masterFileRecKeyType' => '_reckey',
        'obfuscateRecKeys' => false,
    ]);

    $data = $db->data();
    $rec = $data[$inputGroupName]??[];

    $type = ($options['type']??false) ?: 'text';
    // assemble output:
    for ($i=1; $i<=$options['nSlots']; $i++) {
        $val = $rec["input_$i"]??'';
        $valAttr = $val ? " value='$val'" : '';
        if ($permission) {
            $str .= <<<EOT
<div class='pfy-input-widget pfy-input-widget-$i'>
<input type='$type' name='input-$i'$valAttr>
<div class="pfy-mini-button">✓</div>
</div>
EOT;

        } else {
            $str .= <<<EOT
<div class='pfy-input-widget pfy-input-widget-$i'>
<div class="pfy-input-widget-inner">$val</div>
</div>
EOT;
        }

    }

    $str = <<<EOT

<div class="pfy-input-widget-wrapper pfy-input-widget-wrapper-$inx" data-input-src="$inx" data-input-group="$inputGroupName">
$str
</div>

EOT;

    PageFactory::$pg->addAssets('INPUT_WIDGET');

    return $str;
};
