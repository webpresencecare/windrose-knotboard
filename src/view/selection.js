/**
 * The visitor's selection (plain functions, tested with node --test): at most one header and one footer (template
 * parts) and any number of sections and pages (content), in the visitor's order. Each entry keeps what the selection
 * page needs ({id, kind, title, lang}). Kept in the visitor's browser only.
 */

const PARTS = [ 'header', 'footer' ];
const CONTENT = [ 'section', 'page' ];

/**
 * An empty selection.
 *
 * @return {{header: Object|null, footer: Object|null, content: Object[]}} Selection.
 */
export function emptySelection() {
	return { header: null, footer: null, content: [] };
}

/**
 * A clean entry, or null.
 *
 * @param {Object} item What was stored (checked here).
 * @return {Object|null} Entry.
 */
export function entry( item ) {
	if ( ! item || 'object' !== typeof item ) {
		return null;
	}
	const id = Number( item.id );
	const kind = String( item.kind ?? '' );
	if (
		! Number.isInteger( id ) ||
		id <= 0 ||
		! [ ...PARTS, ...CONTENT ].includes( kind )
	) {
		return null;
	}
	return {
		id,
		kind,
		title: String( item.title ?? '' ),
		lang: String( item.lang ?? '' ),
		dir: 'rtl' === item.dir ? 'rtl' : '',
	};
}

/**
 * A selection read from storage (anything wrong is left out).
 *
 * @param {string|null} json Stored JSON.
 * @return {Object} Selection.
 */
export function readSelection( json ) {
	const selection = emptySelection();
	let data = null;
	try {
		data = JSON.parse( json ?? 'null' );
	} catch {
		return selection;
	}
	if ( ! data || 'object' !== typeof data ) {
		return selection;
	}
	PARTS.forEach( ( kind ) => {
		const item = entry( data[ kind ] );
		if ( item && item.kind === kind ) {
			selection[ kind ] = item;
		}
	} );
	if ( Array.isArray( data.content ) ) {
		const seen = new Set();
		data.content.forEach( ( raw ) => {
			const item = entry( raw );
			if (
				item &&
				CONTENT.includes( item.kind ) &&
				! seen.has( item.id )
			) {
				seen.add( item.id );
				selection.content.push( item );
			}
		} );
	}
	return selection;
}

/**
 * Whether a pattern is in the selection.
 *
 * @param {Object} selection Selection.
 * @param {number} id        Pattern ID.
 * @return {boolean} In it.
 */
export function isSelected( selection, id ) {
	return (
		selection.header?.id === id ||
		selection.footer?.id === id ||
		selection.content.some( ( item ) => item.id === id )
	);
}

/**
 * Adds or removes a pattern: a header or footer replaces the one chosen before; content is added at the end.
 *
 * @param {Object} selection Selection.
 * @param {Object} raw       Pattern ({id, kind, title, lang}).
 * @return {Object} New selection.
 */
export function toggle( selection, raw ) {
	const item = entry( raw );
	if ( ! item ) {
		return selection;
	}
	if ( PARTS.includes( item.kind ) ) {
		return {
			...selection,
			[ item.kind ]: selection[ item.kind ]?.id === item.id ? null : item,
		};
	}
	return {
		...selection,
		content: selection.content.some( ( c ) => c.id === item.id )
			? selection.content.filter( ( c ) => c.id !== item.id )
			: [ ...selection.content, item ],
	};
}

/**
 * Removes a pattern wherever it is.
 *
 * @param {Object} selection Selection.
 * @param {number} id        Pattern ID.
 * @return {Object} New selection.
 */
export function remove( selection, id ) {
	return {
		header: selection.header?.id === id ? null : selection.header,
		footer: selection.footer?.id === id ? null : selection.footer,
		content: selection.content.filter( ( item ) => item.id !== id ),
	};
}

/**
 * Moves a content pattern up (-1) or down (+1).
 *
 * @param {Object} selection Selection.
 * @param {number} id        Pattern ID.
 * @param {number} delta     -1 or +1.
 * @return {Object} New selection.
 */
export function move( selection, id, delta ) {
	const list = [ ...selection.content ];
	const from = list.findIndex( ( item ) => item.id === id );
	const to = from + delta;
	if ( from < 0 || to < 0 || to >= list.length ) {
		return selection;
	}
	const [ item ] = list.splice( from, 1 );
	list.splice( to, 0, item );
	return { ...selection, content: list };
}

/**
 * Keeps only the patterns still offered, with their current title, kind and language.
 *
 * @param {Object}   selection Selection.
 * @param {Object[]} offered   Patterns the site still offers ({id, kind, title, lang, dir}).
 * @return {Object} New selection.
 */
export function refresh( selection, offered ) {
	const fresh = new Map(
		offered.map( ( item ) => [ Number( item.id ), entry( item ) ] )
	);
	const keep = ( item, kinds ) => {
		const now = item ? fresh.get( item.id ) : null;
		return now && kinds.includes( now.kind ) ? now : null;
	};
	return {
		header: keep( selection.header, [ 'header' ] ),
		footer: keep( selection.footer, [ 'footer' ] ),
		content: selection.content
			.map( ( item ) => keep( item, CONTENT ) )
			.filter( Boolean ),
	};
}

/**
 * The selection as one page, top to bottom: header, content, footer.
 *
 * @param {Object} selection Selection.
 * @return {Object[]} Entries.
 */
export function pageOrder( selection ) {
	return [ selection.header, ...selection.content, selection.footer ].filter(
		Boolean
	);
}

/**
 * How many patterns the selection holds.
 *
 * @param {Object} selection Selection.
 * @return {number} Count.
 */
export function count( selection ) {
	return pageOrder( selection ).length;
}

/**
 * A pattern file as core's Site Editor imports it (Patterns, Import pattern from JSON): one pattern, not synced.
 *
 * @param {string} title   Title.
 * @param {string} content Block markup.
 * @return {Object} File data.
 */
export function patternFile( title, content ) {
	return {
		__file: 'wp_block',
		title,
		content,
		syncStatus: 'unsynced',
	};
}

/**
 * A file name from a title: lower case letters, digits and hyphens.
 *
 * @param {string} title    Title.
 * @param {string} fallback When nothing is left.
 * @return {string} Name with .json.
 */
export function fileName( title, fallback = 'pattern' ) {
	const base = String( title )
		.normalize( 'NFKD' )
		.replace( /[̀-ͯ]/g, '' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' )
		.slice( 0, 60 );
	return ( base || fallback ) + '.json';
}
