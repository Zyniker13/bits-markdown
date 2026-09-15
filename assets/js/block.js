( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var RawHTML = wp.element.RawHTML;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var BlockControls = wp.blockEditor.BlockControls;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	var ToolbarGroup = wp.components.ToolbarGroup;
	var ToolbarButton = wp.components.ToolbarButton;
	var PanelBody = wp.components.PanelBody;
	var TextareaControl = wp.components.TextareaControl;
	var Notice = wp.components.Notice;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	function renderMath( root ) {
		if ( ! root || ! window.katex ) {
			return;
		}
		root.querySelectorAll( '.bristlecone-markdown-math, .bits-markdown-math' ).forEach( function ( node ) {
			if ( node.getAttribute( 'data-rendered' ) === '1' ) {
				return;
			}
			try {
				window.katex.render( node.textContent, node, {
					displayMode: node.getAttribute( 'data-display' ) === 'true',
					throwOnError: false,
				} );
				node.setAttribute( 'data-rendered', '1' );
			} catch ( e ) {
				// Leave the TeX source visible.
			}
		} );
	}

	registerBlockType( 'bristlecone/markdown', {
		edit: function ( props ) {
			var markdown = props.attributes.markdown || '';
			var html = props.attributes.html || '';
			var previewState = useState( html );
			var preview = previewState[ 0 ];
			var setPreview = previewState[ 1 ];
			var tabState = useState( 'markdown' );
			var tab = tabState[ 0 ];
			var setTab = tabState[ 1 ];
			var errorState = useState( '' );
			var error = errorState[ 0 ];
			var setError = errorState[ 1 ];
			var timer = useRef( null );
			var previewRef = useRef( null );
			var postId = 0;

			try {
				postId = wp.data.select( 'core/editor' ).getCurrentPostId() || 0;
			} catch ( e ) {
				postId = 0;
			}

			var blockProps = useBlockProps( {
				className: 'bristlecone-markdown-editor',
			} );

			function fetchPreview( source ) {
				apiFetch( {
					path: '/bristlecone-markdown/v1/preview',
					method: 'POST',
					data: {
						markdown: source,
						post_id: postId,
					},
				} )
					.then( function ( response ) {
						var nextHtml = response.html || '';
						setPreview( nextHtml );
						setError( '' );
						if ( nextHtml !== ( props.attributes.html || '' ) ) {
							props.setAttributes( { html: nextHtml } );
						}
					} )
					.catch( function () {
						setError(
							__( 'Could not render a preview. The published output still uses the server parser.', 'bristlecone-markdown' )
						);
					} );
			}

			useEffect(
				function () {
					if ( timer.current ) {
						window.clearTimeout( timer.current );
					}
					timer.current = window.setTimeout( function () {
						fetchPreview( markdown );
					}, 400 );
					return function () {
						if ( timer.current ) {
							window.clearTimeout( timer.current );
						}
					};
				},
				[ markdown ]
			);

			useEffect(
				function () {
					if ( tab === 'preview' ) {
						renderMath( previewRef.current );
					}
				},
				[ preview, tab ]
			);

			function insertMedia( media ) {
				if ( ! media || ! media.url ) {
					return;
				}
				var alt = media.alt || media.title || '';
				var snippet = '![' + alt + '](' + media.url + ')';
				var next = markdown ? markdown.replace( /\s*$/, '\n\n' ) + snippet + '\n' : snippet + '\n';
				props.setAttributes( { markdown: next } );
			}

			return el(
				'div',
				blockProps,
				el(
					BlockControls,
					null,
					el(
						ToolbarGroup,
						null,
						el( ToolbarButton, {
							icon: 'editor-code',
							label: __( 'Markdown', 'bristlecone-markdown' ),
							isPressed: tab === 'markdown',
							onClick: function () {
								setTab( 'markdown' );
							},
						} ),
						el( ToolbarButton, {
							icon: 'visibility',
							label: __( 'Preview', 'bristlecone-markdown' ),
							isPressed: tab === 'preview',
							onClick: function () {
								setTab( 'preview' );
							},
						} )
					),
					el(
						MediaUploadCheck,
						null,
						el( MediaUpload, {
							onSelect: insertMedia,
							allowedTypes: [ 'image' ],
							render: function ( obj ) {
								return el( ToolbarButton, {
									icon: 'format-image',
									label: __( 'Insert image', 'bristlecone-markdown' ),
									onClick: obj.open,
								} );
							},
						} )
					)
				),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Bristlecone Markdown', 'bristlecone-markdown' ), initialOpen: true },
						el(
							'p',
							null,
							__(
								'Write Markdown in this block. Syntax follows iA Writer (CommonMark plus highlight, footnotes, tables, math, metadata, and more). Task lists and Content Blocks are not converted.',
								'bristlecone-markdown'
							)
						)
					)
				),
				tab === 'markdown'
					? el( TextareaControl, {
							className: 'bristlecone-markdown-source',
							label: __( 'Markdown', 'bristlecone-markdown' ),
							hideLabelFromVision: true,
							value: markdown,
							onChange: function ( value ) {
								props.setAttributes( { markdown: value } );
							},
							rows: 16,
					  } )
					: el(
							'div',
							{
								className: 'bristlecone-markdown-preview bristlecone-markdown',
								ref: previewRef,
							},
							el( RawHTML, null, preview || '<p></p>' )
					  ),
				error ? el( Notice, { status: 'warning', isDismissible: false }, error ) : null
			);
		},
		save: function ( props ) {
			var blockProps = useBlockProps.save( {
				className: 'bristlecone-markdown',
			} );
			return el( 'div', blockProps, el( RawHTML, null, props.attributes.html || '' ) );
		},
	} );
} )( window.wp );
