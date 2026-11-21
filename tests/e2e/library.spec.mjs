/**
 * The pattern library (Knotboard 0.1) on a WordPress network with WP-CLI (needs Windrose Livery 0.7 network active and
 * Windrose Prow for Ipsum installed). Environment: WINDROSE_E2E_HOST (the network's host, default localhost),
 * WINDROSE_E2E_USER (a network administrator for WP-CLI, default admin).
 *
 * A throwaway site on Windrose Prow for Ipsum with seven library patterns from tests/e2e/fixtures (header, footer, hero, concert
 * list, opening hours in English and Arabic, one with pictures) with kinds, categories, tags and two languages, a draft,
 * and one of the site's own patterns (core's My patterns), which the library never shows.
 * Visitor (no cookie, only this host): the library's cards and thumbnails (the Arabic one right to left), filters and
 * search, Copy pattern, View code, Add to selection and the bar; the selection in a new tab (order, remove, Copy
 * header, footer and content with where to paste them, Download, the preview with each pattern in its own direction,
 * another style), the first tab following; a pattern's page with Wide and Phone; axe on the library and the selection.
 * The copied content opens in the block editor with valid blocks and core's image placeholders; the downloaded file
 * goes in with the Site Editor's Import pattern from JSON. Administrator: the templates in the Site Editor, the
 * address base on Settings, Permalinks, the network switch. Import (0.2): Tools, Import lists the importer; a package
 * made here goes through upload, dry run and import, its pattern shows "Translation not reviewed yet" and its photo
 * credit; a smaller package asks what happens to the pattern no longer in it. Everything is deleted at the end.
 */
import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import AxeBuilder from '@axe-core/playwright';
import { test, expect } from '@playwright/test';

const HOST = process.env.WINDROSE_E2E_HOST ?? 'localhost';
const USER = process.env.WINDROSE_E2E_USER ?? 'admin';
const ROOT = `https://${ HOST }`;
const SITE = 'library-e2e';
const SITE_TITLE = 'Real Library Site E2E';
const WORK = '/tmp/windrose-knotboard-e2e';
const FIXTURES = new URL( './fixtures/', import.meta.url );
const LIB = `${ ROOT }/${ SITE }/patterns/`;

const wpAt = ( slug, ...args ) =>
	execFileSync( 'wp', [ ...args, '--path=/var/www/html/web', `--url=${ ROOT }/${ slug ? slug + '/' : '' }` ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} ).trim();
const wp = ( ...args ) => wpAt( '', ...args );
const site = ( ...args ) => wpAt( SITE, ...args );

function deleteSite() {
	try {
		const id = wp( 'site', 'list', '--field=blog_id', `--path__in=/${ SITE }/` );
		if ( /^\d+$/.test( id ) ) {
			wp( 'site', 'delete', id, '--yes' );
			rmSync( `/var/www/html/web/wp-content/uploads/sites/${ id }`, { recursive: true, force: true } );
		}
	} catch {}
}

const ids = {};

/** Makes a library pattern from a fixture, with its terms. */
function pattern( key, file, title, excerpt, terms, status = 'publish', replace = {} ) {
	let content = readFileSync( new URL( `${ file }.html`, FIXTURES ), 'utf8' );
	Object.entries( replace ).forEach( ( [ from, to ] ) => {
		content = content.split( from ).join( to );
	} );
	const path = `${ WORK }/${ key }.html`;
	writeFileSync( path, content );
	ids[ key ] = Number(
		site( 'post', 'create', path, '--post_type=windrose_knotboard', `--post_status=${ status }`, `--post_title=${ title }`, `--post_excerpt=${ excerpt }`, '--porcelain', `--user=${ USER }` )
	);
	Object.entries( terms ).forEach( ( [ taxonomy, list ] ) => site( 'post', 'term', 'set', String( ids[ key ] ), `windrose_knotboard_${ taxonomy }`, ...list ) );
}

test.describe.configure( { mode: 'serial' } );

test.beforeAll( () => {
	mkdirSync( WORK, { recursive: true } );
	deleteSite();
	wp( 'site', 'create', `--slug=${ SITE }`, `--title=${ SITE_TITLE }`, `--email=${ USER }@example.com` );
	site( 'theme', 'activate', 'windrose-prow-ipsum' );
	// The site's own language (English) is made by Knotboard on the site's first request.
	expect( site( 'term', 'list', 'windrose_knotboard_language', '--field=slug' ) ).toBe( 'en' );
	site( 'term', 'create', 'windrose_knotboard_language', 'العربية', '--slug=ar' );
	site( 'eval', `$i = imagecreatetruecolor( 800, 500 ); imagefill( $i, 0, 0, imagecolorallocate( $i, 40, 90, 160 ) ); imagepng( $i, '${ WORK }/photo.png' );` );
	const media = Number( site( 'media', 'import', `${ WORK }/photo.png`, '--porcelain', `--user=${ USER }` ) );
	const url = site( 'post', 'get', String( media ), '--field=guid' );
	pattern( 'header', 'header', 'Header with button E2E', 'Site title, menu and a button.', { kind: [ 'header' ], language: [ 'en' ] } );
	pattern( 'footer', 'footer', 'Dark footer E2E', 'Title and a line.', { kind: [ 'footer' ], language: [ 'en' ] } );
	pattern( 'hero', 'hero', 'Hero with rating E2E', 'A promise and a review.', { kind: [ 'section' ], category: [ 'Heroes' ], tag: [ 'business' ], language: [ 'en' ] } );
	pattern( 'concerts', 'concerts', 'Concert list E2E', 'Dates and tickets.', { kind: [ 'section' ], category: [ 'Events' ], tag: [ 'music' ], language: [ 'en' ] } );
	pattern( 'hours', 'hours', 'Opening hours E2E', 'Address and hours.', { kind: [ 'section' ], category: [ 'Contact' ], tag: [ 'restaurant' ], language: [ 'en' ] } );
	pattern( 'hoursAr', 'hours-ar', 'ساعات العمل E2E', 'العنوان والساعات.', { kind: [ 'section' ], category: [ 'Contact' ], tag: [ 'restaurant' ], language: [ 'ar' ] } );
	pattern( 'pictures', 'pictures', 'Pictures E2E', 'Image, media and text, cover.', { kind: [ 'section' ], category: [ 'Media' ], language: [ 'en' ] }, 'publish', { '{{ID}}': String( media ), '{{URL}}': url } );
	pattern( 'draft', 'hero', 'Draft E2E', 'Not offered.', { kind: [ 'section' ], language: [ 'en' ] }, 'draft' );
	site( 'post', 'create', '--post_type=wp_block', '--post_status=publish', '--post_title=Site own pattern E2E', '--post_content=<!-- wp:paragraph --><p>Own</p><!-- /wp:paragraph -->' );
	site( 'rewrite', 'flush' );
} );

test.afterAll( () => {
	deleteSite();
	rmSync( WORK, { recursive: true, force: true } );
	try {
		wp( 'site', 'option', 'delete', 'windrose_knotboard_network' );
	} catch {}
} );

const visitorContext = async ( browser ) => {
	const context = await browser.newContext( { storageState: { cookies: [], origins: [] }, ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 } } );
	await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ], { origin: ROOT } );
	return context;
};
const card = ( page, title ) => page.locator( '.windrose-knotboard-card' ).filter( { hasText: title } );
const clipboard = ( page ) => page.evaluate( () => window.navigator.clipboard.readText() );
const frameDoc = ( locator ) => locator.locator( 'iframe' ).evaluate( ( f ) => f.contentDocument?.body?.innerText ?? '' ).catch( () => '' );

test( 'visitor: the library, filters, copy, code, selection in a new tab, a pattern’s page', async ( { browser } ) => {
	test.setTimeout( 240000 );
	const context = await visitorContext( browser );
	const hosts = new Set();
	context.on( 'request', ( r ) => {
		if ( ! /^(data|blob|about):/.test( r.url() ) ) {
			hosts.add( new URL( r.url() ).host );
		}
	} );
	const v = await context.newPage();
	await v.goto( LIB );

	// Cards: the seven offered patterns, never the draft or the site's own pattern; thumbnails drawn.
	await expect( v.locator( '.windrose-knotboard-card' ) ).toHaveCount( 7 );
	await expect( v.locator( 'main' ) ).not.toContainText( 'Draft E2E' );
	await expect( v.locator( 'main' ) ).not.toContainText( 'Site own pattern E2E' );
	await expect.poll( () => frameDoc( card( v, 'Concert list E2E' ) ), { timeout: 30000 } ).toContain( 'On tour' );
	expect( await frameDoc( card( v, 'Concert list E2E' ) ) ).not.toContain( SITE_TITLE );
	await expect.poll( () => card( v, 'ساعات العمل E2E' ).locator( 'iframe' ).evaluate( ( f ) => f.contentDocument?.documentElement?.dir ), { timeout: 30000 } ).toBe( 'rtl' );
	await v.screenshot( { path: test.info().outputPath( 'library.png' ), fullPage: true } );

	// Filters: buttons opening checkboxes (several at once: OR inside a filter, AND between filters), one Filter
	// button, the active choices as chips. Nothing changes before Filter.
	const filters = v.locator( 'form.windrose-knotboard-filters' );
	const menu = ( name ) => filters.locator( 'details', { has: v.locator( 'summary', { hasText: name } ) } );
	await menu( 'Kind' ).locator( 'summary' ).click();
	await menu( 'Kind' ).getByRole( 'checkbox', { name: /Headers/ } ).check();
	await menu( 'Kind' ).getByRole( 'checkbox', { name: /Footers/ } ).check();
	await expect( menu( 'Kind' ).locator( 'summary' ) ).toHaveText( 'Kind (2)' );
	await expect( v.locator( '.windrose-knotboard-card' ) ).toHaveCount( 7 );
	await filters.getByRole( 'button', { name: 'Filter' } ).click();
	await expect( v.locator( '.windrose-knotboard-card' ) ).toHaveCount( 2 );
	await v.getByRole( 'link', { name: 'Remove the filter Footers' } ).click();
	await expect( v.locator( '.windrose-knotboard-card' ) ).toHaveCount( 1 );
	await v.getByRole( 'link', { name: 'Clear filters' } ).click();
	await expect( v.locator( '.windrose-knotboard-card' ) ).toHaveCount( 7 );

	// Tags restaurant OR music (3 patterns), and Language English: the Arabic restaurant pattern leaves (2).
	await menu( 'Tags' ).locator( 'summary' ).click();
	await menu( 'Tags' ).getByRole( 'checkbox', { name: /restaurant/ } ).check();
	await menu( 'Tags' ).getByRole( 'checkbox', { name: /music/ } ).check();
	await filters.getByRole( 'button', { name: 'Filter' } ).click();
	await expect( v.locator( '.windrose-knotboard-card' ) ).toHaveCount( 3 );
	await menu( 'Language' ).locator( 'summary' ).click();
	await menu( 'Language' ).getByRole( 'checkbox', { name: /English/ } ).check();
	await filters.getByRole( 'button', { name: 'Filter' } ).click();
	await expect( v.locator( '.windrose-knotboard-card' ) ).toHaveCount( 2 );
	await expect( menu( 'Tags' ).locator( 'summary' ) ).toHaveText( 'Tags (2)' );
	await expect( v.locator( '.windrose-knotboard-filters__active a' ) ).toHaveText( [ /music/, /restaurant/, /English/, 'Clear filters' ] );
	await v.getByRole( 'link', { name: 'Clear filters' } ).click();

	// The same with the keyboard only: Tab to the menu, Enter opens it, Space ticks, Escape closes, Enter sends.
	await filters.getByPlaceholder( 'Search patterns' ).fill( 'hours' );
	await filters.getByPlaceholder( 'Search patterns' ).focus();
	let tabs = 0;
	while ( ! ( await menu( 'Tags' ).locator( 'summary' ).evaluate( ( e ) => e === document.activeElement ) ) && tabs++ < 10 ) {
		await v.keyboard.press( 'Tab' );
	}
	await v.keyboard.press( 'Enter' );
	await expect( menu( 'Tags' ) ).toHaveAttribute( 'open', '' );
	await v.keyboard.press( 'Tab' );
	await v.keyboard.press( 'Space' );
	await expect( menu( 'Tags' ).getByRole( 'checkbox' ).first() ).toBeChecked();
	await v.keyboard.press( 'Escape' );
	await expect( menu( 'Tags' ) ).not.toHaveAttribute( 'open', '' );
	await expect( menu( 'Tags' ).locator( 'summary' ) ).toBeFocused();
	await filters.getByRole( 'button', { name: 'Filter' } ).press( 'Enter' );
	await expect( v ).toHaveURL( /windrose_knotboard_search=hours/ );
	await v.goto( LIB );

	// The style picker: a list of styles with their colors; Escape closes it.
	const picker = v.locator( '.windrose-knotboard-style' ).first();
	await picker.getByRole( 'button', { name: /Preview style/ } ).click();
	await expect( picker.getByRole( 'radio', { name: 'Ipsum' } ) ).toBeChecked();
	await expect( picker.getByRole( 'radio', { name: 'Corner Café' } ).locator( '..' ).locator( '.windrose-knotboard-style__dots span' ) ).toHaveCount( 6 );
	await v.keyboard.press( 'Escape' );
	await expect( picker.getByRole( 'group', { name: 'Preview style' } ) ).toBeHidden();

	// Copy pattern: straight to the clipboard, pictures kept but detached from the library's attachments, and a word
	// on uploading them.
	await card( v, 'Pictures E2E' ).getByRole( 'button', { name: 'Copy pattern' } ).click();
	await expect( card( v, 'Pictures E2E' ).getByRole( 'button', { name: 'Copied' } ) ).toBeVisible();
	await expect( v.locator( '#a11y-speak-polite' ) ).toContainText( 'pictures still come from this library' );
	const copied = await clipboard( v );
	expect( copied ).toContain( 'Media and text E2E' );
	expect( copied ).toContain( `${ ROOT }/${ SITE }/wp-content/uploads/` );
	expect( copied ).not.toMatch( /wp-image-\d|"id":\d|"mediaId":\d/ );
	writeFileSync( `${ WORK }/copied.html`, copied );

	// View code: a dialog with the same markup.
	await card( v, 'Concert list E2E' ).getByRole( 'button', { name: 'View code' } ).click();
	const dialog = v.getByRole( 'dialog', { name: 'Concert list E2E' } );
	await expect( dialog ).toBeVisible();
	await expect( dialog.getByRole( 'textbox' ) ).toHaveValue( /On tour/ );
	await dialog.getByRole( 'button', { name: 'Close' } ).click();
	await expect( dialog ).toBeHidden();

	// Add to selection; the bar appears.
	for ( const title of [ 'Concert list E2E', 'ساعات العمل E2E', 'Opening hours E2E', 'Header with button E2E', 'Dark footer E2E' ] ) {
		await card( v, title ).getByRole( 'button', { name: 'Add to selection' } ).click();
	}
	await expect( card( v, 'Concert list E2E' ).getByRole( 'button', { name: 'In selection' } ) ).toHaveAttribute( 'aria-pressed', 'true' );
	const bar = v.locator( '.windrose-knotboard-bar' );
	await expect( bar ).toContainText( '5 patterns in your selection.' );
	const link = bar.getByRole( 'link', { name: 'See selection (opens in a new tab)' } );
	await expect( link ).toHaveAttribute( 'target', '_blank' );

	// The selection opens in a new tab.
	const [ s ] = await Promise.all( [ context.waitForEvent( 'page' ), link.click() ] );
	await s.waitForLoadState();
	expect( s.url() ).toBe( `${ LIB }selection/` );
	const content = s.locator( '.windrose-knotboard-selection__content' );
	await expect( content.locator( 'li' ) ).toHaveCount( 3 );
	// Added: concerts, Arabic hours, hours. Hours up twice, then the Arabic one removed.
	await s.getByRole( 'button', { name: 'Move up: Opening hours E2E' } ).click();
	await s.getByRole( 'button', { name: 'Move up: Opening hours E2E' } ).click();
	await s.getByRole( 'button', { name: 'Remove: ساعات العمل E2E' } ).click();
	await expect( content.locator( 'li' ) ).toHaveCount( 2 );
	await expect( content.locator( 'li' ).first() ).toContainText( 'Opening hours E2E' );
	await expect( s.getByText( 'Where to paste it: in your Site Editor, Patterns, Template parts, Header.', { exact: false } ) ).toBeVisible();
	await expect( s.getByText( 'Where to paste it: in a new page of your site', { exact: false } ) ).toBeVisible();

	// The preview: header, content in order, footer.
	const preview = s.locator( '.windrose-knotboard-selection .windrose-knotboard-preview' );
	const inOrder = async () => {
		const t = await frameDoc( preview );
		const at = [ 'Get in touch', 'Opening hours', 'On tour', 'Footer sample E2E' ].map( ( x ) => t.indexOf( x ) );
		return at.every( ( n, i ) => n >= 0 && ( 0 === i || n > at[ i - 1 ] ) );
	};
	await expect.poll( inOrder, { timeout: 30000 } ).toBe( true );
	await s.screenshot( { path: test.info().outputPath( 'selection.png' ), fullPage: true } );

	// Copies, with the right parts.
	await s.getByRole( 'button', { name: 'Copy header' } ).click();
	await expect( s.locator( '.windrose-knotboard-selection__status' ) ).toHaveText( 'The header is copied.' );
	expect( await clipboard( s ) ).toContain( 'Get in touch' );
	await s.getByRole( 'button', { name: 'Copy content' } ).click();
	await expect( s.locator( '.windrose-knotboard-selection__status' ) ).toHaveText( 'The content is copied.' );
	const contentCopy = await clipboard( s );
	expect( contentCopy.indexOf( 'Opening hours' ) ).toBeLessThan( contentCopy.indexOf( 'On tour' ) );
	expect( contentCopy ).not.toContain( 'Get in touch' );
	const [ file ] = await Promise.all( [ s.waitForEvent( 'download' ), content.getByRole( 'button', { name: 'Download' } ).click() ] );
	const json = JSON.parse( readFileSync( await file.path(), 'utf8' ) );
	expect( json ).toEqual( { __file: 'wp_block', title: 'Page from the pattern library', content: contentCopy, syncStatus: 'unsynced' } );
	writeFileSync( `${ WORK }/download.json`, JSON.stringify( json ) );

	// Another style changes the preview.
	const before = await preview.locator( 'iframe' ).evaluate( ( f ) => getComputedStyle( f.contentDocument.body ).backgroundColor );
	await s.getByRole( 'button', { name: /Preview style/ } ).click();
	await s.getByRole( 'radio', { name: 'Midnight Ink' } ).check();
	await expect( s.getByRole( 'button', { name: /Preview style/ } ) ).toContainText( 'Midnight Ink' );
	await expect.poll( () => preview.locator( 'iframe' ).evaluate( ( f ) => getComputedStyle( f.contentDocument.body ).backgroundColor ).catch( () => before ), { timeout: 30000 } ).not.toBe( before );

	// The library's tab follows the selection changed in the other tab.
	await expect( bar ).toContainText( '4 patterns in your selection.' );
	await expect( card( v, 'ساعات العمل E2E' ).getByRole( 'button', { name: 'Add to selection' } ) ).toBeVisible();

	// A pattern's page: large preview, Wide and Phone.
	await v.goto( `${ LIB }concert-list-e2e/` );
	await expect( v.getByRole( 'heading', { level: 1, name: 'Concert list E2E' } ) ).toBeVisible();
	const large = v.locator( '.windrose-knotboard-preview.is-full' );
	await expect.poll( () => frameDoc( large ), { timeout: 30000 } ).toContain( 'On tour' );
	await large.getByRole( 'button', { name: 'Phone' } ).click();
	await expect( large.locator( 'iframe' ) ).toHaveJSProperty( 'offsetWidth', 390 );
	await expect( v.getByRole( 'button', { name: 'In selection' } ) ).toBeVisible();
	await v.getByRole( 'link', { name: 'Back to all patterns' } ).click();
	await expect( v ).toHaveURL( LIB );

	expect( await context.cookies() ).toEqual( [] );
	expect( [ ...hosts ] ).toEqual( [ HOST ] );
	await context.close();
} );

test( 'Style Mermaid: See the patterns in this style opens the library with the style being made', async ( { browser } ) => {
	test.setTimeout( 180000 );
	site( 'option', 'update', 'windrose_livery_mermaid', '{"public":true,"remote":false}', '--format=json' );
	const pageId = site( 'post', 'create', '--post_type=page', '--post_status=publish', '--post_title=Make a style', '--post_content=<!-- wp:windrose/style-mermaid /-->', '--porcelain' );
	const context = await visitorContext( browser );
	const m = await context.newPage();
	await m.goto( `${ ROOT }/${ SITE }/?page_id=${ pageId }` );
	await expect( m.frameLocator( 'iframe[title="Preview of the style"]' ).locator( 'h1' ) ).toBeVisible( { timeout: 30000 } );
	await m.getByRole( 'button', { name: /^Corner Café/ } ).click();
	const [ v ] = await Promise.all( [
		context.waitForEvent( 'page' ),
		m.getByRole( 'button', { name: 'See the patterns in this style (opens in a new tab)' } ).click(),
	] );
	await v.waitForLoadState();
	expect( v.url() ).toBe( LIB );
	const toggle = v.getByRole( 'button', { name: /Preview style/ } ).first();
	await expect( toggle ).toContainText( 'My style (from Style Mermaid)' );
	const background = () =>
		card( v, 'Concert list E2E' )
			.locator( 'iframe' )
			.evaluate( ( f ) => getComputedStyle( f.contentDocument.body ).backgroundColor )
			.catch( () => '' );
	// Corner Café's background, #f8f0e5.
	await expect.poll( background, { timeout: 30000 } ).toBe( 'rgb(248, 240, 229)' );

	// The visitor goes on in Style Mermaid: the library follows.
	await m.getByRole( 'button', { name: /^Midnight Ink/ } ).click();
	await expect.poll( background, { timeout: 30000 } ).not.toBe( 'rgb(248, 240, 229)' );
	await toggle.click();
	await expect( v.getByRole( 'radio', { name: 'My style (from Style Mermaid)' } ) ).toBeChecked();
	await expect( v.getByRole( 'link', { name: 'Make your own style with Style Mermaid' } ) ).toHaveAttribute( 'href', new RegExp( `page_id=${ pageId }|make-a-style` ) );
	expect( await context.cookies() ).toEqual( [] );
	await context.close();
	site( 'option', 'update', 'windrose_livery_mermaid', '{"public":false,"remote":false}', '--format=json' );
} );

test( 'no jump: after another style, scrolling to the large preview leaves the page where it is', async ( { browser } ) => {
	const context = await visitorContext( browser );
	const v = await context.newPage();
	await v.setViewportSize( { width: 1280, height: 700 } );
	await v.goto( `${ LIB }concert-list-e2e/` );
	const frame = v.locator( '.windrose-knotboard-preview.is-full iframe' );
	await expect.poll( () => frame.evaluate( ( f ) => f.offsetHeight ), { timeout: 30000 } ).toBeGreaterThan( 300 );
	await v.getByRole( 'button', { name: /Preview style/ } ).click();
	await v.getByRole( 'radio', { name: 'Jukebox' } ).check();
	await v.keyboard.press( 'Escape' );
	// Scrolled down with the mouse wheel, as a visitor does: the redrawn preview comes into view and the page stays.
	await v.evaluate( () => window.scrollTo( 0, 0 ) );
	await v.mouse.move( 640, 350 );
	for ( let i = 0; i < 10; i++ ) {
		await v.mouse.wheel( 0, 100 );
		await v.waitForTimeout( 60 );
	}
	await v.waitForTimeout( 300 );
	const scrolled = await v.evaluate( () => Math.round( window.scrollY ) );
	expect( scrolled ).toBeGreaterThan( 500 );
	for ( let i = 0; i < 10; i++ ) {
		await v.waitForTimeout( 200 );
		expect( await v.evaluate( () => Math.round( window.scrollY ) ) ).toBe( scrolled );
	}
	await context.close();
} );

test( 'the selection proposes no header or footer when the library has none', async ( { browser } ) => {
	site( 'post', 'update', String( ids.header ), String( ids.footer ), '--post_status=draft' );
	try {
		const context = await visitorContext( browser );
		const v = await context.newPage();
		await v.goto( LIB );
		await expect( v.locator( 'details', { has: v.locator( 'summary', { hasText: 'Kind' } ) } ) ).toHaveCount( 0 );
		await card( v, 'Concert list E2E' ).getByRole( 'button', { name: 'Add to selection' } ).click();
		await v.goto( `${ LIB }selection/` );
		await expect( v.locator( '.windrose-knotboard-selection__content li' ) ).toHaveCount( 1 );
		await expect( v.getByRole( 'heading', { name: 'Template parts' } ) ).toHaveCount( 0 );
		await expect( v.getByText( 'Choose one in the library' ) ).toHaveCount( 0 );
		await context.close();
	} finally {
		site( 'post', 'update', String( ids.header ), String( ids.footer ), '--post_status=publish' );
	}
} );

test( 'accessible: the library and the selection (axe, WCAG 2.2 AA; previews run no scripts and are left out)', async ( { browser } ) => {
	const context = await visitorContext( browser );
	const v = await context.newPage();
	for ( const url of [ LIB, `${ LIB }selection/`, `${ LIB }concert-list-e2e/` ] ) {
		await v.goto( url );
		await v.waitForLoadState( 'networkidle' );
		const axe = await new AxeBuilder( { page: v } ).include( 'main' ).exclude( '.windrose-knotboard-preview__frame' ).withTags( [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ] ).analyze();
		expect( axe.violations.map( ( x ) => `${ url }: ${ x.id }` ) ).toEqual( [] );
	}
	// A filter's menu open: its checkboxes, labels and counts.
	await v.goto( LIB );
	await v.locator( 'summary', { hasText: 'Tags' } ).click();
	const open = await new AxeBuilder( { page: v } ).include( '.windrose-knotboard-filters' ).withTags( [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ] ).analyze();
	expect( open.violations.map( ( x ) => x.id ) ).toEqual( [] );
	await context.close();
} );

test( 'refused: drafts and the site’s own patterns, also through the preview', async ( { browser } ) => {
	const context = await visitorContext( browser );
	const r = context.request;
	const own = site( 'post', 'list', '--post_type=wp_block', '--field=ID' );
	expect( ( await r.get( `${ ROOT }/${ SITE }/wp-json/windrose-knotboard/v1/copy?ids=${ ids.draft },${ own }` ) ).status() ).toBe( 404 );
	const recipe = JSON.stringify( ( await ( await r.get( `${ ROOT }/${ SITE }/wp-json/windrose-knotboard/v1/styles/ipsum` ) ).json() ).recipe );
	const render = `${ ROOT }/${ SITE }/?windrose_livery_patterns=1`;
	expect( ( await r.post( render, { form: { recipe, source: 'knotboard', items: `${ ids.draft },${ own }`, layout: 'list' } } ) ).status() ).toBe( 404 );
	expect( ( await r.get( `${ LIB }draft-e2e/` ) ).status() ).toBe( 404 );
	await context.close();
} );

test( 'the copied pattern opens in the editor with valid blocks and outside pictures WordPress offers to upload', async ( { page } ) => {
	test.setTimeout( 120000 );
	const post = site( 'post', 'create', `${ WORK }/copied.html`, '--post_type=page', '--post_status=draft', '--post_title=Pasted E2E', '--porcelain', `--user=${ USER }` );
	await page.goto( `${ ROOT }/${ SITE }/wp-admin/post.php?post=${ post }&action=edit` );
	await page.waitForFunction( () => window.wp?.data?.select( 'core/block-editor' )?.getBlocks()?.length > 0, null, { timeout: 60000 } );
	const result = await page.evaluate( () => {
		const bad = [];
		const media = [];
		const walk = ( blocks ) =>
			blocks.forEach( ( b ) => {
				if ( ! b.isValid ) {
					bad.push( b.name );
				}
				if ( [ 'core/image', 'core/cover', 'core/media-text' ].includes( b.name ) ) {
					media.push( [ b.name, b.attributes.url ?? b.attributes.mediaUrl ?? null, b.attributes.id ?? b.attributes.mediaId ?? null ] );
				}
				walk( b.innerBlocks );
			} );
		walk( window.wp.data.select( 'core/block-editor' ).getBlocks() );
		return { bad, media };
	} );
	expect( result.bad ).toEqual( [] );
	expect( result.media.map( ( [ name, url, id ] ) => [ name, url?.startsWith( `${ ROOT }/${ SITE }/wp-content/uploads/` ), id ] ) ).toEqual( [
		[ 'core/image', true, null ],
		[ 'core/media-text', true, null ],
		[ 'core/cover', true, null ],
	] );
	// WordPress's own Upload to Media Library (7.2: the server downloads the picture) gives the Image an attachment.
	await page.evaluate( () => {
		const image = window.wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ].innerBlocks.find( ( b ) => 'core/image' === b.name );
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( image.clientId );
	} );
	await page.getByRole( 'button', { name: 'Upload to Media Library' } ).click();
	await expect.poll(
		() => page.evaluate( () => window.wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ].innerBlocks.find( ( b ) => 'core/image' === b.name ).attributes.id ?? null ),
		{ timeout: 30000 }
	).not.toBeNull();
	site( 'post', 'delete', post, '--force' );
} );

test( 'the downloaded file goes into the Site Editor with core’s Import pattern from JSON', async ( { page } ) => {
	test.setTimeout( 120000 );
	const before = Number( site( 'post', 'list', '--post_type=wp_block', '--format=count' ) );
	await page.goto( `${ ROOT }/${ SITE }/wp-admin/site-editor.php?p=%2Fpattern` );
	await page.getByRole( 'button', { name: /^Add (New )?pattern$/i } ).first().click( { timeout: 60000 } );
	const chooser = page.waitForEvent( 'filechooser' );
	await page.getByRole( 'menuitem', { name: /Import pattern from JSON/i } ).click();
	await ( await chooser ).setFiles( `${ WORK }/download.json` );
	await expect.poll( () => Number( site( 'post', 'list', '--post_type=wp_block', '--format=count' ) ), { timeout: 30000 } ).toBe( before + 1 );
} );

test( 'administrator: templates in the Site Editor, the address base, the network switch', async ( { page } ) => {
	test.setTimeout( 120000 );
	await page.goto( `${ ROOT }/${ SITE }/wp-admin/site-editor.php?p=%2Ftemplate` );
	await expect( page.getByText( 'Pattern Library', { exact: true } ).filter( { visible: true } ).first() ).toBeVisible( { timeout: 60000 } );
	await expect( page.getByText( 'Pattern Library: selection', { exact: true } ).first() ).toBeVisible();

	await page.goto( `${ ROOT }/${ SITE }/wp-admin/options-permalink.php` );
	await page.getByLabel( 'Pattern library base' ).fill( 'designs' );
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();
	await page.goto( `${ ROOT }/${ SITE }/designs/` );
	await expect( page.locator( '.windrose-knotboard-card' ) ).toHaveCount( 7 );
	await page.goto( `${ ROOT }/${ SITE }/wp-admin/options-permalink.php` );
	await page.getByLabel( 'Pattern library base' ).fill( '' );
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();
	expect( ( await page.request.get( LIB ) ).status() ).toBe( 200 );

	await page.goto( `${ ROOT }/wp-admin/network/settings.php` );
	const box = page.getByLabel( 'Site administrators manage their site’s pattern library' );
	await expect( box ).toBeChecked();
	await box.uncheck();
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();
	await expect( page.getByLabel( 'Site administrators manage their site’s pattern library' ) ).not.toBeChecked();
	expect( wp( 'site', 'option', 'get', 'windrose_knotboard_network', '--format=json' ) ).toBe( '{"site_admins":false}' );
} );

/**
 * Makes a pattern library package (zip) in WORK: a pattern with a picture and one without, in English.
 *
 * @param {string}   name Zip name.
 * @param {string[]} keys Patterns to put in it.
 * @return {string} Path.
 */
function packageZip( name, keys ) {
	const dir = `${ WORK }/${ name }`;
	rmSync( dir, { recursive: true, force: true } );
	mkdirSync( `${ dir }/patterns`, { recursive: true } );
	mkdirSync( `${ dir }/images`, { recursive: true } );
	site( 'eval', `$i = imagecreatetruecolor( 600, 400 ); imagefill( $i, 0, 0, imagecolorallocate( $i, 30, 30, 30 ) ); imagepng( $i, '${ dir }/images/stage-band.photo.png' );` );
	const sha = ( data ) => createHash( 'sha256' ).update( data ).digest( 'hex' );
	const png = readFileSync( `${ dir }/images/stage-band.photo.png` );
	const all = {
		'stage-band': {
			kind: 'section',
			title: 'Stage band import E2E',
			reviewed: false,
			tags: [ 'about' ],
			images: [ 'stage-band.photo' ],
			content:
				'<!-- wp:media-text {"mediaId":{{id:stage-band.photo}},"mediaType":"image"} -->\n<div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="{{url:stage-band.photo}}" alt="A dark stage" class="wp-image-{{id:stage-band.photo}} size-full"/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph -->\n<p>We play loud.</p>\n<!-- /wp:paragraph --></div></div>\n<!-- /wp:media-text -->',
		},
		'tour-import': {
			kind: 'section',
			title: 'Tour import E2E',
			reviewed: true,
			tags: [],
			images: [],
			content: '<!-- wp:heading -->\n<h2 class="wp-block-heading">On tour</h2>\n<!-- /wp:heading -->',
		},
	};
	const patterns = keys.map( ( key ) => {
		const { content, ...rest } = all[ key ];
		writeFileSync( `${ dir }/patterns/${ key }.html`, content );
		return { key, ...rest, description: `About ${ rest.title }`, categories: [ 'musician' ], file: `patterns/${ key }.html`, sha256: sha( content ) };
	} );
	const manifest = {
		format: 'windrose-knotboard-package',
		format_version: 1,
		generator: 'Knotboard E2E',
		pack: 'musician-e2e',
		release: '0.1.0',
		language: { code: 'en', name: 'English', dir: 'ltr' },
		terms: { category: { musician: { en: 'Musician E2E', fr: 'Musicien E2E' } }, tag: { about: 'About E2E' } },
		images: {
			'stage-band.photo': {
				file: 'images/stage-band.photo.png',
				sha256: sha( png ),
				type: 'image/png',
				alt: 'A dark stage',
				credit: { author: 'Nilo Velez', author_url: 'https://wordpress.org/photos/author/nilovelez/', source: 'https://wordpress.org/photos/photo/7086659866/', licence: 'CC0 1.0' },
			},
		},
		patterns,
	};
	writeFileSync( `${ dir }/knotboard.json`, JSON.stringify( manifest ) );
	execFileSync( 'zip', [ '-qr', `${ WORK }/${ name }.zip`, 'knotboard.json', 'patterns', 'images' ], { cwd: dir } );
	return `${ WORK }/${ name }.zip`;
}

test( 'import: Tools, Import; upload, dry run, import, notes and credit; a pattern no longer in its pack', async ( { page } ) => {
	test.setTimeout( 180000 );
	const full = packageZip( 'musician-e2e-en', [ 'stage-band', 'tour-import' ] );
	const smaller = packageZip( 'musician-e2e-en-smaller', [ 'tour-import' ] );
	const imports = () => Number( site( 'post', 'list', '--post_type=attachment', '--post_status=any', '--meta_key=_wp_attachment_context', '--meta_value=import', '--format=count' ) );

	await page.goto( `${ ROOT }/${ SITE }/wp-admin/import.php` );
	await expect( page.getByText( 'Pattern library package', { exact: true } ) ).toBeVisible();
	await page.goto( `${ ROOT }/${ SITE }/wp-admin/edit.php?post_type=windrose_knotboard` );
	await page.locator( '#menu-posts-windrose_knotboard' ).getByRole( 'link', { name: 'Import', exact: true } ).click();
	await expect( page.getByRole( 'heading', { name: 'Import into the Pattern Library' } ) ).toBeVisible();

	// Upload and check: a dry run, nothing written.
	await page.getByLabel( 'Packages' ).setInputFiles( full );
	await page.getByRole( 'button', { name: 'Upload and check' } ).click();
	await expect( page.getByText( 'Dry run: nothing has changed yet.' ) ).toBeVisible();
	const table = page.locator( 'table.windrose-knotboard-import' );
	await expect( table.locator( 'tbody tr' ) ).toHaveCount( 2 );
	await expect( table ).toContainText( 'New' );
	expect( site( 'post', 'list', '--post_type=windrose_knotboard', '--s=import E2E', '--format=count' ) ).toBe( '0' );
	await page.getByRole( 'button', { name: 'Import' } ).click();
	await expect( page.locator( '.notice-success' ) ).toContainText( '2 created' );
	await expect( page.locator( '.notice-success' ) ).toContainText( '1 pictures added' );
	expect( imports() ).toBe( 0 );

	// The library: the card says the translation is not reviewed; the pattern's page credits the photo.
	await page.goto( LIB );
	await expect( card( page, 'Stage band import E2E' ).locator( '.windrose-knotboard-notes__unreviewed' ) ).toHaveText( 'Translation not reviewed yet' );
	await expect( card( page, 'Tour import E2E' ).locator( '.windrose-knotboard-notes' ) ).toHaveCount( 0 );
	await page.goto( `${ LIB }stage-band-en/` );
	await expect( page.locator( '.windrose-knotboard-notes__credit' ) ).toContainText( 'Photo: Nilo Velez, CC0 1.0' );
	await expect( page.locator( '.windrose-knotboard-notes__credit a' ).last() ).toHaveAttribute( 'href', 'https://wordpress.org/photos/photo/7086659866/' );

	// The same package again: unchanged. A smaller one: the missing pattern needs a choice; drafts.
	await page.goto( `${ ROOT }/${ SITE }/wp-admin/admin.php?import=windrose-knotboard` );
	await page.getByLabel( 'Packages' ).setInputFiles( smaller );
	await page.getByRole( 'button', { name: 'Upload and check' } ).click();
	await expect( table ).toContainText( 'No longer in its pack' );
	await expect( table ).toContainText( 'Unchanged' );
	await page.getByRole( 'button', { name: 'Import' } ).click();
	// The choice is required.
	await expect( page.getByText( 'Dry run: nothing has changed yet.' ) ).toBeVisible();
	await page.getByLabel( /They move to drafts/ ).check();
	await page.getByRole( 'button', { name: 'Import' } ).click();
	await expect( page.locator( '.notice-success' ) ).toContainText( '1 moved to drafts' );
	await expect( table ).toContainText( 'Moved to drafts' );
	expect( site( 'post', 'list', '--post_type=windrose_knotboard', '--name=stage-band-en', '--post_status=draft', '--format=count' ) ).toBe( '1' );
	expect( imports() ).toBe( 0 );
} );
