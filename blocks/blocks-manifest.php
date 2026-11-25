<?php defined( 'ABSPATH' ) || exit;
// This file is generated. Do not modify it manually.
return array(
	'pattern-actions' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-actions',
		'title' => 'Pattern Actions',
		'category' => 'theme',
		'description' => 'Copy pattern, Add to selection and View code for a library pattern.',
		'keywords' => array(
			'copy',
			'selection',
			'code'
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'attributes' => array(
			'copy' => array(
				'type' => 'boolean',
				'default' => true
			),
			'select' => array(
				'type' => 'boolean',
				'default' => true
			),
			'code' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	),
	'pattern-back' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-back',
		'title' => 'Pattern Library Link',
		'category' => 'theme',
		'description' => 'A link back to the pattern library.',
		'keywords' => array(
			'back',
			'library'
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	),
	'pattern-filter' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-filter',
		'title' => 'Pattern Filter',
		'category' => 'theme',
		'description' => 'Filters the pattern library by kind, category, tag or language, several values at once: a button with checkboxes (in Pattern Filters), links, or a cloud. Shown with two values or more.',
		'keywords' => array(
			'filter',
			'category',
			'tag',
			'language',
			'kind'
		),
		'usesContext' => array(
			'windrose-knotboard/form'
		),
		'attributes' => array(
			'taxonomy' => array(
				'type' => 'string',
				'enum' => array(
					'kind',
					'category',
					'tag',
					'language'
				),
				'default' => 'kind'
			),
			'display' => array(
				'type' => 'string',
				'enum' => array(
					'dropdown',
					'links',
					'cloud'
				),
				'default' => 'dropdown'
			)
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	),
	'pattern-filters' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-filters',
		'title' => 'Pattern Filters',
		'category' => 'theme',
		'description' => 'The library\'s search and filters in one row, with one Filter button. Holds Pattern Search and Pattern Filter blocks.',
		'keywords' => array(
			'filter',
			'search',
			'library'
		),
		'attributes' => array(
			'isForm' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'providesContext' => array(
			'windrose-knotboard/form' => 'isForm'
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			),
			'multiple' => false
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	),
	'pattern-notes' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-notes',
		'title' => 'Pattern Notes',
		'category' => 'theme',
		'description' => 'Notes on a library pattern: a translation not reviewed yet, and the credits of its photos.',
		'keywords' => array(
			'credits',
			'translation',
			'photo'
		),
		'attributes' => array(
			'credits' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'supports' => array(
			'html' => false,
			'typography' => array(
				'fontSize' => true
			)
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	),
	'pattern-preview' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-preview',
		'title' => 'Pattern Preview',
		'category' => 'theme',
		'description' => 'A library pattern drawn in the visitor\'s chosen style: a thumbnail in a Query Loop, or large on a pattern\'s page.',
		'keywords' => array(
			'pattern',
			'preview',
			'thumbnail'
		),
		'usesContext' => array(
			'postId',
			'postType'
		),
		'attributes' => array(
			'size' => array(
				'type' => 'string',
				'enum' => array(
					'thumb',
					'full'
				),
				'default' => 'thumb'
			)
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	),
	'pattern-search' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-search',
		'title' => 'Pattern Search',
		'category' => 'theme',
		'description' => 'A search field for the pattern library.',
		'keywords' => array(
			'search'
		),
		'usesContext' => array(
			'windrose-knotboard/form'
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	),
	'pattern-selection' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-selection',
		'title' => 'Pattern Selection',
		'category' => 'theme',
		'description' => 'The visitor\'s chosen patterns: order, remove, copy the header, the footer and the content, download, and a preview of them together.',
		'keywords' => array(
			'selection',
			'copy'
		),
		'supports' => array(
			'html' => false,
			'multiple' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	),
	'pattern-selection-bar' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-selection-bar',
		'title' => 'Pattern Selection Bar',
		'category' => 'theme',
		'description' => 'How many patterns the visitor chose, with See selection (opens in a new tab).',
		'keywords' => array(
			'selection'
		),
		'supports' => array(
			'html' => false,
			'multiple' => false
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	),
	'pattern-style' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'windrose/pattern-style',
		'title' => 'Pattern Preview Style',
		'category' => 'theme',
		'description' => 'Lets visitors choose the style the pattern previews use (Windrose Livery\'s ready styles).',
		'keywords' => array(
			'style',
			'preview'
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'windrose-knotboard',
		'editorScript' => 'windrose-knotboard-blocks',
		'viewScript' => 'windrose-knotboard-view',
		'style' => 'windrose-knotboard-view'
	)
);
