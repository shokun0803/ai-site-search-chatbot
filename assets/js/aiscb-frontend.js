( function () {
	function openWidget( widget, shouldOpen ) {
		var launcher = widget.querySelector( '.aiscb-widget__launcher' );
		var panel = widget.querySelector( '.aiscb-widget__panel' );

		if ( ! launcher || ! panel ) {
			return;
		}

		panel.hidden = ! shouldOpen;
		launcher.setAttribute( 'aria-expanded', shouldOpen ? 'true' : 'false' );
		widget.classList.toggle( 'is-open', shouldOpen );
	}

	function addMessage( container, role, text, sources ) {
		var bubble = document.createElement( 'div' );
		bubble.className = 'aiscb-widget__message aiscb-widget__message--' + role;
		bubble.textContent = text;

		if ( Array.isArray( sources ) && sources.length ) {
			var sourceWrap = document.createElement( 'div' );
			sourceWrap.className = 'aiscb-widget__sources';

			var sourceTitle = document.createElement( 'div' );
			sourceTitle.className = 'aiscb-widget__sources-title';
			sourceTitle.textContent = container.closest( '.aiscb-widget' ).dataset.sourceLabel || 'Sources';
			sourceWrap.appendChild( sourceTitle );

			var list = document.createElement( 'ul' );
			list.className = 'aiscb-widget__source-list';

			sources.forEach( function ( source ) {
				var item = document.createElement( 'li' );
				var link = document.createElement( 'a' );
				link.href = source.url;
				link.textContent = source.title;
				item.appendChild( link );
				list.appendChild( item );
			} );

			sourceWrap.appendChild( list );
			bubble.appendChild( sourceWrap );
		}

		container.appendChild( bubble );
		container.scrollTop = container.scrollHeight;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var widgets = document.querySelectorAll( '.aiscb-widget' );

		widgets.forEach( function ( widget ) {
			var endpoint = widget.dataset.endpoint;
			var greeting = widget.dataset.greeting || 'Hi, ask me a question about this site.';
			var thinkingLabel = widget.dataset.thinkingLabel || 'Thinking...';
			var errorLabel = widget.dataset.errorLabel || 'The chatbot is temporarily unavailable. Please try again later.';
			var launcher = widget.querySelector( '.aiscb-widget__launcher' );
			var panel = widget.querySelector( '.aiscb-widget__panel' );
			var close = widget.querySelector( '.aiscb-widget__close' );
			var messages = widget.querySelector( '.aiscb-widget__messages' );
			var form = widget.querySelector( '.aiscb-widget__form' );
			var input = widget.querySelector( '.aiscb-widget__input' );
			var submit = widget.querySelector( '.aiscb-widget__submit' );
			var hasGreeting = false;

			if ( launcher ) {
				launcher.addEventListener( 'click', function () {
					var nextState = launcher.getAttribute( 'aria-expanded' ) !== 'true';
					openWidget( widget, nextState );

					if ( nextState && ! hasGreeting ) {
						addMessage( messages, 'assistant', greeting );
						hasGreeting = true;
					}

					if ( nextState ) {
						input.focus();
					}
				} );
			}

			if ( close ) {
				close.addEventListener( 'click', function () {
					openWidget( widget, false );

					if ( launcher ) {
						launcher.focus();
					}
				} );
			}

			widget.addEventListener( 'keydown', function ( event ) {
				if ( 'Escape' === event.key ) {
					openWidget( widget, false );
				}
			} );

			if ( panel && ! panel.hidden && ! hasGreeting ) {
				addMessage( messages, 'assistant', greeting );
				hasGreeting = true;
			}

			input.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' !== event.key || event.shiftKey || event.isComposing ) {
					return;
				}

				event.preventDefault();
				form.requestSubmit();
			} );

			form.addEventListener( 'submit', async function ( event ) {
				event.preventDefault();

				var value = input.value.trim();

				if ( ! value ) {
					return;
				}

				addMessage( messages, 'user', value );
				input.value = '';
				submit.disabled = true;
				input.disabled = true;

				var pending = document.createElement( 'div' );
				pending.className = 'aiscb-widget__message aiscb-widget__message--assistant';
				pending.textContent = thinkingLabel;
				messages.appendChild( pending );

				try {
					var response = await fetch( endpoint, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json'
						},
						body: JSON.stringify( {
							message: value
						} )
					} );

					var data = await response.json();
					messages.removeChild( pending );
					addMessage( messages, 'assistant', data.answer || 'No answer was returned.', data.sources || [] );
				} catch ( error ) {
					messages.removeChild( pending );
					addMessage( messages, 'assistant', errorLabel );
				} finally {
					submit.disabled = false;
					input.disabled = false;
					input.focus();
				}
			} );
		} );
	} );
} )();
