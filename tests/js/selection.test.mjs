/**
 * The visitor's selection (src/view/selection.js), node --test.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
	count,
	emptySelection,
	fileName,
	isSelected,
	move,
	pageOrder,
	patternFile,
	readSelection,
	refresh,
	remove,
	toggle,
} from '../../src/view/selection.js';

const H1 = { id: 1, kind: 'header', title: 'H1', lang: 'en' };
const H2 = { id: 2, kind: 'header', title: 'H2', lang: 'en' };
const F = { id: 3, kind: 'footer', title: 'F', lang: 'en' };
const S1 = { id: 4, kind: 'section', title: 'S1', lang: 'en' };
const S2 = { id: 5, kind: 'section', title: 'S2', lang: 'ar', dir: 'rtl' };
const P = { id: 6, kind: 'page', title: 'P', lang: 'en' };
const ids = ( list ) => list.map( ( item ) => item.id );

test( 'one header and one footer: a new one replaces the old, the same one removes it', () => {
	let s = toggle( emptySelection(), H1 );
	assert.equal( s.header.id, 1 );
	s = toggle( s, H2 );
	assert.equal( s.header.id, 2 );
	assert.ok( isSelected( s, 2 ) && ! isSelected( s, 1 ) );
	s = toggle( s, H2 );
	assert.equal( s.header, null );
	s = toggle( s, F );
	assert.equal( s.footer.title, 'F' );
	assert.equal( toggle( s, { id: 'x', kind: 'section' } ), s, 'Bad entries change nothing.' );
} );

test( 'content in the visitor’s order; move and remove', () => {
	let s = emptySelection();
	for ( const item of [ S1, P, S2 ] ) {
		s = toggle( s, item );
	}
	assert.deepEqual( ids( s.content ), [ 4, 6, 5 ] );
	s = move( s, 5, -1 );
	assert.deepEqual( ids( s.content ), [ 4, 5, 6 ] );
	assert.deepEqual( ids( move( s, 4, -1 ).content ), [ 4, 5, 6 ] );
	assert.deepEqual( ids( move( s, 6, 1 ).content ), [ 4, 5, 6 ] );
	assert.equal( s.content[ 1 ].dir, 'rtl' );
	s = toggle( toggle( s, H1 ), F );
	s = remove( remove( s, 4 ), 1 );
	assert.deepEqual( ids( pageOrder( s ) ), [ 5, 6, 3 ] );
} );

test( 'as a page: header, content, footer', () => {
	let s = emptySelection();
	for ( const item of [ S2, F, H1, S1 ] ) {
		s = toggle( s, item );
	}
	assert.deepEqual( ids( pageOrder( s ) ), [ 1, 5, 4, 3 ] );
	assert.equal( count( s ), 4 );
	assert.deepEqual( pageOrder( emptySelection() ), [] );
} );

test( 'stored selections: anything wrong left out, no duplicates', () => {
	const stored = JSON.stringify( {
		header: F,
		footer: F,
		content: [ S1, { id: 99 }, H1, P, S1, 'x' ],
	} );
	const s = readSelection( stored );
	assert.equal( s.header, null );
	assert.equal( s.footer.id, 3 );
	assert.deepEqual( ids( s.content ), [ 4, 6 ] );
	assert.deepEqual( readSelection( '{bad' ), emptySelection() );
	assert.deepEqual( readSelection( null ), emptySelection() );
	assert.deepEqual( readSelection( '"text"' ), emptySelection() );
} );

test( 'refresh: patterns no longer offered leave, titles and kinds follow the site', () => {
	let s = emptySelection();
	for ( const item of [ H1, F, S1, S2 ] ) {
		s = toggle( s, item );
	}
	const now = refresh( s, [
		{ ...H1, title: 'New title' },
		{ ...S1, kind: 'footer' },
		S2,
	] );
	assert.equal( now.header.title, 'New title' );
	assert.equal( now.footer, null );
	assert.deepEqual( ids( now.content ), [ 5 ] );
} );

test( 'pattern files as core imports them, and their names', () => {
	assert.deepEqual( patternFile( 'Hero', '<!-- wp:paragraph /-->' ), {
		__file: 'wp_block',
		title: 'Hero',
		content: '<!-- wp:paragraph /-->',
		syncStatus: 'unsynced',
	} );
	assert.equal( fileName( 'Café: Opening hours!' ), 'cafe-opening-hours.json' );
	assert.equal( fileName( 'مرحبا', 'header' ), 'header.json' );
} );
