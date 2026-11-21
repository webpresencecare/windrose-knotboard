/**
 * Knotboard's blocks in the editor (the Site Editor's library templates, or any page): simple stand-ins with their
 * settings. What visitors see is drawn on the published page.
 */
import { registerBlockType } from '@wordpress/blocks';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import preview from '../../blocks/pattern-preview/block.json';
import actions from '../../blocks/pattern-actions/block.json';
import filter from '../../blocks/pattern-filter/block.json';
import filters from '../../blocks/pattern-filters/block.json';
import search from '../../blocks/pattern-search/block.json';
import style from '../../blocks/pattern-style/block.json';
import bar from '../../blocks/pattern-selection-bar/block.json';
import selection from '../../blocks/pattern-selection/block.json';
import back from '../../blocks/pattern-back/block.json';
import notes from '../../blocks/pattern-notes/block.json';
import './editor.scss';

const data = window.windroseKnotboardBlocks || {};

/**
 * A stand-in.
 *
 * @param {Object} props          Props.
 * @param {string} props.label    What it is.
 * @param {string} props.children What visitors get.
 * @return {Element} Stand-in.
 */
function StandIn( { label, children } ) {
	return (
		<div
			{ ...useBlockProps( { className: 'windrose-knotboard-standin' } ) }
		>
			<strong>{ label }</strong>
			<span>{ children }</span>
		</div>
	);
}

registerBlockType( preview.name, {
	...preview,
	edit: ( { attributes, setAttributes } ) => (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Preview', 'windrose-knotboard' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Size', 'windrose-knotboard' ) }
						value={ attributes.size }
						options={ [
							{
								value: 'thumb',
								label: __(
									'Thumbnail (for a list of patterns)',
									'windrose-knotboard'
								),
							},
							{
								value: 'full',
								label: __(
									'Large, with Wide and Phone (for a pattern’s page)',
									'windrose-knotboard'
								),
							},
						] }
						onChange={ ( size ) => setAttributes( { size } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<StandIn label={ __( 'Pattern Preview', 'windrose-knotboard' ) }>
				{ data.livery
					? __(
							'The pattern, drawn in the style the visitor chooses.',
							'windrose-knotboard'
						)
					: __(
							'Needs Windrose Livery 0.7 or later.',
							'windrose-knotboard'
						) }
			</StandIn>
		</>
	),
	save: () => null,
} );

registerBlockType( actions.name, {
	...actions,
	edit: ( { attributes, setAttributes } ) => (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Buttons', 'windrose-knotboard' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Copy pattern', 'windrose-knotboard' ) }
						checked={ attributes.copy }
						onChange={ ( copy ) => setAttributes( { copy } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Add to selection', 'windrose-knotboard' ) }
						checked={ attributes.select }
						onChange={ ( value ) =>
							setAttributes( { select: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'View code', 'windrose-knotboard' ) }
						checked={ attributes.code }
						onChange={ ( code ) => setAttributes( { code } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<StandIn label={ __( 'Pattern Actions', 'windrose-knotboard' ) }>
				{ [
					attributes.copy &&
						__( 'Copy pattern', 'windrose-knotboard' ),
					attributes.select &&
						__( 'Add to selection', 'windrose-knotboard' ),
					attributes.code && __( 'View code', 'windrose-knotboard' ),
				]
					.filter( Boolean )
					.join( ' · ' ) || __( 'No button', 'windrose-knotboard' ) }
			</StandIn>
		</>
	),
	save: () => null,
} );

const FILTERS = {
	kind: __( 'Kind filter', 'windrose-knotboard' ),
	category: __( 'Category filter', 'windrose-knotboard' ),
	tag: __( 'Tag filter', 'windrose-knotboard' ),
	language: __( 'Language filter', 'windrose-knotboard' ),
};

registerBlockType( filter.name, {
	...filter,
	variations: Object.entries( FILTERS ).map( ( [ taxonomy, title ] ) => ( {
		name: taxonomy,
		title,
		attributes: { taxonomy },
		isActive: [ 'taxonomy' ],
		scope: [ 'inserter', 'transform' ],
	} ) ),
	edit: ( { attributes, setAttributes } ) => (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Filter', 'windrose-knotboard' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Filter by', 'windrose-knotboard' ) }
						value={ attributes.taxonomy }
						options={ Object.entries( FILTERS ).map(
							( [ value, label ] ) => ( { value, label } )
						) }
						onChange={ ( taxonomy ) =>
							setAttributes( { taxonomy } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Show as', 'windrose-knotboard' ) }
						value={ attributes.display }
						options={ [
							{
								value: 'dropdown',
								label: __(
									'Button with checkboxes',
									'windrose-knotboard'
								),
							},
							{
								value: 'links',
								label: __( 'Links', 'windrose-knotboard' ),
							},
							{
								value: 'cloud',
								label: __(
									'Cloud (bigger when more patterns use it)',
									'windrose-knotboard'
								),
							},
						] }
						onChange={ ( display ) => setAttributes( { display } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<StandIn label={ FILTERS[ attributes.taxonomy ] ?? FILTERS.kind }>
				{ 'language' === attributes.taxonomy
					? __(
							'Links to the patterns of each language (shown once there are two languages or more).',
							'windrose-knotboard'
						)
					: __(
							'Links that narrow the library.',
							'windrose-knotboard'
						) }
			</StandIn>
		</>
	),
	save: () => null,
} );

[
	[
		search,
		__( 'Pattern Search', 'windrose-knotboard' ),
		__( 'A search field for the library.', 'windrose-knotboard' ),
	],
	[
		style,
		__( 'Pattern Preview Style', 'windrose-knotboard' ),
		__(
			'Visitors choose the style of the previews.',
			'windrose-knotboard'
		),
	],
	[
		bar,
		__( 'Pattern Selection Bar', 'windrose-knotboard' ),
		__(
			'The visitor’s number of chosen patterns and See selection (opens in a new tab). Hidden while the selection is empty.',
			'windrose-knotboard'
		),
	],
	[
		selection,
		__( 'Pattern Selection', 'windrose-knotboard' ),
		__(
			'The visitor’s chosen patterns: order, remove, Copy header, Copy footer, Copy content, Download, and a preview of them together.',
			'windrose-knotboard'
		),
	],
	[
		back,
		__( 'Pattern Library Link', 'windrose-knotboard' ),
		__( 'Back to all patterns.', 'windrose-knotboard' ),
	],
].forEach( ( [ metadata, label, text ] ) =>
	registerBlockType( metadata.name, {
		...metadata,
		edit: () => <StandIn label={ label }>{ text }</StandIn>,
		save: () => null,
	} )
);

/**
 * Pattern Filters in the editor: its search and filter blocks, then the Filter button.
 *
 * @return {Element} Edit view.
 */
function FiltersEdit() {
	return (
		<div
			{ ...useBlockProps( {
				className: 'windrose-knotboard-standin is-filters',
			} ) }
		>
			<strong>{ __( 'Pattern Filters', 'windrose-knotboard' ) }</strong>
			<InnerBlocks
				allowedBlocks={ [ search.name, filter.name ] }
				orientation="horizontal"
				template={ [
					[ search.name ],
					[ filter.name, { taxonomy: 'kind' } ],
					[ filter.name, { taxonomy: 'category' } ],
					[ filter.name, { taxonomy: 'tag' } ],
					[ filter.name, { taxonomy: 'language' } ],
				] }
			/>
			<span>{ __( 'Then a Filter button.', 'windrose-knotboard' ) }</span>
		</div>
	);
}

registerBlockType( filters.name, {
	...filters,
	edit: FiltersEdit,
	save: () => <InnerBlocks.Content />,
} );

/**
 * Pattern Notes in the editor: what it shows, and whether photo credits are part of it.
 *
 * @param {Object}                  props               Props.
 * @param {Object}                  props.attributes    Attributes.
 * @param {(attrs: Object) => void} props.setAttributes Setter.
 * @return {Element} Edit view.
 */
function NotesEdit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'windrose-knotboard' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show photo credits',
							'windrose-knotboard'
						) }
						checked={ false !== attributes.credits }
						onChange={ ( credits ) => setAttributes( { credits } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<StandIn label={ __( 'Pattern Notes', 'windrose-knotboard' ) }>
				{ false !== attributes.credits
					? __(
							'“Translation not reviewed yet” when it applies, and who took its photos.',
							'windrose-knotboard'
						)
					: __(
							'“Translation not reviewed yet” when it applies.',
							'windrose-knotboard'
						) }
			</StandIn>
		</>
	);
}

registerBlockType( notes.name, {
	...notes,
	edit: NotesEdit,
	save: () => null,
} );
