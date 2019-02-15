<?php

/* default left and right menus */
$config['left'] = 1;
$config['right'] = 2;

$config['right protected'] = 58;
$config['right public'] = 57;

/* default styles - used in migrations */
$config['styles'] = [
	'protected'=>['icon'=>'user-secret','color'=>'00007F'],
	'public'=>['icon'=>'user','color'=>'7F0002'],
	'main-menu'=>['icon'=>'window-close','color'=>'000000'],
	'orange'=>['icon'=>'lemon-o','color'=>'DB643A'],
];

$config['bootstrap nav'] = [
	'navigation_open'=>'',
	'navigation_close'=>'',
	
	'item_open'=>'<li>',
	'item_close'=>'</li>',

	'item_open_dropdown'=>'<li class="dropdown">',
	'item_close_dropdown'=>'</li>',
	
	'item_open_dropdown_sub'=>'<li class="dropdown dropdown-submenu">',
	
	'anchor'=>'<a href="{url}" data-color="{color}" data-icon="{icon}" target="{target}">{text}</a>',
	'anchor_dropdown'=>'<a href="{url}" class="{class} dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{text}</a>',
	'anchor_sub_dropdown'=>'<a href="{url}" class="{class} dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{text}</a>',
	
	'hr'=>'<li role="separator" class="divider"></li>',
	
	'dropdown_open'=>'<ul class="dropdown-menu" role="menu">',
	'dropdown_close'=>'</ul>',
];

$config['bootstrap nav icons'] = [
	'navigation_open'=>'',
	'navigation_close'=>'',
	
	'item_open'=>'<li>',
	'item_close'=>'</li>',

	'item_open_dropdown'=>'<li class="dropdown">',
	'item_close_dropdown'=>'</li>',
	
	'item_open_dropdown_sub'=>'<li class="dropdown dropdown-submenu">',
	
	'anchor'=>'<a href="{url}" data-color="{color}" data-icon="{icon}" target="{target}"><i class="fa fa-{icon}"></i> {text}</a>',
	'anchor_dropdown'=>'<a href="{url}" class="{class} dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><i class="fa fa-{icon}"></i> {text}</a>',
	'anchor_sub_dropdown'=>'<a href="{url}" class="{class} dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><i class="fa fa-{icon}"></i> {text}</a>',

	'hr'=>'<li role="separator" class="divider"></li>',
	
	'dropdown_open'=>'<ul class="dropdown-menu" role="menu">',
	'dropdown_close'=>'</ul>',
];


$config['dd-list'] = [
	'navigation_open'=>'<ol class="dd-list">',
	'navigation_close'=>'</ol>',
	
	'item_open'=>'<li class="panel-default dd-item dd3-item" data-id="{id}"><div class="btn-primary dd-handle dd3-handle">Drag</div>',
	'item_close'=>'</li>',

	'item_inactive_class'=>'text-muted',

	'content'=>'<div class="btn btn-default dd3-content"><span class="{disable_class}">{text}<small>{url}</small></span></div>',
];

$config['generic'] = [
	'navigation_open'=>'<ul>',
	'navigation_close'=>'</ul>',
	
	'item_open'=>'<li>',
	'item_close'=>'</li>',

	'item_inactive_class'=>'text-muted',

	'content'=>'{text}',
];
