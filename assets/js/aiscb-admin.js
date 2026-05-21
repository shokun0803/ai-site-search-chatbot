( function () {
	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	function linkifyText( text ) {
		return escapeHtml( text ).replace(
			/(https?:\/\/[^\s<]+)/g,
			function ( url ) {
				return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + url + '</a>';
			}
		);
	}

	function formatProviderInfo( provider, i18n, authMode ) {
		var html = '';
		var isBearerMode = 'bearer_token' === authMode;
		var steps = ( isBearerMode && provider.bearer_token_setup_steps )
			? provider.bearer_token_setup_steps
			: provider.setup_steps;
		var note = ( isBearerMode && provider.bearer_token_note )
			? provider.bearer_token_note
			: provider.note;
		var helpTitle = isBearerMode ? i18n.bearerTokenHelpTitle : i18n.apiKeyHelpTitle;

		if ( steps ) {
			html += '<div class="aiscb-provider-info-item">';
			html += '<strong>' + escapeHtml( helpTitle ) + '</strong>';
			html += '<ol>';
			steps.forEach( function ( step ) {
				html += '<li>' + linkifyText( step ) + '</li>';
			} );
			html += '</ol>';
			html += '</div>';
		}

		if ( note ) {
			html += '<div class="aiscb-provider-info-item aiscb-provider-note">';
			html += '<strong>' + escapeHtml( i18n.noteTitle ) + '</strong>';
			html += '<p>' + escapeHtml( note ) + '</p>';
			html += '</div>';
		}

		return html;
	}

	function showNotice( element, type, message ) {
		element.className = 'notice inline aiscb-notice notice-' + type;
		element.textContent = message;
		element.hidden = false;
	}

	function renderAdminTestAnswer( data, i18n ) {
		var container = document.getElementById( 'aiscb_test_answer' );
		container.innerHTML = '';

		if ( ! data || ! data.answer ) {
			container.hidden = true;
			return;
		}

		var answerTitle = document.createElement( 'strong' );
		answerTitle.textContent = i18n.assistantReply;
		container.appendChild( answerTitle );

		var answerBody = document.createElement( 'div' );
		answerBody.style.marginTop = '8px';
		answerBody.style.whiteSpace = 'pre-wrap';
		answerBody.textContent = data.answer;
		container.appendChild( answerBody );

		if ( Array.isArray( data.sources ) && data.sources.length ) {
			var sourceTitle = document.createElement( 'strong' );
			sourceTitle.style.display = 'block';
			sourceTitle.style.marginTop = '12px';
			sourceTitle.textContent = i18n.referencedSources;
			container.appendChild( sourceTitle );

			var sourceList = document.createElement( 'ul' );
			sourceList.style.margin = '8px 0 0 18px';

			data.sources.forEach( function ( source ) {
				var item = document.createElement( 'li' );
				var link = document.createElement( 'a' );
				link.href = source.url;
				link.textContent = source.title;
				item.appendChild( link );
				sourceList.appendChild( item );
			} );

			container.appendChild( sourceList );
		}

		container.hidden = false;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( 'undefined' === typeof AISCBAdmin ) {
			return;
		}

		var providers = AISCBAdmin.providers || {};
			var credentialStatus = AISCBAdmin.credentialStatus || {};
		var i18n = AISCBAdmin.i18n || {};
		var optionKey = AISCBAdmin.optionKey || 'aiscb_settings';
		var radioSelector = 'input[name="' + optionKey + '[ai_provider]"]';
		var providerInfo = document.getElementById( 'aiscb-provider-info-content' );
		var modelHelp = document.getElementById( 'aiscb_model_help' );
		var modelReference = document.getElementById( 'aiscb_model_reference' );
		var validationResult = document.getElementById( 'aiscb_validation_result' );
		var testResult = document.getElementById( 'aiscb_test_result' );
			var apiKeyStatus = document.getElementById( 'aiscb_api_key_status' );
			var bearerTokenStatus = document.getElementById( 'aiscb_claude_bearer_token_status' );

		var authModeRow = document.getElementById( 'aiscb_claude_auth_mode_row' );
		var apiKeyRow = document.getElementById( 'aiscb_api_key_row' );
		var bearerTokenRow = document.getElementById( 'aiscb_claude_bearer_token_row' );

		function getSelectedProvider() {
			return document.querySelector( radioSelector + ':checked' );
		}

		function isClaudeProvider() {
			var provider = getSelectedProvider();
			return provider && 'claude' === provider.value;
		}

		function getClaudeAuthMode() {
			var bearerRadio = document.getElementById( 'aiscb_claude_auth_mode_bearer_token' );
			return ( bearerRadio && bearerRadio.checked ) ? 'bearer_token' : 'api_key';
		}

		function isBearerTokenMode() {
			return isClaudeProvider() && 'bearer_token' === getClaudeAuthMode();
		}

			function getCredentialStatus( providerKey, credentialType ) {
				var providerState = credentialStatus[ providerKey ] || {};
				return providerState[ credentialType ] || { configured: false, source: 'none' };
			}

			function hasAvailableCredential( providerKey, bearerMode ) {
				var credentialType = bearerMode ? 'bearer_token' : 'api_key';
				var status = getCredentialStatus( providerKey, credentialType );
				return !! status.configured;
			}

			function getCredentialStatusText( providerKey, credentialType ) {
				var status = getCredentialStatus( providerKey, credentialType );

				if ( status.configured ) {
					if ( 'config' === status.source ) {
						return 'bearer_token' === credentialType ? i18n.bearerTokenConfig : i18n.apiKeyConfig;
					}

					return 'bearer_token' === credentialType ? i18n.bearerTokenStored : i18n.apiKeyStored;
				}

				return 'bearer_token' === credentialType ? i18n.bearerTokenEmpty : i18n.apiKeyEmpty;
			}

			function updateCredentialStatusUI() {
				var provider = getSelectedProvider();

				if ( apiKeyStatus ) {
					apiKeyStatus.textContent = provider ? getCredentialStatusText( provider.value, 'api_key' ) : '';
				}

				if ( bearerTokenStatus ) {
					bearerTokenStatus.textContent = provider ? getCredentialStatusText( provider.value, 'bearer_token' ) : '';
				}
			}

		function updateClaudeAuthUI() {
			if ( ! authModeRow || ! apiKeyRow || ! bearerTokenRow ) {
				return;
			}

			if ( isClaudeProvider() ) {
				authModeRow.hidden = false;
				var bearer = isBearerTokenMode();
				bearerTokenRow.hidden = ! bearer;
				apiKeyRow.hidden = bearer;
			} else {
				authModeRow.hidden = true;
				bearerTokenRow.hidden = true;
				apiKeyRow.hidden = false;
			}

				updateCredentialStatusUI();
		}

		function updateProviderInfo() {
			var selectedProvider = getSelectedProvider();
			var provider = selectedProvider ? providers[ selectedProvider.value ] : null;
			var authMode = isClaudeProvider() ? getClaudeAuthMode() : 'api_key';
			providerInfo.innerHTML = provider ? formatProviderInfo( provider, i18n, authMode ) : '';
		}

		function updateModelOptions() {
			var selectedProvider = getSelectedProvider();
			var provider = selectedProvider ? providers[ selectedProvider.value ] : null;

			if ( provider ) {
				modelHelp.textContent = i18n.modelExample + ' ' + ( provider.example_model || '' );

				if ( provider.model_docs_url ) {
					modelReference.innerHTML = escapeHtml( i18n.modelReference ) + ' <a href="' + escapeHtml( provider.model_docs_url ) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml( provider.model_docs_label || provider.model_docs_url ) + '</a>';
				} else {
					modelReference.textContent = '';
				}
			} else {
				modelHelp.textContent = '';
				modelReference.textContent = '';
			}

			updateClaudeAuthUI();
			updateProviderInfo();
		}

		async function validateSettings() {
			var button = document.getElementById( 'aiscb_validate_button' );
			var provider = getSelectedProvider();
			var apiKey = document.getElementById( 'aiscb_api_key' );
			var bearerToken = document.getElementById( 'aiscb_claude_bearer_token' );
			var model = document.getElementById( 'aiscb_model' );
			var systemPrompt = document.getElementById( 'aiscb_system_prompt' );
			var bearerMode = isBearerTokenMode();
			var credential = bearerMode ? bearerToken.value : apiKey.value;

				if ( ! provider || ( ! credential.trim() && ! hasAvailableCredential( provider.value, bearerMode ) ) || ! model.value.trim() ) {
				showNotice( validationResult, 'warning', bearerMode ? i18n.validationMissingBearer : i18n.validationMissing );
				return;
			}

			button.disabled = true;
			showNotice( validationResult, 'warning', i18n.validationRunning );

			try {
				var response = await fetch( AISCBAdmin.validateEndpoint, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': AISCBAdmin.restNonce
					},
					body: JSON.stringify( {
						ai_provider: provider.value,
						api_key: apiKey.value,
						model: model.value,
						system_prompt: systemPrompt.value,
						claude_auth_mode: getClaudeAuthMode(),
						claude_bearer_token: bearerToken ? bearerToken.value : '',
					} )
				} );

				var data = await response.json();

				if ( response.ok && data.success ) {
					showNotice( validationResult, 'success', data.message || i18n.validationSuccess );
					return;
				}

				showNotice( validationResult, 'error', data && data.message ? data.message : i18n.validationFailed );
			} catch ( error ) {
				showNotice( validationResult, 'error', i18n.validationRequestFail );
			} finally {
				button.disabled = false;
			}
		}

		async function runAdminChatTest() {
			var button = document.getElementById( 'aiscb_test_chat_button' );
			var provider = getSelectedProvider();
			var apiKey = document.getElementById( 'aiscb_api_key' );
			var bearerToken = document.getElementById( 'aiscb_claude_bearer_token' );
			var model = document.getElementById( 'aiscb_model' );
			var systemPrompt = document.getElementById( 'aiscb_system_prompt' );
			var maxSources = document.getElementById( 'aiscb_max_sources' );
			var message = document.getElementById( 'aiscb_test_message' );
			var bearerMode = isBearerTokenMode();
			var credential = bearerMode ? bearerToken.value : apiKey.value;

				if ( ! provider || ( ! credential.trim() && ! hasAvailableCredential( provider.value, bearerMode ) ) || ! model.value.trim() ) {
				showNotice( testResult, 'warning', bearerMode ? i18n.adminTestMissingBearer : i18n.adminTestMissingModel );
				renderAdminTestAnswer( null, i18n );
				return;
			}

			if ( ! message.value.trim() ) {
				showNotice( testResult, 'warning', i18n.adminTestMissingText );
				renderAdminTestAnswer( null, i18n );
				return;
			}

			button.disabled = true;
			showNotice( testResult, 'warning', i18n.adminTestRunning );
			renderAdminTestAnswer( null, i18n );

			try {
				var response = await fetch( AISCBAdmin.testChatEndpoint, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': AISCBAdmin.restNonce
					},
					body: JSON.stringify( {
						ai_provider: provider.value,
						api_key: apiKey.value,
						model: model.value,
						system_prompt: systemPrompt.value,
						max_sources: maxSources.value,
						message: message.value,
						claude_auth_mode: getClaudeAuthMode(),
						claude_bearer_token: bearerToken ? bearerToken.value : '',
					} )
				} );

				var data = await response.json();

				if ( response.ok && data.success ) {
					showNotice( testResult, 'success', i18n.adminTestSuccess );
					renderAdminTestAnswer( data, i18n );
					return;
				}

				showNotice( testResult, 'error', data && data.message ? data.message : i18n.adminTestFailed );
				renderAdminTestAnswer( null, i18n );
			} catch ( error ) {
				showNotice( testResult, 'error', i18n.adminTestRequestFail );
				renderAdminTestAnswer( null, i18n );
			} finally {
				button.disabled = false;
			}
		}

		document.querySelectorAll( radioSelector ).forEach( function ( radio ) {
			radio.addEventListener( 'change', updateModelOptions );
		} );

		document.querySelectorAll( 'input[name="' + optionKey + '[claude_auth_mode]"]' ).forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				updateClaudeAuthUI();
				updateProviderInfo();
			} );
		} );

		document.getElementById( 'aiscb_validate_button' ).addEventListener( 'click', validateSettings );
		document.getElementById( 'aiscb_test_chat_button' ).addEventListener( 'click', runAdminChatTest );

		updateModelOptions();
			updateCredentialStatusUI();
	} );
} )();
