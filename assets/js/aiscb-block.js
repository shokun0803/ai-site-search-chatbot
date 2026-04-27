( function ( blocks, element, i18n, blockEditor, components, config ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var Placeholder = components.Placeholder;
	var labels = config && config.i18n ? config.i18n : {};

	blocks.registerBlockType( 'ai-site-search-chatbot/widget', {
		title: labels.title || __( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ),
		description: labels.description || __( 'Place the site search chatbot without typing the shortcode manually.', 'ai-site-search-chatbot' ),
		icon: 'format-chat',
		category: 'widgets',
		supports: {
			html: false,
			reusable: false,
		},
		edit: function () {
			return el(
				'div',
				useBlockProps(),
				el( Placeholder, {
					icon: 'format-chat',
					label: labels.title || __( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ),
					instructions: labels.instructions || __( 'This block displays the chatbot on the public site. Enable the chatbot and choose the shortcode display mode in the plugin settings before publishing.', 'ai-site-search-chatbot' ),
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor, window.wp.components, window.AISCBBlock );