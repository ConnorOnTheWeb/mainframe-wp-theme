/**
 * Mainframe — Featured Image URL block editor integration.
 *
 * Two behaviours:
 *  1. When _mainframe_featured_image_url is set, replace the native
 *     "Featured Image" panel content with a preview of that URL plus a
 *     "Remove" button. The native attachment UI is hidden while a custom
 *     URL is active.
 *  2. A "Featured Image URL" document-settings panel provides a URL input
 *     for setting / changing the value.
 *
 * No build tools — uses globally available wp.* APIs.
 */
( function ( wp ) {
	'use strict';

	if (
		! wp ||
		! wp.plugins || ! wp.editPost || ! wp.data ||
		! wp.components || ! wp.element ||
		! wp.hooks || ! wp.compose
	) {
		return;
	}

	var el                         = wp.element.createElement;
	var Fragment                   = wp.element.Fragment;
	var __                         = wp.i18n.__;
	var registerPlugin             = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var useSelect                  = wp.data.useSelect;
	var useDispatch                = wp.data.useDispatch;
	var TextControl                = wp.components.TextControl;
	var Button                     = wp.components.Button;
	var addFilter                  = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;

	// -----------------------------------------------------------------------
	// Override the native PostFeaturedImage component when a custom URL is set
	// -----------------------------------------------------------------------
	addFilter(
		'editor.PostFeaturedImage',
		'mainframe/featured-image-url-override',
		createHigherOrderComponent( function ( PostFeaturedImage ) {
			return function ( props ) {
				var meta = useSelect( function ( select ) {
					return select( 'core/editor' ).getEditedPostAttribute( 'meta' );
				} );

				var editPost = useDispatch( 'core/editor' ).editPost;

				var customUrl = ( meta && meta._mainframe_featured_image_url )
					? meta._mainframe_featured_image_url
					: ( meta && meta.fifu_image_url )
						? meta.fifu_image_url
						: '';

				// No custom URL — render the native featured image UI unchanged.
				if ( ! customUrl ) {
					return el( PostFeaturedImage, props );
				}

				// Custom URL is set — show the image and a remove affordance.
				return el( Fragment, null,
					el( 'img', {
						src:   customUrl,
						alt:   __( 'Featured image (external URL)', 'mainframe' ),
						style: {
							display:      'block',
							width:        '100%',
							borderRadius: '2px',
						},
					} ),
					el( 'div', {
						style: {
							display:        'flex',
							alignItems:     'center',
							justifyContent: 'space-between',
							padding:        '8px 0 4px',
							gap:            '8px',
						},
					},
						el( 'span', {
							style: { fontSize: '12px', color: '#757575', flex: '1' },
						}, __( 'Featured Image URL is set', 'mainframe' ) ),
						el( Button, {
							variant:       'link',
							isDestructive: true,
							style:         { fontSize: '12px', flexShrink: 0 },
							onClick: function () {
								editPost( { meta: { _mainframe_featured_image_url: '' } } );
							},
						}, __( 'Remove', 'mainframe' ) )
					)
				);
			};
		}, 'MainframeFeaturedImageURLOverride' )
	);

	// -----------------------------------------------------------------------
	// Document Settings panel — URL input only (preview is shown above)
	// -----------------------------------------------------------------------
	function FeaturedImageUrlPanel() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		} );

		var editPost = useDispatch( 'core/editor' ).editPost;

		// Only the theme's own field is editable via this input.
		var url = ( meta && meta._mainframe_featured_image_url )
			? meta._mainframe_featured_image_url
			: '';

		// FIFU migration: inform the user when their image is coming from the old plugin's meta.
		var fifuUrl = ( ! url && meta && meta.fifu_image_url ) ? meta.fifu_image_url : '';

		return el(
			PluginDocumentSettingPanel,
			{
				name:  'mainframe-featured-image-url',
				title: __( 'Featured Image URL', 'mainframe' ),
				icon:  'format-image',
			},
			el( TextControl, {
				label:       __( 'External image URL', 'mainframe' ),
				help:        __( 'Overrides the attached featured image in the REST API response. Preview shown in the Featured Image panel above.', 'mainframe' ),
				value:       url,
				onChange:    function ( value ) {
					editPost( { meta: { _mainframe_featured_image_url: value } } );
				},
				placeholder: fifuUrl || 'https://',
				type:        'url',
			} ),
			fifuUrl
				? el( 'p', { style: { fontSize: '12px', color: '#757575', marginTop: '4px' } },
					__( 'Image inherited from FIFU. Enter a URL above to override it.', 'mainframe' )
				)
				: null
		);
	}

	// Remove UI elements that link to the WordPress URL — irrelevant on a
	// headless install where the WP frontend is not the consuming app.

	// Preview button (toolbar — desktop and mobile).
	addFilter(
		'editor.PostPreview',
		'mainframe/disable-preview',
		createHigherOrderComponent(
			function () { return function () { return null; }; },
			'MainframeDisablePreview'
		)
	);

	// The post-publish "What's next?" section (address) is suppressed via CSS
	// injected in inc/meta.php. The View Post / Add Post buttons are preserved
	// and will link to the frontend app when a frontend URL is configured.

	registerPlugin( 'mainframe-featured-image-url', {
		render: FeaturedImageUrlPanel,
	} );

} )( window.wp );

