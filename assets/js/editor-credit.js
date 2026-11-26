/**
 * "Published by" panel for the block editor sidebar.
 *
 * Writes the same post meta the theme already reads (_eagleeye_credit), so the
 * credit can be set while writing a new story instead of scrolling to the
 * classic panel below the editor.
 */
( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.element || ! wp.data || ! wp.components ) {
		return;
	}

	var el             = wp.element.createElement;
	var registerPlugin = wp.plugins.registerPlugin;
	var useSelect      = wp.data.useSelect;
	var useDispatch    = wp.data.useDispatch;
	var TextControl    = wp.components.TextControl;
	var Panel          = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! Panel ) {
		return;
	}

	registerPlugin( 'eagleeye-credit', {
		render: function () {
			var meta = useSelect( function ( select ) {
				var editor = select( 'core/editor' );
				return ( editor && editor.getEditedPostAttribute( 'meta' ) ) || {};
			}, [] );

			var editPost = useDispatch( 'core/editor' ).editPost;

			return el(
				Panel,
				{
					name: 'eagleeye-credit',
					title: 'Published by',
					className: 'ee-credit-panel'
				},
				el( TextControl, {
					label: 'Reporter credit',
					help: 'Leave empty to publish as Eagle Eye.',
					value: meta._eagleeye_credit || '',
					onChange: function ( value ) {
						editPost( {
							meta: Object.assign( {}, meta, { _eagleeye_credit: value } )
						} );

						// Keep the classic field in sync so saving never sends a stale value.
						var fallback = document.getElementById( 'eagleeye_credit' );
						if ( fallback ) {
							fallback.value = value;
						}
					}
				} )
			);
		}
	} );
} )( window.wp );
