/**
 * Previews: Windrose Livery draws the patterns with the chosen style on this site (one request for all the
 * thumbnails of a page, as a list with a label before each pattern); the page is split here into one small document
 * per pattern, shown in frames that run no scripts (`sandbox="allow-same-origin"`, `srcdoc`).
 */

const config = window.windroseKnotboard || {};
const STYLE_KEY = 'windrose-knotboard-style';
const recipes = new Map();

/** The style Style Mermaid is making (Windrose Livery keeps it in this browser: { title, recipe, at }). */
export const MERMAID = 'style-mermaid';
export const MERMAID_KEY = 'windrose-livery-recipe';

/**
 * The style being made in Style Mermaid, or null.
 *
 * @return {{title: string, recipe: Object}|null} Style.
 */
export function mermaidStyle() {
	try {
		const data = JSON.parse(
			window.localStorage.getItem( MERMAID_KEY ) ?? 'null'
		);
		if ( data?.recipe?.settings && 'string' === typeof data.title ) {
			return data;
		}
	} catch {}
	return null;
}

/**
 * The chosen style's slug.
 *
 * @return {string} Slug.
 */
export function currentStyle() {
	try {
		return window.localStorage.getItem( STYLE_KEY ) || config.style;
	} catch {
		return config.style;
	}
}

/**
 * Keeps the chosen style.
 *
 * @param {string} slug Slug.
 */
export function keepStyle( slug ) {
	try {
		window.localStorage.setItem( STYLE_KEY, slug );
	} catch {}
}

/**
 * A ready style's recipe (asked once per page).
 *
 * @param {string} slug Slug.
 * @return {Promise<Object>} Recipe.
 */
export function recipe( slug ) {
	if ( MERMAID === slug ) {
		const made = mermaidStyle();
		return made
			? Promise.resolve( made.recipe )
			: Promise.reject( new Error( 'style' ) );
	}
	if ( ! recipes.has( slug ) ) {
		recipes.set(
			slug,
			window
				.fetch(
					`${ config.rest }/styles/${ encodeURIComponent( slug ) }`,
					{
						credentials: 'omit',
					}
				)
				.then( ( response ) => {
					if ( ! response.ok ) {
						throw new Error( 'style' );
					}
					return response.json();
				} )
				.then( ( data ) => data.recipe )
		);
	}
	return recipes.get( slug );
}

/**
 * Livery's page of patterns.
 *
 * @param {number[]} ids    Pattern IDs, in order.
 * @param {string}   layout list (a label before each) or page.
 * @param {string}   slug   Style.
 * @return {Promise<string>} HTML.
 */
export async function draw( ids, layout, slug ) {
	let style;
	try {
		style = await recipe( slug );
	} catch {
		style = await recipe( config.style );
	}
	const body = new window.FormData();
	body.append( 'recipe', JSON.stringify( style ) );
	body.append( 'source', config.source );
	body.append( 'items', ids.join( ',' ) );
	body.append( 'layout', layout );
	const response = await window.fetch( config.render, {
		method: 'POST',
		body,
		credentials: 'omit',
	} );
	const text = await response.text();
	if ( ! response.ok ) {
		let message = '';
		try {
			message = JSON.parse( text )?.data?.message ?? '';
		} catch {}
		throw new Error( message || String( response.status ) );
	}
	return text;
}

/**
 * The parts of Livery's page: its styles, the body's classes, and the elements of each pattern by ID.
 *
 * @param {string} html Livery's page (layout list: a label before each pattern).
 * @return {{styles: string, bodyClass: string, parts: Map<number, Element[]>}} Parts.
 */
export function split( html ) {
	const doc = new window.DOMParser().parseFromString( html, 'text/html' );
	const styles = [
		...doc.querySelectorAll( 'style, link[rel="stylesheet"]' ),
	]
		.map( ( node ) => node.outerHTML )
		.join( '\n' );
	const parts = new Map();
	const root = doc.querySelector( '.wp-site-blocks' ) || doc.body;
	let current = null;
	[ ...root.children ].forEach( ( node ) => {
		const name = node.getAttribute( 'data-windrose-livery-pattern' );
		if ( name ) {
			current = Number( name.split( '/' ).pop() );
			parts.set( current, [] );
		} else if ( null !== current ) {
			parts.get( current ).push( node );
		}
	} );
	return { styles, bodyClass: doc.body.className, parts };
}

/**
 * A pattern's HTML, its top elements marked with its language and direction (so a right-to-left pattern stays right
 * to left next to others).
 *
 * @param {Object} drawn split()'s result.
 * @param {number} id    Pattern ID.
 * @param {string} lang  Language code.
 * @param {string} dir   rtl or ''.
 * @return {string|null} HTML, or null when Livery did not draw it.
 */
export function htmlFor( drawn, id, lang = '', dir = '' ) {
	const nodes = drawn.parts.get( id );
	if ( ! nodes ) {
		return null;
	}
	return nodes
		.map( ( node ) => {
			const copy = node.cloneNode( true );
			if ( lang ) {
				copy.setAttribute( 'lang', lang );
				copy.setAttribute( 'dir', 'rtl' === dir ? 'rtl' : 'ltr' );
			}
			return copy.outerHTML;
		} )
		.join( '' );
}

/**
 * Core's Media & Text sets `direction: ltr`, which only its right-to-left stylesheet changes, and the library's site
 * may be written left to right: a right-to-left pattern gets what an RTL site gives it (picture and text mirrored).
 */
const RTL_FIX =
	'[dir=rtl] .wp-block-media-text,[dir=rtl] .wp-block-media-text>.wp-block-media-text__content{direction:rtl}';

/**
 * A preview document.
 *
 * @param {Object} drawn split()'s result.
 * @param {string} inner Patterns' HTML.
 * @param {string} lang  Language code of the whole page ('' when mixed).
 * @param {string} dir   rtl or ''.
 * @return {string} Document.
 */
export function documentFor( drawn, inner, lang = '', dir = '' ) {
	const attrs =
		( lang ? ` lang="${ lang.replace( /[^a-zA-Z0-9-]/g, '' ) }"` : '' ) +
		( 'rtl' === dir ? ' dir="rtl"' : '' );
	return `<!DOCTYPE html><html${ attrs }><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">${ drawn.styles }<style>html,body,.wp-site-blocks{margin:0;min-height:0!important}body{overflow:hidden}${ RTL_FIX }</style></head><body class="${ drawn.bodyClass }"><div class="wp-site-blocks">${ inner }</div></body></html>`;
}

/**
 * The height of a frame's document, once it is drawn.
 *
 * @param {HTMLIFrameElement} frame Frame.
 * @return {number} Pixels.
 */
export function contentHeight( frame ) {
	const blocks = frame.contentDocument?.querySelector( '.wp-site-blocks' );
	if ( ! blocks ) {
		return 0;
	}
	// The patterns' own height: the bottom of the last of them (themes often make the page at least as tall as
	// the window, which here is the frame itself).
	const top = blocks.getBoundingClientRect().top;
	let bottom = 0;
	[ ...blocks.children ].forEach( ( child ) => {
		const rect = child.getBoundingClientRect();
		const margin = parseFloat(
			frame.contentWindow.getComputedStyle( child ).marginBottom || 0
		);
		bottom = Math.max( bottom, rect.bottom + margin - top );
	} );
	return Math.ceil( bottom );
}

/**
 * Fills a frame and keeps it fitted: `fit` runs when it loads, when its fonts are ready and a little later.
 *
 * @param {HTMLIFrameElement} frame Frame.
 * @param {string}            html  Document.
 * @param {() => void}        fit   Fits the frame.
 */
export function fill( frame, html, fit ) {
	frame.addEventListener(
		'load',
		() => {
			fit();
			frame.contentDocument?.fonts?.ready.then( fit ).catch( () => {} );
			window.setTimeout( fit, 400 );
			window.setTimeout( fit, 1500 );
		},
		{ once: true }
	);
	frame.srcdoc = html;
}
