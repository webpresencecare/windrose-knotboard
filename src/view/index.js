/**
 * Knotboard on the site's pages (blocks windrose/pattern-*): the preview style, thumbnails and large previews, Copy
 * pattern, Add to selection, View code, the selection bar and the selection page. Plain DOM, no framework.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import {
	contentHeight,
	currentStyle,
	documentFor,
	draw,
	fill,
	htmlFor,
	keepStyle,
	MERMAID,
	MERMAID_KEY,
	mermaidStyle,
	split,
} from './preview.js';
import {
	count,
	fileName,
	isSelected,
	move,
	pageOrder,
	patternFile,
	readSelection,
	refresh,
	remove,
	toggle,
} from './selection.js';
import './view.scss';

const config = window.windroseKnotboard || {};
const SELECTION_KEY = `windrose-knotboard-selection-${ config.site }`;
const THUMB_WIDTH = 1200;
const THUMB_MAX = 340;
const THUMB_MIN = 96;
const PHONE = 390;

/* ---------- Small helpers ---------- */

/**
 * Makes an element.
 *
 * @param {string}                    tag      Tag.
 * @param {Object}                    attrs    Attributes (on* are listeners).
 * @param {Array<Node|string>|string} children Children.
 * @return {HTMLElement} Element.
 */
function h( tag, attrs = {}, children = [] ) {
	const el = document.createElement( tag );
	Object.entries( attrs ).forEach( ( [ key, value ] ) => {
		if ( key.startsWith( 'on' ) && 'function' === typeof value ) {
			el.addEventListener( key.slice( 2 ).toLowerCase(), value );
		} else if ( true === value ) {
			el.setAttribute( key, '' );
		} else if ( false !== value && null !== value && undefined !== value ) {
			el.setAttribute( key, String( value ) );
		}
	} );
	( Array.isArray( children ) ? children : [ children ] ).forEach(
		( child ) => el.append( child )
	);
	return el;
}

/**
 * Says something to screen readers and shows it in a status line when there is one.
 *
 * @param {string}           message Message.
 * @param {HTMLElement|null} where   Visible status line.
 */
function say( message, where = null ) {
	speak( message );
	if ( where ) {
		where.textContent = message;
	}
}

/**
 * What to do with the pictures of a copy: they still come from this library until the visitor uploads them on their
 * own site (WordPress offers Upload for outside pictures).
 *
 * @param {string} kind header, footer, or anything else (content).
 * @return {string} Text.
 */
function picturesText( kind ) {
	return 'header' === kind || 'footer' === kind
		? __(
				'Its pictures still come from this library: on your site, select each picture and click Upload to Media Library, or Replace.',
				'windrose-knotboard'
			)
		: __(
				'Its pictures still come from this library: on your site, click Upload under External media in the publish panel, or select a picture and click Upload to Media Library (or Replace).',
				'windrose-knotboard'
			);
}

/**
 * Copies text: the clipboard, or the old way where the browser refuses it.
 *
 * @param {string} text Text.
 * @return {Promise<boolean>} Done.
 */
async function copyText( text ) {
	try {
		await window.navigator.clipboard.writeText( text );
		return true;
	} catch {
		const area = h( 'textarea', {
			readonly: true,
			style: 'position:fixed;opacity:0;inset-block-start:0',
		} );
		area.value = text;
		document.body.append( area );
		area.select();
		let done = false;
		try {
			done = document.execCommand( 'copy' );
		} catch {}
		area.remove();
		return done;
	}
}

/**
 * Saves a pattern file (core's Site Editor imports it).
 *
 * @param {string} title    Title.
 * @param {string} content  Block markup.
 * @param {string} fallback File name when the title has no letters.
 */
function download( title, content, fallback ) {
	const blob = new window.Blob(
		[ JSON.stringify( patternFile( title, content ), null, '\t' ) ],
		{ type: 'application/json' }
	);
	const url = window.URL.createObjectURL( blob );
	const link = h( 'a', { href: url, download: fileName( title, fallback ) } );
	document.body.append( link );
	link.click();
	link.remove();
	window.setTimeout( () => window.URL.revokeObjectURL( url ), 1000 );
}

/* ---------- Selection storage ---------- */

/**
 * The stored selection.
 *
 * @return {Object} Selection.
 */
function getSelection() {
	try {
		return readSelection( window.localStorage.getItem( SELECTION_KEY ) );
	} catch {
		return readSelection( null );
	}
}

/**
 * Stores the selection and updates the page.
 *
 * @param {Object} selection Selection.
 */
function setSelection( selection ) {
	try {
		window.localStorage.setItem(
			SELECTION_KEY,
			JSON.stringify( selection )
		);
	} catch {}
	document.dispatchEvent(
		new window.CustomEvent( 'windrose-knotboard-selection' )
	);
}

/* ---------- Previews ---------- */

/**
 * A frame in a preview block (made once).
 *
 * @param {HTMLElement} block Preview block.
 * @return {HTMLIFrameElement} Frame.
 */
function frameOf( block ) {
	const box = block.querySelector( '.windrose-knotboard-preview__frame' );
	let frame = box.querySelector( 'iframe' );
	if ( frame ) {
		// Drawn again (another style): at once, so it never changes size while the visitor scrolls to it.
		frame.loading = 'eager';
	} else {
		box.textContent = '';
		frame = h( 'iframe', {
			title: sprintf(
				/* translators: %s: pattern title */
				__( 'Preview: %s', 'windrose-knotboard' ),
				block.dataset.title || ''
			),
			sandbox: 'allow-same-origin',
			// Thumbnails load as they come into view, the first time only; large previews at once.
			loading: block.classList.contains( 'is-thumb' ) ? 'lazy' : 'eager',
			tabindex: block.classList.contains( 'is-thumb' ) ? '-1' : null,
		} );
		box.append( frame );
	}
	return frame;
}

/**
 * Fits a thumbnail: the pattern drawn 1200 pixels wide, shrunk to the card.
 *
 * @param {HTMLElement} block Preview block.
 */
function fitThumb( block ) {
	const box = block.querySelector( '.windrose-knotboard-preview__frame' );
	const frame = box?.querySelector( 'iframe' );
	if ( ! frame ) {
		return;
	}
	const scale = box.clientWidth / THUMB_WIDTH;
	const height = contentHeight( frame ) || 600;
	const shown = height * scale;
	// Short patterns (a header, a footer) sit in the middle of a small frame.
	const room = Math.max( THUMB_MIN, Math.min( shown, THUMB_MAX ) );
	frame.style.width = `${ THUMB_WIDTH }px`;
	frame.style.height = `${ height }px`;
	frame.style.transform = `scale(${ scale })`;
	frame.style.top = `${ shown < room ? ( room - shown ) / 2 : 0 }px`;
	box.style.height = `${ room }px`;
}

/**
 * Fits a large preview: its whole height, wide or phone width.
 *
 * @param {HTMLElement} block Preview block.
 */
function fitFull( block ) {
	const frame = block.querySelector( 'iframe' );
	if ( ! frame ) {
		return;
	}
	frame.style.width =
		'phone' === block.dataset.size ? `${ PHONE }px` : '100%';
	// Measured from the patterns themselves, so the frame keeps its height meanwhile: shrinking it would shorten the
	// page and move the visitor's scroll position.
	const height = contentHeight( frame );
	if ( height > 0 ) {
		frame.style.height = `${ height }px`;
	}
}

/**
 * Shows a message in a preview's place.
 *
 * @param {HTMLElement} block   Preview block.
 * @param {string}      message Message.
 */
function previewMessage( block, message ) {
	const box = block.querySelector( '.windrose-knotboard-preview__frame' );
	box.textContent = '';
	box.style.height = '';
	box.append(
		h( 'p', { class: 'windrose-knotboard-preview__wait' }, message )
	);
}

/**
 * Draws every thumbnail of the page with one request, and every large preview.
 */
async function drawPreviews() {
	const style = currentStyle();
	const thumbs = [
		...document.querySelectorAll( '.windrose-knotboard-preview.is-thumb' ),
	];
	if ( thumbs.length ) {
		const ids = [
			...new Set(
				thumbs.map( ( b ) =>
					Number( b.dataset.windroseKnotboardPreview )
				)
			),
		];
		try {
			const drawn = split( await draw( ids, 'list', style ) );
			thumbs.forEach( ( block ) => {
				const inner = htmlFor(
					drawn,
					Number( block.dataset.windroseKnotboardPreview )
				);
				if ( null === inner ) {
					previewMessage(
						block,
						__( 'No preview.', 'windrose-knotboard' )
					);
					return;
				}
				fill(
					frameOf( block ),
					documentFor(
						drawn,
						inner,
						block.dataset.lang,
						block.dataset.dir
					),
					() => fitThumb( block )
				);
			} );
		} catch ( e ) {
			thumbs.forEach( ( block ) =>
				previewMessage(
					block,
					sprintf(
						/* translators: %s: reason */
						__(
							'The preview could not be drawn (%s).',
							'windrose-knotboard'
						),
						e.message
					)
				)
			);
		}
	}
	document
		.querySelectorAll( '.windrose-knotboard-preview.is-full' )
		.forEach( async ( block ) => {
			try {
				const id = Number( block.dataset.windroseKnotboardPreview );
				const drawn = split( await draw( [ id ], 'list', style ) );
				fill(
					frameOf( block ),
					documentFor(
						drawn,
						htmlFor( drawn, id ) ?? '',
						block.dataset.lang,
						block.dataset.dir
					),
					() => fitFull( block )
				);
			} catch ( e ) {
				previewMessage( block, e.message );
			}
		} );
}

/* ---------- Pattern actions ---------- */

let codeDialog = null;

/**
 * The View code dialog (one for the page).
 *
 * @param {string} title  Pattern title.
 * @param {string} markup Block markup.
 */
function showCode( title, markup ) {
	if ( ! codeDialog ) {
		const status = h( 'p', {
			class: 'windrose-knotboard-code__status',
			role: 'status',
		} );
		const area = h( 'textarea', {
			readonly: true,
			rows: '16',
			spellcheck: 'false',
			'aria-label': __( 'Block markup', 'windrose-knotboard' ),
		} );
		codeDialog = h(
			'dialog',
			{
				class: 'windrose-knotboard-code',
				'aria-labelledby': 'windrose-knotboard-code-title',
			},
			[
				h( 'h2', { id: 'windrose-knotboard-code-title' } ),
				area,
				h( 'p', { class: 'windrose-knotboard-code__buttons' }, [
					h(
						'button',
						{
							type: 'button',
							onClick: async () =>
								say(
									( await copyText( area.value ) )
										? __(
												'The pattern is copied. Paste it in your page.',
												'windrose-knotboard'
											)
										: __(
												'Your browser did not allow copying: select the code and copy it.',
												'windrose-knotboard'
											),
									status
								),
						},
						__( 'Copy code', 'windrose-knotboard' )
					),
					h(
						'button',
						{ type: 'button', onClick: () => codeDialog.close() },
						__( 'Close', 'windrose-knotboard' )
					),
				] ),
				status,
			]
		);
		document.body.append( codeDialog );
	}
	codeDialog.querySelector( 'h2' ).textContent = title;
	codeDialog.querySelector( 'textarea' ).value = markup;
	codeDialog.querySelector( '.windrose-knotboard-code__status' ).textContent =
		'';
	codeDialog.showModal();
}

/**
 * The Add to selection buttons follow the selection.
 */
function syncButtons() {
	const selection = getSelection();
	document
		.querySelectorAll( '.windrose-knotboard-actions' )
		.forEach( ( box ) => {
			const button = box.querySelector( '[data-action="select"]' );
			if ( ! button ) {
				return;
			}
			const on = isSelected(
				selection,
				Number( box.dataset.windroseKnotboardPattern )
			);
			button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			button.textContent = on
				? __( 'In selection', 'windrose-knotboard' )
				: __( 'Add to selection', 'windrose-knotboard' );
		} );
}

/**
 * Clicks on the pattern buttons.
 *
 * @param {MouseEvent} event Click.
 */
async function onAction( event ) {
	const button = event.target.closest(
		'.windrose-knotboard-actions [data-action]'
	);
	if ( ! button ) {
		return;
	}
	const box = button.closest( '.windrose-knotboard-actions' );
	const markup = JSON.parse(
		box.querySelector( '.windrose-knotboard-actions__markup' )
			?.textContent || '""'
	);
	const item = {
		id: Number( box.dataset.windroseKnotboardPattern ),
		kind: box.dataset.kind,
		title: box.dataset.title,
		lang: box.dataset.lang,
	};
	const action = button.dataset.action;
	if ( 'copy' === action ) {
		const done = await copyText( markup );
		const label = button.textContent;
		button.textContent = done
			? __( 'Copied', 'windrose-knotboard' )
			: label;
		button.classList.toggle( 'is-copied', done );
		say(
			done
				? sprintf(
						/* translators: %s: pattern title */
						__(
							'%s is copied. Paste it in your page.',
							'windrose-knotboard'
						),
						item.title
					) +
						( '1' === box.dataset.pictures
							? ' ' + picturesText( item.kind )
							: '' )
				: __(
						'Your browser did not allow copying: use View code.',
						'windrose-knotboard'
					)
		);
		window.setTimeout( () => {
			button.textContent = label;
			button.classList.remove( 'is-copied' );
		}, 1600 );
	} else if ( 'select' === action ) {
		const next = toggle( getSelection(), item );
		setSelection( next );
		say(
			isSelected( next, item.id )
				? sprintf(
						/* translators: %s: pattern title */
						__( '%s is in your selection.', 'windrose-knotboard' ),
						item.title
					)
				: sprintf(
						/* translators: %s: pattern title */
						__( '%s left your selection.', 'windrose-knotboard' ),
						item.title
					)
		);
	} else if ( 'code' === action ) {
		showCode( item.title, markup );
	}
}

/* ---------- Selection bar ---------- */

/**
 * The bars follow the selection.
 */
function syncBars() {
	const n = count( getSelection() );
	document.querySelectorAll( '.windrose-knotboard-bar' ).forEach( ( bar ) => {
		bar.hidden = 0 === n;
		bar.querySelector( '.windrose-knotboard-bar__count' ).textContent =
			sprintf(
				/* translators: %d: number of patterns */
				_n(
					'%d pattern in your selection.',
					'%d patterns in your selection.',
					n,
					'windrose-knotboard'
				),
				n
			);
	} );
}

/* ---------- Selection page ---------- */

/**
 * The selection page.
 *
 * @param {HTMLElement} root The block.
 */
function mountSelection( root ) {
	const status = h( 'p', {
		class: 'windrose-knotboard-selection__status',
		role: 'status',
	} );
	const preview = h(
		'div',
		{
			class: 'windrose-knotboard-preview is-full',
			'data-title': __( 'Your selection', 'windrose-knotboard' ),
			'data-size': 'wide',
		},
		[ h( 'div', { class: 'windrose-knotboard-preview__frame' } ) ]
	);
	const sizes = h( 'div', {
		class: 'windrose-knotboard-preview__sizes',
		role: 'group',
		'aria-label': __( 'Preview width', 'windrose-knotboard' ),
	} );
	[
		[ 'wide', __( 'Wide', 'windrose-knotboard' ) ],
		[ 'phone', __( 'Phone', 'windrose-knotboard' ) ],
	].forEach( ( [ size, label ] ) =>
		sizes.append(
			h(
				'button',
				{
					type: 'button',
					'data-size': size,
					'aria-pressed': 'wide' === size ? 'true' : 'false',
					onClick: () => {
						preview.dataset.size = size;
						sizes
							.querySelectorAll( 'button' )
							.forEach( ( b ) =>
								b.setAttribute(
									'aria-pressed',
									b.dataset.size === size ? 'true' : 'false'
								)
							);
						fitFull( preview );
					},
				},
				label
			)
		)
	);
	preview.prepend( sizes );
	const lists = h( 'div', { class: 'windrose-knotboard-selection__lists' } );
	let markup = new Map();
	let pictures = new Set();

	/**
	 * A note on pictures when one of these patterns has some.
	 *
	 * @param {Array}  items Patterns.
	 * @param {string} kind  header, footer or content.
	 * @return {HTMLElement|string} Note, or nothing.
	 */
	const picturesNote = ( items, kind ) =>
		items.some( ( item ) => pictures.has( item.id ) )
			? h(
					'p',
					{ class: 'windrose-knotboard-selection__pictures' },
					picturesText( kind )
				)
			: '';
	let drawing = 0;

	/**
	 * Copies or downloads part of the selection.
	 *
	 * @param {string} what   header, footer or content.
	 * @param {string} action copy or download.
	 */
	const take = async ( what, action ) => {
		const selection = getSelection();
		const items =
			'content' === what
				? selection.content
				: [ selection[ what ] ].filter( Boolean );
		const text = items
			.map( ( item ) => markup.get( item.id ) ?? '' )
			.join( '\n\n' );
		if ( ! text ) {
			return;
		}
		if ( 'download' === action ) {
			const titles = {
				header: selection.header?.title,
				footer: selection.footer?.title,
				content: __(
					'Page from the pattern library',
					'windrose-knotboard'
				),
			};
			download( titles[ what ] || what, text, what );
			say(
				__(
					'The pattern file is in your downloads.',
					'windrose-knotboard'
				),
				status
			);
			return;
		}
		const done = await copyText( text );
		const copied = {
			header: __( 'The header is copied.', 'windrose-knotboard' ),
			footer: __( 'The footer is copied.', 'windrose-knotboard' ),
			content: __( 'The content is copied.', 'windrose-knotboard' ),
		};
		say(
			done
				? copied[ what ]
				: __(
						'Your browser did not allow copying: download the pattern file instead.',
						'windrose-knotboard'
					),
			status
		);
	};

	const buttons = ( what, copyLabel ) =>
		h( 'p', { class: 'windrose-knotboard-selection__buttons' }, [
			h(
				'button',
				{
					type: 'button',
					class: 'wp-element-button',
					onClick: () => take( what, 'copy' ),
				},
				copyLabel
			),
			h(
				'button',
				{ type: 'button', onClick: () => take( what, 'download' ) },
				__( 'Download', 'windrose-knotboard' )
			),
		] );

	const where = {
		header: __(
			'Where to paste it: in your Site Editor, Patterns, Template parts, Header. Open it, select all of its blocks, then paste. Or download the file and import it in Patterns (Import pattern from JSON).',
			'windrose-knotboard'
		),
		footer: __(
			'Where to paste it: in your Site Editor, Patterns, Template parts, Footer. Open it, select all of its blocks, then paste. Or download the file and import it in Patterns (Import pattern from JSON).',
			'windrose-knotboard'
		),
		content: __(
			'Where to paste it: in a new page of your site, click in the empty paragraph, then paste. Or download the file and import it in your Site Editor, Patterns (Import pattern from JSON): it is then in your inserter.',
			'windrose-knotboard'
		),
	};

	const row = ( item, index, total ) => {
		const actions = h( 'span', {
			class: 'windrose-knotboard-selection__order',
		} );
		if ( 'header' !== item.kind && 'footer' !== item.kind ) {
			actions.append(
				h(
					'button',
					{
						type: 'button',
						disabled: 0 === index,
						'aria-label': sprintf(
							/* translators: %s: pattern title */
							__( 'Move up: %s', 'windrose-knotboard' ),
							item.title
						),
						onClick: () =>
							setSelection( move( getSelection(), item.id, -1 ) ),
					},
					'↑'
				),
				h(
					'button',
					{
						type: 'button',
						disabled: index === total - 1,
						'aria-label': sprintf(
							/* translators: %s: pattern title */
							__( 'Move down: %s', 'windrose-knotboard' ),
							item.title
						),
						onClick: () =>
							setSelection( move( getSelection(), item.id, 1 ) ),
					},
					'↓'
				)
			);
		}
		actions.append(
			h(
				'button',
				{
					type: 'button',
					'aria-label': sprintf(
						/* translators: %s: pattern title */
						__( 'Remove: %s', 'windrose-knotboard' ),
						item.title
					),
					onClick: () => {
						setSelection( remove( getSelection(), item.id ) );
						say(
							sprintf(
								/* translators: %s: pattern title */
								__(
									'%s left your selection.',
									'windrose-knotboard'
								),
								item.title
							),
							status
						);
					},
				},
				'×'
			)
		);
		return h( 'li', { class: 'windrose-knotboard-selection__row' }, [
			h(
				'span',
				{
					class: 'windrose-knotboard-selection__name',
					lang: item.lang || null,
				},
				item.title
			),
			actions,
		] );
	};

	const library = () =>
		h(
			'a',
			{ href: config.library },
			__( 'Choose one in the library', 'windrose-knotboard' )
		);

	const part = ( kind, title, copyLabel ) => {
		const item = getSelection()[ kind ];
		// The library has no header (or footer) to offer, and none was chosen: nothing to propose.
		if ( ! item && false === config.parts?.[ kind ] ) {
			return '';
		}
		return h( 'section', { class: 'windrose-knotboard-selection__part' }, [
			h( 'h3', {}, title ),
			item
				? h( 'ul', {}, [ row( item, 0, 1 ) ] )
				: h( 'p', {}, [
						__( 'None chosen.', 'windrose-knotboard' ),
						' ',
						library(),
					] ),
			...( item
				? [
						buttons( kind, copyLabel ),
						h(
							'p',
							{ class: 'windrose-knotboard-selection__where' },
							where[ kind ]
						),
						picturesNote( [ item ], kind ),
					]
				: [] ),
		] );
	};

	const render = () => {
		const selection = getSelection();
		lists.textContent = '';
		if ( 0 === count( selection ) ) {
			lists.append(
				h( 'p', {}, [
					__( 'Your selection is empty.', 'windrose-knotboard' ),
					' ',
					h(
						'a',
						{ href: config.library },
						__(
							'Choose patterns in the library',
							'windrose-knotboard'
						)
					),
				] )
			);
			preview.hidden = true;
			return;
		}
		preview.hidden = false;
		const parts = [
			part(
				'header',
				__( 'Header', 'windrose-knotboard' ),
				__( 'Copy header', 'windrose-knotboard' )
			),
			part(
				'footer',
				__( 'Footer', 'windrose-knotboard' ),
				__( 'Copy footer', 'windrose-knotboard' )
			),
		].filter( Boolean );
		if ( parts.length ) {
			lists.append(
				h( 'div', { class: 'windrose-knotboard-selection__parts' }, [
					h( 'h2', {}, __( 'Template parts', 'windrose-knotboard' ) ),
					...parts,
				] )
			);
		}
		lists.append(
			h( 'div', { class: 'windrose-knotboard-selection__content' }, [
				h( 'h2', {}, __( 'Content', 'windrose-knotboard' ) ),
				selection.content.length
					? h(
							'ol',
							{},
							selection.content.map( ( item, i ) =>
								row( item, i, selection.content.length )
							)
						)
					: h( 'p', {}, [
							__( 'None chosen.', 'windrose-knotboard' ),
							' ',
							library(),
						] ),
				...( selection.content.length
					? [
							buttons(
								'content',
								__( 'Copy content', 'windrose-knotboard' )
							),
							h(
								'p',
								{
									class: 'windrose-knotboard-selection__where',
								},
								where.content
							),
							picturesNote( selection.content, 'content' ),
						]
					: [] ),
			] )
		);
		redraw( selection );
	};

	const redraw = async ( selection ) => {
		const ids = pageOrder( selection ).map( ( item ) => item.id );
		const ticket = ++drawing;
		try {
			// As a list (one part per pattern), then put together: each pattern keeps its own direction.
			const drawn = split( await draw( ids, 'list', currentStyle() ) );
			if ( ticket !== drawing ) {
				return;
			}
			const inner = pageOrder( selection )
				.map(
					( item ) =>
						htmlFor( drawn, item.id, item.lang, item.dir ) ?? ''
				)
				.join( '' );
			fill( frameOf( preview ), documentFor( drawn, inner ), () =>
				fitFull( preview )
			);
		} catch ( e ) {
			previewMessage( preview, e.message );
		}
	};

	const load = async () => {
		const selection = getSelection();
		const ids = pageOrder( selection ).map( ( item ) => item.id );
		if ( ids.length ) {
			try {
				const response = await window.fetch(
					`${ config.rest }/copy?ids=${ ids.join( ',' ) }`,
					{ credentials: 'omit' }
				);
				const data = response.ok
					? await response.json()
					: { patterns: [] };
				const offered = data.patterns ?? [];
				markup = new Map(
					offered.map( ( p ) => [ Number( p.id ), p.content ] )
				);
				pictures = new Set(
					offered
						.filter( ( p ) => p.pictures )
						.map( ( p ) => Number( p.id ) )
				);
				const fresh = refresh( selection, offered );
				if ( count( fresh ) !== count( selection ) ) {
					say(
						__(
							'Some patterns are no longer offered and left your selection.',
							'windrose-knotboard'
						),
						status
					);
				}
				setSelection( fresh );
				return;
			} catch {}
		}
		render();
	};

	root.textContent = '';
	root.append( lists, status, preview );
	document.addEventListener( 'windrose-knotboard-selection', render );
	document.addEventListener( 'windrose-knotboard-style', () =>
		redraw( getSelection() )
	);
	load();
}

/* ---------- Style picker ---------- */

/**
 * Shows the chosen style on every picker of the page.
 *
 * @param {string} slug Style.
 */
function showStyle( slug ) {
	document
		.querySelectorAll( '[data-windrose-knotboard-style]' )
		.forEach( ( picker ) => {
			const radio =
				picker.querySelector( `input[value="${ slug }"]` ) ||
				picker.querySelector( 'input' );
			if ( ! radio ) {
				return;
			}
			radio.checked = true;
			picker.querySelector(
				'.windrose-knotboard-style__current'
			).textContent = radio.dataset.title;
			const dots = picker.querySelector(
				'.windrose-knotboard-style__toggle .windrose-knotboard-style__dots'
			);
			dots.textContent = '';
			radio.dataset.colors
				.split( ',' )
				.filter( Boolean )
				.forEach( ( color ) =>
					dots.append(
						h( 'span', { style: `background:${ color }` } )
					)
				);
		} );
}

// Ipsum's six colors, in its order (core r65224: Twenty Twenty-Five's slugs).
const ROLES = [
	'base',
	'contrast',
	'accent-1',
	'accent-3',
	'accent-5',
	'accent-6',
];

/**
 * The six colors of a recipe, in Ipsum's order.
 *
 * @param {Object} style Recipe.
 * @return {string[]} Colors.
 */
function colorsOf( style ) {
	const palette = style?.settings?.color?.palette ?? [];
	return ROLES.map(
		( slug ) => palette.find( ( c ) => c.slug === slug )?.color
	).filter( ( color ) => /^#[0-9a-fA-F]{3,8}$/.test( color ?? '' ) );
}

/**
 * "My style (from Style Mermaid)" first in a picker's list, while Style Mermaid keeps one in this browser.
 *
 * @param {HTMLElement} picker The block.
 */
function mermaidOption( picker ) {
	const list = picker.querySelector( '.windrose-knotboard-style__list' );
	const made = mermaidStyle();
	let option = list.querySelector( `input[value="${ MERMAID }"]` );
	if ( ! made ) {
		option?.closest( 'label' ).remove();
		return;
	}
	const title = sprintf(
		/* translators: %s: the name of the style the visitor is making in Style Mermaid */
		__( '%s (from Style Mermaid)', 'windrose-knotboard' ),
		made.title
	);
	if ( ! option ) {
		const first = list.querySelector( 'input' );
		option = h( 'input', {
			type: 'radio',
			name: first?.name ?? 'style',
			value: MERMAID,
		} );
		list.querySelector( 'legend' ).after(
			h(
				'label',
				{ class: 'windrose-knotboard-style__option is-mermaid' },
				[
					option,
					h( 'span', { class: 'windrose-knotboard-style__name' } ),
					h( 'span', {
						class: 'windrose-knotboard-style__dots',
						'aria-hidden': 'true',
					} ),
				]
			)
		);
	}
	const label = option.closest( 'label' );
	option.dataset.title = title;
	option.dataset.colors = colorsOf( made.recipe ).join( ',' );
	label.querySelector( '.windrose-knotboard-style__name' ).textContent =
		title;
	const dots = label.querySelector( '.windrose-knotboard-style__dots' );
	dots.textContent = '';
	colorsOf( made.recipe ).forEach( ( color ) =>
		dots.append( h( 'span', { style: `background:${ color }` } ) )
	);
}

/**
 * A style picker: a button opening the list of styles (radio buttons with their colors).
 *
 * @param {HTMLElement} picker The block.
 */
function setupStylePicker( picker ) {
	mermaidOption( picker );
	const button = picker.querySelector( '.windrose-knotboard-style__toggle' );
	const list = picker.querySelector( '.windrose-knotboard-style__list' );
	const open = ( yes ) => {
		list.hidden = ! yes;
		button.setAttribute( 'aria-expanded', yes ? 'true' : 'false' );
		if ( yes ) {
			(
				list.querySelector( 'input:checked' ) ||
				list.querySelector( 'input' )
			)?.focus();
		}
	};
	button.addEventListener( 'click', () => open( list.hidden ) );
	list.addEventListener( 'change', ( event ) => {
		const slug = event.target.value;
		keepStyle( slug );
		showStyle( slug );
		document.dispatchEvent(
			new window.CustomEvent( 'windrose-knotboard-style' )
		);
		speak(
			sprintf(
				/* translators: %s: style name */
				__( 'Previews in the style %s.', 'windrose-knotboard' ),
				event.target.dataset.title
			)
		);
	} );
	// Enter or a click on a style closes the list; Escape closes it too.
	list.addEventListener( 'click', ( event ) => {
		if ( event.target.closest( 'label' ) && 0 !== event.detail ) {
			window.setTimeout( () => {
				open( false );
				button.focus();
			}, 0 );
		}
	} );
	picker.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key && ! list.hidden ) {
			open( false );
			button.focus();
		}
		if ( 'Enter' === event.key && event.target.matches( 'input' ) ) {
			event.preventDefault();
			open( false );
			button.focus();
		}
	} );
	document.addEventListener( 'click', ( event ) => {
		if ( ! list.hidden && ! picker.contains( event.target ) ) {
			open( false );
		}
	} );
	showStyle( currentStyle() );
}

/* ---------- Filter menus ---------- */

/**
 * A filter's menu (a native disclosure with checkboxes): its button counts the ticked values, one menu open at a
 * time, Escape or a click outside closes it, and long lists get a field to find a value.
 *
 * @param {HTMLDetailsElement} menu The menu.
 */
function setupFilterMenu( menu ) {
	const summary = menu.querySelector( 'summary' );
	const boxes = [ ...menu.querySelectorAll( 'input[type="checkbox"]' ) ];
	const showCount = () => {
		const n = boxes.filter( ( box ) => box.checked ).length;
		summary.textContent = n
			? sprintf(
					/* translators: 1: a filter's name (Tags), 2: how many values are chosen */
					__( '%1$s (%2$d)', 'windrose-knotboard' ),
					menu.dataset.name,
					n
				)
			: menu.dataset.name;
	};
	boxes.forEach( ( box ) => box.addEventListener( 'change', showCount ) );
	if ( boxes.length > 8 ) {
		const list = menu.querySelector( 'fieldset' );
		const find = h( 'input', {
			type: 'search',
			class: 'windrose-knotboard-filter__find',
			'aria-label': sprintf(
				/* translators: %s: a filter's name (Tags) */
				__( 'Find in %s', 'windrose-knotboard' ),
				menu.dataset.name
			),
			placeholder: __( 'Find…', 'windrose-knotboard' ),
		} );
		find.addEventListener( 'input', () => {
			const words = find.value.trim().toLowerCase();
			boxes.forEach( ( box ) => {
				box.closest( 'label' ).hidden =
					'' !== words &&
					! box
						.closest( 'label' )
						.textContent.toLowerCase()
						.includes( words );
			} );
		} );
		// Enter in the field must not send the form before the visitor ticked anything.
		find.addEventListener( 'keydown', ( event ) => {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
			}
		} );
		list.querySelector( 'legend' ).after( find );
	}
	menu.addEventListener( 'toggle', () => {
		if ( menu.open ) {
			document
				.querySelectorAll( '.windrose-knotboard-filter__menu[open]' )
				.forEach( ( other ) => {
					if ( other !== menu ) {
						other.open = false;
					}
				} );
		}
	} );
	menu.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key && menu.open ) {
			menu.open = false;
			summary.focus();
		}
	} );
	document.addEventListener( 'click', ( event ) => {
		if ( menu.open && ! menu.contains( event.target ) ) {
			menu.open = false;
		}
	} );
}

/* ---------- Start ---------- */

function start() {
	// Style Mermaid's "See the patterns in this style" opens the library with this mark: use that style.
	if (
		'#windrose-livery-recipe' === window.location.hash &&
		mermaidStyle()
	) {
		keepStyle( MERMAID );
		window.history.replaceState(
			null,
			'',
			window.location.pathname + window.location.search
		);
	}
	document
		.querySelectorAll( '[data-windrose-knotboard-style]' )
		.forEach( setupStylePicker );
	document
		.querySelectorAll( '.windrose-knotboard-filter__menu' )
		.forEach( setupFilterMenu );
	document
		.querySelectorAll( '.windrose-knotboard-preview.is-full [data-size]' )
		.forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const block = button.closest( '.windrose-knotboard-preview' );
				block.dataset.size = button.dataset.size;
				block
					.querySelectorAll( '[data-size]' )
					.forEach( ( b ) =>
						b.setAttribute(
							'aria-pressed',
							b === button ? 'true' : 'false'
						)
					);
				fitFull( block );
			} );
		} );
	document.addEventListener( 'click', onAction );
	document
		.querySelectorAll( '.windrose-knotboard-bar [data-action="clear"]' )
		.forEach( ( button ) =>
			button.addEventListener( 'click', () => {
				// Two clicks: the first asks, the second clears (no dialog box).
				if ( 'true' !== button.dataset.asking ) {
					const label = button.textContent;
					button.dataset.asking = 'true';
					button.textContent = __(
						'Click again to clear',
						'windrose-knotboard'
					);
					speak(
						__(
							'Click again to remove every pattern from your selection.',
							'windrose-knotboard'
						)
					);
					window.setTimeout( () => {
						button.dataset.asking = '';
						button.textContent = label;
					}, 4000 );
					return;
				}
				button.dataset.asking = '';
				button.textContent = __( 'Clear', 'windrose-knotboard' );
				setSelection( readSelection( null ) );
				speak( __( 'Your selection is empty.', 'windrose-knotboard' ) );
			} )
		);
	document.addEventListener( 'windrose-knotboard-selection', () => {
		syncButtons();
		syncBars();
	} );
	document.addEventListener( 'windrose-knotboard-style', drawPreviews );
	// Another tab changed the selection (the selection page opens in a new tab), or Style Mermaid the style.
	window.addEventListener( 'storage', ( event ) => {
		if ( MERMAID_KEY === event.key ) {
			document
				.querySelectorAll( '[data-windrose-knotboard-style]' )
				.forEach( mermaidOption );
			showStyle( currentStyle() );
			if ( MERMAID === currentStyle() ) {
				document.dispatchEvent(
					new window.CustomEvent( 'windrose-knotboard-style' )
				);
			}
		}
		if ( SELECTION_KEY === event.key ) {
			document.dispatchEvent(
				new window.CustomEvent( 'windrose-knotboard-selection' )
			);
		}
	} );
	let resize = 0;
	window.addEventListener( 'resize', () => {
		window.clearTimeout( resize );
		resize = window.setTimeout( () => {
			document
				.querySelectorAll( '.windrose-knotboard-preview.is-thumb' )
				.forEach( fitThumb );
			document
				.querySelectorAll( '.windrose-knotboard-preview.is-full' )
				.forEach( fitFull );
		}, 150 );
	} );
	document
		.querySelectorAll( '[data-windrose-knotboard-selection]' )
		.forEach( mountSelection );
	syncButtons();
	syncBars();
	drawPreviews();
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
