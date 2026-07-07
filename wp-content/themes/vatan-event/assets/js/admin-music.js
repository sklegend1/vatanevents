/**
 * Frontend admin — music management JS.
 *
 * Loaded by inc/music/admin.php on every /admin/music/* view. Each block
 * below feature-detects its own DOM hook and no-ops when absent, so this
 * one file covers all five views (overview / tracks / albums / artists /
 * genres) plus the three edit forms.
 *
 * Inline scripts in admin templates are off-limits — something in the
 * WordPress output pipeline serializes them to text. All music-admin JS
 * lives here.
 */
( function () {
	'use strict';

	bulkActions();
	trackForm();
	albumTrackReorder();
	artistLinksRepeater();
	batchUpload();

	/* ============================================================
	   Bulk actions — lists (tracks / albums / artists)
	   ============================================================ */

	function bulkActions() {
		var bulkForm = document.getElementById( 'vatan-music-bulk' );
		if ( ! bulkForm ) return;

		function checks()   { return document.querySelectorAll( '[data-vm-bulk-check]' ); }
		function selected() { return Array.prototype.filter.call( checks(), function ( c ) { return c.checked; } ); }

		var label = document.querySelector( '[data-vm-bulk-count]' );
		function refreshCount() {
			var n = selected().length;
			if ( label ) label.textContent = n ? ( '— ' + n + ' selected' ) : '';
		}

		var all = document.querySelector( '[data-vm-bulk-all]' );
		if ( all ) {
			all.addEventListener( 'change', function () {
				checks().forEach( function ( c ) { c.checked = all.checked; } );
				refreshCount();
			} );
		}

		document.addEventListener( 'change', function ( e ) {
			if ( e.target && e.target.matches && e.target.matches( '[data-vm-bulk-check]' ) ) {
				refreshCount();
			}
		} );

		bulkForm.addEventListener( 'submit', function ( e ) {
			// The select uses form="vatan-music-bulk" attribute, so it's
			// associated with the form even though it's outside the <form> tag.
			var action = document.querySelector( '[name=vatan_music_bulk_action]' );
			var sel    = selected();
			if ( ! action || ! action.value || ! sel.length ) {
				e.preventDefault();
				return;
			}
			var prompts = {
				trash:     'Move ' + sel.length + ' item(s) to trash?',
				untrash:   'Restore ' + sel.length + ' item(s)?',
				feature:   'Mark ' + sel.length + ' item(s) as featured?',
				unfeature: 'Remove ' + sel.length + ' item(s) from featured?'
			};
			if ( prompts[ action.value ] && ! confirm( prompts[ action.value ] ) ) {
				e.preventDefault();
			}
		} );

		refreshCount();
	}

	/* ============================================================
	   Track edit form — live-stream toggle + genre chip class sync
	   ============================================================ */

	function trackForm() {
		var toggle    = document.querySelector( '[data-vm-live-toggle]' );
		var fileBlock = document.querySelector( '[data-vm-audio-file]' );
		var urlBlock  = document.querySelector( '[data-vm-stream-url]' );
		if ( toggle && fileBlock && urlBlock ) {
			toggle.addEventListener( 'change', function () {
				if ( toggle.checked ) {
					fileBlock.setAttribute( 'hidden', '' );
					urlBlock.removeAttribute( 'hidden' );
				} else {
					urlBlock.setAttribute( 'hidden', '' );
					fileBlock.removeAttribute( 'hidden' );
				}
			} );
		}

		// Genre chip click toggles the is-on visual.
		document.querySelectorAll( '.vatan-music-form__chip input' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				var chip = cb.closest( '.vatan-music-form__chip' );
				if ( chip ) chip.classList.toggle( 'is-on', cb.checked );
			} );
		} );
	}

	/* ============================================================
	   Album track manager — HTML5 drag-to-reorder
	   ============================================================ */

	function albumTrackReorder() {
		var list = document.querySelector( '[data-vm-track-list]' );
		if ( ! list ) return;
		var dragging = null;

		function renumber() {
			list.querySelectorAll( '[data-vm-track-row]' ).forEach( function ( row, idx ) {
				var input = row.querySelector( '[data-vm-track-pos]' );
				if ( input ) input.value = idx + 1;
			} );
		}

		list.addEventListener( 'dragstart', function ( e ) {
			var row = e.target.closest( '[data-vm-track-row]' );
			if ( ! row ) return;
			dragging = row;
			row.classList.add( 'is-dragging' );
			try { e.dataTransfer.effectAllowed = 'move'; } catch ( err ) {}
		} );

		list.addEventListener( 'dragend', function () {
			if ( dragging ) dragging.classList.remove( 'is-dragging' );
			dragging = null;
			renumber();
		} );

		list.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			if ( ! dragging ) return;
			var target = e.target.closest( '[data-vm-track-row]' );
			if ( ! target || target === dragging ) return;
			var rect  = target.getBoundingClientRect();
			var after = ( e.clientY - rect.top ) > ( rect.height / 2 );
			list.insertBefore( dragging, after ? target.nextSibling : target );
		} );
	}

	/* ============================================================
	   Artist edit form — social links repeater (add/remove rows)
	   ============================================================ */

	function artistLinksRepeater() {
		var list   = document.querySelector( '[data-vm-link-list]' );
		var addBtn = document.querySelector( '[data-vm-link-add]' );
		var tpl    = document.querySelector( '[data-vm-link-template]' );
		if ( ! list || ! addBtn || ! tpl ) return;

		function nextIndex() {
			return list.querySelectorAll( '[data-vm-link-row]' ).length;
		}

		addBtn.addEventListener( 'click', function () {
			var html = tpl.innerHTML.replace( /__INDEX__/g, nextIndex() );
			var wrap = document.createElement( 'div' );
			wrap.innerHTML = html;
			if ( wrap.firstElementChild ) list.appendChild( wrap.firstElementChild );
		} );

		list.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-vm-link-remove]' );
			if ( ! btn ) return;
			var row = btn.closest( '[data-vm-link-row]' );
			if ( row ) row.remove();
		} );
	}

	/* ============================================================
	   Batch upload — drag & drop + file list + progress + ID3 detection
	   ============================================================ */

	function batchUpload() {
		var dropzone = document.querySelector( '[data-vm-dropzone]' );
		var fileInput = document.getElementById( 'vatan-batch-files' );
		var fileList = document.querySelector( '[data-vm-file-list]' );
		var form = document.getElementById( 'vatan-batch-form' );
		var submitBtn = document.getElementById( 'vatan-batch-submit' );
		var progress = document.getElementById( 'vatan-upload-progress' );
		var progressFill = document.getElementById( 'vatan-progress-fill' );
		var progressText = document.getElementById( 'vatan-progress-text' );

		// ID3 detection elements
		var detectedMeta = document.getElementById( 'vatan-detected-meta' );
		var detectedGrid = document.getElementById( 'vatan-detected-grid' );
		var detectedTitle = document.getElementById( 'vatan-detected-title' );
		var detectedArtist = document.getElementById( 'vatan-detected-artist' );
		var detectedAlbum = document.getElementById( 'vatan-detected-album' );
		var detectedDuration = document.getElementById( 'vatan-detected-duration' );
		var detectedTrack = document.getElementById( 'vatan-detected-track' );
		var detectedYear = document.getElementById( 'vatan-detected-year' );
		var detectedCover = document.getElementById( 'vatan-detected-cover' );
		var coverPreview = document.getElementById( 'vatan-cover-preview' );
		var nonceField = document.querySelector( '[name="_vatan_music_batch_nonce"]' );

		if ( ! dropzone || ! fileInput || ! fileList ) return;

		var selectedFiles = [];
		var detectedData = null;

		// Drag & drop handlers
		dropzone.addEventListener( 'dragover', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			dropzone.classList.add( 'is-dragover' );
		} );

		dropzone.addEventListener( 'dragleave', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			dropzone.classList.remove( 'is-dragover' );
		} );

		dropzone.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			dropzone.classList.remove( 'is-dragover' );

			var files = e.dataTransfer.files;
			if ( files.length ) {
				addFiles( files );
			}
		} );

		// Click to browse
		dropzone.addEventListener( 'click', function ( e ) {
			if ( e.target === fileInput ) return;
			fileInput.click();
		} );

		// File input change
		fileInput.addEventListener( 'change', function () {
			if ( fileInput.files.length ) {
				addFiles( fileInput.files );
			}
		} );

		function addFiles( files ) {
			for ( var i = 0; i < files.length; i++ ) {
				var file = files[ i ];
				// Check if already added
				var exists = false;
				for ( var j = 0; j < selectedFiles.length; j++ ) {
					if ( selectedFiles[ j ].name === file.name && selectedFiles[ j ].size === file.size ) {
						exists = true;
						break;
					}
				}
				if ( ! exists ) {
					selectedFiles.push( file );
				}
			}
			syncFilesToInput();
			renderFileList();
			detectMetadata( files[ 0 ] );
		}

		// Sync selectedFiles array back to the actual file input
		// so the browser sends them with normal form submission.
		function syncFilesToInput() {
			if ( typeof DataTransfer !== 'undefined' ) {
				var dt = new DataTransfer();
				selectedFiles.forEach( function ( f ) { dt.items.add( f ); } );
				fileInput.files = dt.files;
			}
		}

		function removeFile( index ) {
			selectedFiles.splice( index, 1 );
			syncFilesToInput();
			renderFileList();
			if ( selectedFiles.length === 0 ) {
				hideDetectedMeta();
			}
		}

		function renderFileList() {
			fileList.innerHTML = '';
			if ( ! selectedFiles.length ) {
				fileList.hidden = true;
				return;
			}
			fileList.hidden = false;

			var header = document.createElement( 'div' );
			header.className = 'vatan-music-form__file-list-header';
			header.textContent = selectedFiles.length + ' file(s) selected';
			fileList.appendChild( header );

			selectedFiles.forEach( function ( file, idx ) {
				var row = document.createElement( 'div' );
				row.className = 'vatan-music-form__file-item';

				var name = document.createElement( 'span' );
				name.className = 'vatan-music-form__file-name';
				name.textContent = file.name;

				var size = document.createElement( 'span' );
				size.className = 'vatan-music-form__file-size';
				size.textContent = formatFileSize( file.size );

				var status = document.createElement( 'span' );
				status.className = 'vatan-music-form__file-status';
				status.textContent = '';

				var remove = document.createElement( 'button' );
				remove.type = 'button';
				remove.className = 'vatan-music-form__file-remove';
				remove.textContent = '×';
				remove.setAttribute( 'aria-label', 'Remove' );
				remove.addEventListener( 'click', function () {
					removeFile( idx );
				} );

				row.appendChild( name );
				row.appendChild( size );
				row.appendChild( status );
				row.appendChild( remove );
				fileList.appendChild( row );
			} );
		}

		function formatFileSize( bytes ) {
			if ( bytes < 1024 ) return bytes + ' B';
			if ( bytes < 1048576 ) return ( bytes / 1024 ).toFixed( 1 ) + ' KB';
			return ( bytes / 1048576 ).toFixed( 1 ) + ' MB';
		}

		function formatDuration( seconds ) {
			if ( ! seconds ) return '—';
			var m = Math.floor( seconds / 60 );
			var s = Math.floor( seconds % 60 );
			return m + ':' + ( s < 10 ? '0' : '' ) + s;
		}

		/* ----- ID3 metadata detection ----- */

		function detectMetadata( file ) {
			if ( ! file || ! nonceField ) return;

			// Only detect from audio files
			if ( ! file.type.match( /^audio\// ) ) return;

			var formData = new FormData();
			formData.append( 'action', 'vatan_music_detect_meta' );
			formData.append( 'nonce', nonceField.value );
			formData.append( 'audio_file', file );

			// Show loading state
			if ( detectedMeta ) {
				detectedMeta.hidden = false;
				detectedGrid.style.opacity = '0.5';
			}

			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', ajaxurl || '/wp-admin/admin-ajax.php', true );

			xhr.addEventListener( 'load', function () {
				if ( detectedGrid ) detectedGrid.style.opacity = '1';

				if ( xhr.status === 200 ) {
					try {
						var resp = JSON.parse( xhr.responseText );
						if ( resp.success && resp.data ) {
							showDetectedMeta( resp.data );
						} else {
							hideDetectedMeta();
						}
					} catch ( err ) {
						hideDetectedMeta();
					}
				} else {
					hideDetectedMeta();
				}
			} );

			xhr.addEventListener( 'error', function () {
				if ( detectedGrid ) detectedGrid.style.opacity = '1';
				hideDetectedMeta();
			} );

			xhr.send( formData );
		}

		function showDetectedMeta( meta ) {
			detectedData = meta;

			if ( detectedTitle ) detectedTitle.value = meta.title || '';
			if ( detectedArtist ) detectedArtist.value = meta.artist || '';
			if ( detectedAlbum ) detectedAlbum.value = meta.album || '';
			if ( detectedDuration ) detectedDuration.value = formatDuration( meta.duration );
			if ( detectedTrack ) detectedTrack.value = meta.track_number || '';
			if ( detectedYear ) detectedYear.value = meta.year || '';

			if ( detectedMeta ) detectedMeta.hidden = false;

			// Auto-select matching artist in dropdown
			if ( meta.artist ) {
				var artistSelect = document.getElementById( 'vm-batch-artist' );
				if ( artistSelect ) {
					var options = artistSelect.options;
					for ( var i = 0; i < options.length; i++ ) {
						if ( options[ i ].text.toLowerCase().trim() === meta.artist.toLowerCase().trim() ) {
							artistSelect.value = options[ i ].value;
							break;
						}
					}
				}
			}

			// Auto-select matching album in dropdown
			if ( meta.album ) {
				var albumSelect = document.getElementById( 'vm-batch-album' );
				if ( albumSelect ) {
					var options = albumSelect.options;
					for ( var i = 0; i < options.length; i++ ) {
						if ( options[ i ].text.toLowerCase().trim() === meta.album.toLowerCase().trim() ) {
							albumSelect.value = options[ i ].value;
							break;
						}
					}
				}
			}
		}

		function hideDetectedMeta() {
			detectedData = null;
			if ( detectedMeta ) detectedMeta.hidden = true;
			if ( detectedCover ) detectedCover.hidden = true;
		}

		// Form submission — let the browser handle it normally so PHP
		// wp_safe_redirect() and session data work correctly.
		if ( form && submitBtn && progress ) {
			form.addEventListener( 'submit', function ( e ) {
				if ( ! selectedFiles.length ) {
					e.preventDefault();
					alert( 'Please select at least one audio file.' );
					return;
				}

				// Show uploading state
				submitBtn.disabled = true;
				submitBtn.textContent = 'Uploading…';
				progress.hidden = false;
				progressFill.style.width = '100%';
				progressText.textContent = 'Uploading ' + selectedFiles.length + ' file(s)…';
			} );
		}
	}
} )();
