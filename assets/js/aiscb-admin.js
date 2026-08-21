( function () {
	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	function formatProviderInfo( provider, i18n ) {
		var html = '';
		var note = provider.note;

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

	function formatKnowledgeDateTime( isoText, fallbackText ) {
		if ( ! isoText ) {
			return fallbackText || '';
		}

		try {
			var parsedDate = new Date( isoText );

			if ( Number.isNaN( parsedDate.getTime() ) ) {
				return fallbackText || '';
			}

			return new Intl.DateTimeFormat( undefined, {
				dateStyle: 'medium',
				timeStyle: 'short'
			} ).format( parsedDate );
		} catch ( error ) {
			return fallbackText || '';
		}
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
		var i18n = AISCBAdmin.i18n || {};
		var optionKey = AISCBAdmin.optionKey || 'aiscb_settings';
		var radioSelector = 'input[name="' + optionKey + '[ai_provider]"]';
		var providerInfo = document.getElementById( 'aiscb-provider-info-content' );
		var modelHelp = document.getElementById( 'aiscb_model_help' );
		var modelReference = document.getElementById( 'aiscb_model_reference' );
		var validationResult = document.getElementById( 'aiscb_validation_result' );
		var testResult = document.getElementById( 'aiscb_test_result' );

		function getSelectedProvider() {
			return document.querySelector( radioSelector + ':checked' );
		}

		function isSettingsUIAvailable() {
			return !! document.getElementById( 'aiscb_model' );
		}

		function updateProviderInfo() {
			if ( ! providerInfo ) {
				return;
			}

			var selectedProvider = getSelectedProvider();
			var provider = selectedProvider ? providers[ selectedProvider.value ] : null;
			providerInfo.innerHTML = provider ? formatProviderInfo( provider, i18n ) : '';
		}

		function updateModelOptions() {
			if ( ! isSettingsUIAvailable() ) {
				return;
			}

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

			updateProviderInfo();
		}

		var knowledgeItems = [];
		var knowledgeNotice = document.getElementById( 'aiscb_knowledge_notice' );
		var knowledgeTableBody = document.getElementById( 'aiscb_knowledge_table_body' );
		var knowledgeForm = document.getElementById( 'aiscb_knowledge_form' );
		var knowledgeFormTitle = document.getElementById( 'aiscb_knowledge_form_title' );

		function isKnowledgeUIAvailable() {
			return !! knowledgeForm && !! knowledgeTableBody;
		}

		function showKnowledgeNotice( type, message ) {
			if ( ! knowledgeNotice ) {
				return;
			}

			showNotice( knowledgeNotice, type, message );
		}

		function getKnowledgeHeaders() {
			return {
				'Content-Type': 'application/json',
				'X-WP-Nonce': AISCBAdmin.restNonce
			};
		}

		function focusKnowledgeForm() {
			var questionField = document.getElementById( 'aiscb_knowledge_question' );

			if ( ! knowledgeForm || ! questionField ) {
				return;
			}

			knowledgeForm.scrollIntoView( {
				behavior: 'smooth',
				block: 'start'
			} );

			questionField.focus();
		}

		function resetKnowledgeForm( shouldFocus ) {
			if ( ! isKnowledgeUIAvailable() ) {
				return;
			}

			if ( 'undefined' === typeof shouldFocus ) {
				shouldFocus = true;
			}

			document.getElementById( 'aiscb_knowledge_id' ).value = '';
			document.getElementById( 'aiscb_knowledge_export_uid' ).value = '';
			document.getElementById( 'aiscb_knowledge_status' ).value = 'draft';
			document.getElementById( 'aiscb_knowledge_question' ).value = '';
			document.getElementById( 'aiscb_knowledge_answer' ).value = '';
			document.getElementById( 'aiscb_knowledge_source_post_ids' ).value = '';
			document.getElementById( 'aiscb_knowledge_matching_method_hint' ).value = '';
			document.getElementById( 'aiscb_knowledge_confidence_note' ).value = '';
			document.getElementById( 'aiscb_knowledge_admin_notes' ).value = '';
			document.getElementById( 'aiscb_knowledge_pii_flag' ).checked = false;

			if ( knowledgeFormTitle ) {
				knowledgeFormTitle.textContent = i18n.knowledgeNewEntry;
			}

			if ( shouldFocus ) {
				focusKnowledgeForm();
			}
		}

		function fillKnowledgeForm( item ) {
			if ( ! item || ! isKnowledgeUIAvailable() ) {
				return;
			}

			document.getElementById( 'aiscb_knowledge_id' ).value = item.id || '';
			document.getElementById( 'aiscb_knowledge_export_uid' ).value = item.export_uid || '';
			document.getElementById( 'aiscb_knowledge_status' ).value = item.status || 'draft';
			document.getElementById( 'aiscb_knowledge_question' ).value = item.question_generalized || '';
			document.getElementById( 'aiscb_knowledge_answer' ).value = item.answer_generalized || '';
			document.getElementById( 'aiscb_knowledge_source_post_ids' ).value = Array.isArray( item.source_post_ids ) ? item.source_post_ids.join( ', ' ) : '';
			document.getElementById( 'aiscb_knowledge_matching_method_hint' ).value = item.matching_method_hint || '';
			document.getElementById( 'aiscb_knowledge_confidence_note' ).value = item.confidence_note || '';
			document.getElementById( 'aiscb_knowledge_admin_notes' ).value = item.admin_notes || '';
			document.getElementById( 'aiscb_knowledge_pii_flag' ).checked = !! item.pii_flag;

			if ( knowledgeFormTitle ) {
				knowledgeFormTitle.textContent = i18n.knowledgeEditEntry;
			}

			focusKnowledgeForm();
		}

		function getKnowledgePayload() {
			return {
				export_uid: document.getElementById( 'aiscb_knowledge_export_uid' ).value,
				status: document.getElementById( 'aiscb_knowledge_status' ).value,
				question_generalized: document.getElementById( 'aiscb_knowledge_question' ).value,
				answer_generalized: document.getElementById( 'aiscb_knowledge_answer' ).value,
				source_post_ids: document.getElementById( 'aiscb_knowledge_source_post_ids' ).value,
				matching_method_hint: document.getElementById( 'aiscb_knowledge_matching_method_hint' ).value,
				confidence_note: document.getElementById( 'aiscb_knowledge_confidence_note' ).value,
				admin_notes: document.getElementById( 'aiscb_knowledge_admin_notes' ).value,
				pii_flag: document.getElementById( 'aiscb_knowledge_pii_flag' ).checked ? 1 : 0
			};
		}

		function renderKnowledgeRows( items ) {
			if ( ! knowledgeTableBody ) {
				return;
			}

			if ( ! Array.isArray( items ) || ! items.length ) {
				knowledgeTableBody.innerHTML = '<tr><td colspan="4">' + escapeHtml( i18n.knowledgeNoEntries ) + '</td></tr>';
				return;
			}

			knowledgeTableBody.innerHTML = items.map( function ( item ) {
				var questionExcerpt = item.question_generalized || '';
				var answerExcerpt = item.answer_generalized || '';
				var updatedAtText = formatKnowledgeDateTime( item.updated_at_iso, item.updated_at );
				var statusOptions = Object.keys( AISCBAdmin.knowledgeStatuses || {} ).map( function ( statusKey ) {
					var selected = ( item.status || '' ) === statusKey ? ' selected' : '';
					return '<option value="' + escapeHtml( statusKey ) + '"' + selected + '>' + escapeHtml( AISCBAdmin.knowledgeStatuses[ statusKey ] || statusKey ) + '</option>';
				} ).join( '' );

				if ( questionExcerpt.length > 140 ) {
					questionExcerpt = questionExcerpt.slice( 0, 140 ) + '...';
				}

				if ( answerExcerpt.length > 160 ) {
					answerExcerpt = answerExcerpt.slice( 0, 160 ) + '...';
				}

				return '' +
					'<tr>' +
						'<td>' +
							'<div class="aiscb-knowledge-status-cell">' +
								'<select class="aiscb-knowledge-status-select" data-knowledge-id="' + escapeHtml( String( item.id || '' ) ) + '">' + statusOptions + '</select>' +
								'<button type="button" class="button button-small" data-knowledge-action="status" data-knowledge-id="' + escapeHtml( String( item.id || '' ) ) + '">' + escapeHtml( i18n.knowledgeApplyStatus ) + '</button>' +
								'<div class="aiscb-knowledge-actions">' +
									'<button type="button" class="button button-small" data-knowledge-action="edit" data-knowledge-id="' + escapeHtml( String( item.id || '' ) ) + '">' + escapeHtml( i18n.knowledgeEdit ) + '</button> ' +
									( AISCBAdmin.knowledgeFullAccess ? '<button type="button" class="button button-small" data-knowledge-action="delete" data-knowledge-id="' + escapeHtml( String( item.id || '' ) ) + '">' + escapeHtml( i18n.knowledgeDelete ) + '</button>' : '' ) +
								'</div>' +
							'</div>' +
						'</td>' +
						'<td><div class="aiscb-log-text">' + escapeHtml( questionExcerpt ) + '</div></td>' +
						'<td><div class="aiscb-log-text">' + escapeHtml( answerExcerpt ) + '</div></td>' +
						'<td>' + escapeHtml( updatedAtText ) + '</td>' +
					'</tr>';
			} ).join( '' );
		}

		function getKnowledgeItemById( id ) {
			var numericId = Number( id );

			return knowledgeItems.find( function ( item ) {
				return Number( item.id ) === numericId;
			} );
		}

		async function updateKnowledgeEntryStatus( id ) {
			var item = getKnowledgeItemById( id );
			var select = knowledgeTableBody ? knowledgeTableBody.querySelector( '.aiscb-knowledge-status-select[data-knowledge-id="' + String( id ) + '"]' ) : null;

			if ( ! item || ! select ) {
				return;
			}

			try {
				var response = await fetch( AISCBAdmin.knowledgeBaseEndpoint + '/' + encodeURIComponent( id ), {
					method: 'POST',
					headers: getKnowledgeHeaders(),
					body: JSON.stringify( { status: select.value } )
				} );
				var data = await response.json();

				if ( ! response.ok ) {
					showKnowledgeNotice( 'error', data && data.message ? data.message : i18n.knowledgeSaveFailed );
					return;
				}

				showKnowledgeNotice( 'success', i18n.knowledgeStatusUpdated );
				await loadKnowledgeEntries();
			} catch ( error ) {
				showKnowledgeNotice( 'error', i18n.knowledgeSaveFailed );
			}
		}

		async function loadKnowledgeEntries() {
			if ( ! isKnowledgeUIAvailable() ) {
				return;
			}

			var searchField = document.getElementById( 'aiscb_knowledge_search' );
			var statusField = document.getElementById( 'aiscb_knowledge_status_filter' );
			var params = new URLSearchParams();
			params.set( 'page', '1' );
			params.set( 'per_page', '100' );

			if ( searchField && searchField.value.trim() ) {
				params.set( 'search', searchField.value.trim() );
			}

			if ( statusField && statusField.value ) {
				params.set( 'status', statusField.value );
			}

			try {
				var response = await fetch( AISCBAdmin.knowledgeBaseEndpoint + '?' + params.toString(), {
					method: 'GET',
					headers: {
						'X-WP-Nonce': AISCBAdmin.restNonce
					}
				} );
				var data = await response.json();

				if ( ! response.ok ) {
					showKnowledgeNotice( 'error', data && data.message ? data.message : i18n.knowledgeLoadFailed );
					return;
				}

				knowledgeItems = Array.isArray( data.items ) ? data.items : [];
				renderKnowledgeRows( knowledgeItems );
			} catch ( error ) {
				showKnowledgeNotice( 'error', i18n.knowledgeLoadFailed );
			}
		}

		async function saveKnowledgeEntry( event ) {
			if ( event ) {
				event.preventDefault();
			}

			var id = document.getElementById( 'aiscb_knowledge_id' ).value;
			var endpoint = id ? AISCBAdmin.knowledgeBaseEndpoint + '/' + encodeURIComponent( id ) : AISCBAdmin.knowledgeBaseEndpoint;
			var button = document.getElementById( 'aiscb_knowledge_save' );

			button.disabled = true;

			try {
				var response = await fetch( endpoint, {
					method: 'POST',
					headers: getKnowledgeHeaders(),
					body: JSON.stringify( getKnowledgePayload() )
				} );
				var data = await response.json();

				if ( ! response.ok ) {
					showKnowledgeNotice( 'error', data && data.message ? data.message : i18n.knowledgeSaveFailed );
					return;
				}

				showKnowledgeNotice( 'success', i18n.knowledgeSaved );
				resetKnowledgeForm();
				await loadKnowledgeEntries();
			} catch ( error ) {
				showKnowledgeNotice( 'error', i18n.knowledgeSaveFailed );
			} finally {
				button.disabled = false;
			}
		}

		async function deleteKnowledgeEntry( id ) {
			if ( ! window.confirm( i18n.knowledgeConfirmDelete ) ) {
				return;
			}

			try {
				var response = await fetch( AISCBAdmin.knowledgeBaseEndpoint + '/' + encodeURIComponent( id ), {
					method: 'DELETE',
					headers: {
						'X-WP-Nonce': AISCBAdmin.restNonce
					}
				} );
				var data = await response.json();

				if ( ! response.ok ) {
					showKnowledgeNotice( 'error', data && data.message ? data.message : i18n.knowledgeDeleteFailed );
					return;
				}

				showKnowledgeNotice( 'success', i18n.knowledgeDeleted );
				resetKnowledgeForm();
				await loadKnowledgeEntries();
			} catch ( error ) {
				showKnowledgeNotice( 'error', i18n.knowledgeDeleteFailed );
			}
		}

		async function exportKnowledgeEntries() {
			try {
				var response = await fetch( AISCBAdmin.knowledgeBaseExportEndpoint, {
					method: 'GET',
					headers: {
						'X-WP-Nonce': AISCBAdmin.restNonce
					}
				} );
				var data = await response.json();

				if ( ! response.ok || ! data || ! data.content ) {
					showKnowledgeNotice( 'error', i18n.knowledgeExportFailed );
					return;
				}

				var blob = new Blob( [ data.content ], { type: 'text/csv;charset=utf-8;' } );
				var link = document.createElement( 'a' );
				link.href = URL.createObjectURL( blob );
				link.download = data.filename || 'aiscb-knowledge-base.csv';
				document.body.appendChild( link );
				link.click();
				document.body.removeChild( link );
				URL.revokeObjectURL( link.href );
			} catch ( error ) {
				showKnowledgeNotice( 'error', i18n.knowledgeExportFailed );
			}
		}

		async function importKnowledgeEntries() {
			var fileInput = document.getElementById( 'aiscb_knowledge_import_file' );
			var file = fileInput && fileInput.files ? fileInput.files[ 0 ] : null;

			if ( ! file ) {
				showKnowledgeNotice( 'warning', i18n.knowledgeImportMissing );
				return;
			}

			try {
				var content = await file.text();
				var response = await fetch( AISCBAdmin.knowledgeBaseImportEndpoint, {
					method: 'POST',
					headers: getKnowledgeHeaders(),
					body: JSON.stringify( { content: content } )
				} );
				var data = await response.json();

				if ( ! response.ok ) {
					showKnowledgeNotice( 'error', data && data.message ? data.message : i18n.knowledgeImportFailed );
					return;
				}

				if ( data.errors && data.errors.length ) {
					showKnowledgeNotice( 'warning', data.errors.join( ' ' ) );
				} else {
					showKnowledgeNotice( 'success', i18n.knowledgeImported + ' ' + String( data.created || 0 ) + ' created, ' + String( data.updated || 0 ) + ' updated.' );
				}

				fileInput.value = '';
				await loadKnowledgeEntries();
			} catch ( error ) {
				showKnowledgeNotice( 'error', i18n.knowledgeImportFailed );
			}
		}

		async function validateSettings() {
			var button = document.getElementById( 'aiscb_validate_button' );
			var provider = getSelectedProvider();
			var model = document.getElementById( 'aiscb_model' );
			var systemPrompt = document.getElementById( 'aiscb_system_prompt' );

			if ( ! provider || ! model.value.trim() ) {
				showNotice( validationResult, 'warning', i18n.validationMissing );
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
						model: model.value,
						system_prompt: systemPrompt.value
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
			var model = document.getElementById( 'aiscb_model' );
			var systemPrompt = document.getElementById( 'aiscb_system_prompt' );
			var maxSources = document.getElementById( 'aiscb_max_sources' );
			var message = document.getElementById( 'aiscb_test_message' );

			if ( ! provider || ! model.value.trim() ) {
				showNotice( testResult, 'warning', i18n.adminTestMissingModel );
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
						model: model.value,
						system_prompt: systemPrompt.value,
						max_sources: maxSources.value,
						message: message.value
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

		if ( isSettingsUIAvailable() ) {
			document.querySelectorAll( radioSelector ).forEach( function ( radio ) {
				radio.addEventListener( 'change', updateModelOptions );
			} );

			document.getElementById( 'aiscb_validate_button' ).addEventListener( 'click', validateSettings );
			document.getElementById( 'aiscb_test_chat_button' ).addEventListener( 'click', runAdminChatTest );
		}

		if ( isKnowledgeUIAvailable() ) {
			knowledgeTableBody.addEventListener( 'click', function ( event ) {
				var target = event.target;

				if ( ! target || ! target.dataset ) {
					return;
				}

				var action = target.dataset.knowledgeAction;
				var id = target.dataset.knowledgeId;

				if ( 'edit' === action ) {
					fillKnowledgeForm( getKnowledgeItemById( id ) );
					return;
				}

				if ( 'status' === action ) {
					updateKnowledgeEntryStatus( id );
					return;
				}

				if ( 'delete' === action ) {
					deleteKnowledgeEntry( id );
				}
			} );

			knowledgeForm.addEventListener( 'submit', saveKnowledgeEntry );
			document.getElementById( 'aiscb_knowledge_cancel' ).addEventListener( 'click', resetKnowledgeForm );
			document.getElementById( 'aiscb_knowledge_refresh' ).addEventListener( 'click', loadKnowledgeEntries );
			document.getElementById( 'aiscb_knowledge_create' ).addEventListener( 'click', resetKnowledgeForm );

			var knowledgeExportButton = document.getElementById( 'aiscb_knowledge_export' );
			var knowledgeImportButton = document.getElementById( 'aiscb_knowledge_import' );

			if ( knowledgeExportButton ) {
				knowledgeExportButton.addEventListener( 'click', exportKnowledgeEntries );
			}

			if ( knowledgeImportButton ) {
				knowledgeImportButton.addEventListener( 'click', importKnowledgeEntries );
			}

			document.getElementById( 'aiscb_knowledge_search' ).addEventListener( 'change', loadKnowledgeEntries );
			document.getElementById( 'aiscb_knowledge_status_filter' ).addEventListener( 'change', loadKnowledgeEntries );
			loadKnowledgeEntries();
			resetKnowledgeForm( false );
		}

		if ( isSettingsUIAvailable() ) {
			updateModelOptions();
		}
	} );
} )();
