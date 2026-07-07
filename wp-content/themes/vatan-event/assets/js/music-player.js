/**
 * Vatan Event — music player v2.
 *
 * New design: floating mini-player pill + full-screen now-playing +
 * bottom-tab navigation (Browse / Search / Queue).
 */
( function () {
	'use strict';

	const cfg  = window.vatanMusic || {};
	const i18n = cfg.i18n || {};
	const REST = (cfg.restUrl || '/wp-json/vatan/v1/music/').replace(/\/$/, '/');
	const LANG = cfg.lang || '';

	const PLACEHOLDER_COVER =
		'data:image/svg+xml;utf8,' + encodeURIComponent(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">' +
				'<rect width="64" height="64" fill="#1A1A2E"/>' +
				'<path d="M24 18l18-3v22a6 6 0 1 1-3-5V22l-12 2v22a6 6 0 1 1-3-5V18z" fill="#FF2D78"/>' +
			'</svg>'
		);

	const SVG = {
		play:    '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>',
		pause:   '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>',
		prev:    '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h2v14H6zM18 5v14l-10-7z"/></svg>',
		next:    '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 5h2v14h-2zM6 5l10 7-10 7z"/></svg>',
		down:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
		back:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>',
		search:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
		close:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
		home:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
		queue:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
	};

	/* ============================================================
	   Helpers
	   ============================================================ */
	function el( tag, attrs, children ) {
		const node = document.createElement( tag );
		if ( attrs ) {
			for ( const k in attrs ) {
				if ( k === 'class' ) node.className = attrs[ k ];
				else if ( k === 'text' ) node.textContent = attrs[ k ];
				else if ( k === 'html' ) node.innerHTML = attrs[ k ];
				else if ( k.startsWith( 'on:' ) ) node.addEventListener( k.slice( 3 ), attrs[ k ] );
				else if ( k.startsWith( 'data-' ) || k === 'role' || k === 'aria-label' || k === 'aria-hidden' || k === 'type' || k === 'value' || k === 'placeholder' || k === 'hidden' || k === 'min' || k === 'max' || k === 'step' || k === 'loading' ) {
					if ( attrs[ k ] === false || attrs[ k ] == null ) continue;
					node.setAttribute( k, attrs[ k ] );
				} else {
					node[ k ] = attrs[ k ];
				}
			}
		}
		if ( children ) {
			for ( const c of children ) {
				if ( c == null || c === false ) continue;
				node.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c );
			}
		}
		return node;
	}

	function safeUrl( raw ) {
		if ( ! raw || typeof raw !== 'string' ) return '';
		try {
			const u = new URL( raw, window.location.href );
			return ( u.protocol === 'https:' || u.protocol === 'http:' ) ? u.href : '';
		} catch ( e ) { return ''; }
	}

	function formatTime( s ) {
		if ( ! isFinite( s ) || s < 0 ) return '0:00';
		const m = Math.floor( s / 60 );
		const r = Math.floor( s % 60 );
		return m + ':' + ( r < 10 ? '0' + r : r );
	}

	function fetchJson( path, params ) {
		const url = new URL( REST + path.replace( /^\//, '' ), window.location.origin );
		if ( params ) {
			for ( const k in params ) {
				if ( params[ k ] != null && params[ k ] !== '' ) url.searchParams.set( k, params[ k ] );
			}
		}
		if ( LANG && ! url.searchParams.has( 'lang' ) ) url.searchParams.set( 'lang', LANG );
		return fetch( url.toString(), { credentials: 'same-origin' } ).then( function ( r ) {
			if ( ! r.ok ) throw new Error( 'HTTP ' + r.status );
			return r.json();
		} );
	}

	let toastTimer = 0;
	function showToast( msg ) {
		let toast = document.querySelector( '.vatan-music__toast' );
		if ( ! toast ) {
			toast = el( 'div', { class: 'vatan-music__toast' } );
			document.body.appendChild( toast );
		}
		toast.textContent = msg;
		toast.classList.add( 'is-visible' );
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () { toast.classList.remove( 'is-visible' ); }, 3000 );
	}

	let feedCache = null;
	let feedCacheTime = 0;
	const FEED_CACHE_TTL = 60000;

	// Circular progress SVG for pill cover
	function progressRing( pct ) {
		const circumference = 163;
		const offset = circumference - ( pct / 100 ) * circumference;
		return '<svg viewBox="0 0 58 58"><circle cx="29" cy="29" r="26" stroke-dashoffset="' + offset + '"/></svg>';
	}

	/* ============================================================
	   Player
	   ============================================================ */
	function Player( root ) {
		this.root      = root;
		this.audio     = new Audio();
		this.audio.preload = 'none';
		this.queue     = [];
		this.index     = -1;
		this.fsOpen    = false;
		this.tab       = 'browse';
		this.feed      = null;
		this.searchHit = null;
		this.albumOpen = null;
		this.artistOpen = null;
		this.loading   = false;
		this.dom       = {};

		this.build();
		this.bindAudio();
		this.bindMediaSession();
		this.bindKeyboard();
		this.loadFeed();
	}

	Player.prototype.build = function () {
		// --- Mini-player pill ---
		const pillCoverImg = el( 'img', { class: 'vatan-music__pill-cover-img', alt: '', src: PLACEHOLDER_COVER, loading: 'lazy' } );
		const pillCoverSvg = document.createElement( 'span' );
		pillCoverSvg.innerHTML = progressRing( 0 );
		const pillCover = el( 'div', { class: 'vatan-music__pill-cover' }, [ pillCoverImg, pillCoverSvg ] );

		const pillTitle  = el( 'div', { class: 'vatan-music__pill-title', text: i18n.nowPlaying || 'Now playing' } );
		const pillArtist = el( 'div', { class: 'vatan-music__pill-artist', text: '' } );
		const pillMeta   = el( 'div', { class: 'vatan-music__pill-meta' }, [ pillTitle, pillArtist ] );

		const pillBtnPrev  = el( 'button', { class: 'vatan-music__pill-btn', type: 'button', 'aria-label': i18n.previous || 'Previous', html: SVG.prev, 'on:click': this.prev.bind( this ) } );
		const pillBtnPlay  = el( 'button', { class: 'vatan-music__pill-btn vatan-music__pill-btn--play', type: 'button', 'aria-label': i18n.play || 'Play', html: SVG.play, 'on:click': this.toggle.bind( this ) } );
		const pillBtnNext  = el( 'button', { class: 'vatan-music__pill-btn', type: 'button', 'aria-label': i18n.next || 'Next', html: SVG.next, 'on:click': this.next.bind( this ) } );
		const pillControls = el( 'div', { class: 'vatan-music__pill-controls' }, [ pillBtnPrev, pillBtnPlay, pillBtnNext ] );

		const pill = el( 'aside', { class: 'vatan-music__pill', role: 'region', 'aria-label': i18n.nowPlaying || 'Now playing' }, [
			pillCover, pillMeta, pillControls,
		] );

		// Tap pill to open full-screen
		pillCover.addEventListener( 'click', this.openFs.bind( this ) );
		pillMeta.addEventListener( 'click', this.openFs.bind( this ) );

		// --- Full-screen view ---
		const fsBg = el( 'div', { class: 'vatan-music__fs-bg' } );

		const fsClose = el( 'button', { class: 'vatan-music__fs-close', type: 'button', 'aria-label': i18n.close || 'Close', html: SVG.down, 'on:click': this.closeFs.bind( this ) } );
		const fsLabel = el( 'div', { class: 'vatan-music__fs-label', text: i18n.nowPlaying || 'Now playing' } );
		const fsMenu  = el( 'button', { class: 'vatan-music__fs-menu', type: 'button', 'aria-label': 'Menu', html: '•••' } );
		const fsHeader = el( 'div', { class: 'vatan-music__fs-header' }, [ fsClose, fsLabel, fsMenu ] );

		const fsArt = el( 'img', { class: 'vatan-music__fs-art', alt: '', src: PLACEHOLDER_COVER, loading: 'lazy' } );
		const fsTitle  = el( 'h2', { class: 'vatan-music__fs-title', text: '' } );
		const fsArtist = el( 'p', { class: 'vatan-music__fs-artist', text: '' } );
		const fsInfo = el( 'div', { class: 'vatan-music__fs-info' }, [ fsTitle, fsArtist ] );

		const fsSeekCur = el( 'span', { class: 'vatan-music__fs-seek-time', text: '0:00' } );
		const fsSeekDur = el( 'span', { class: 'vatan-music__fs-seek-time', text: '0:00' } );
		const fsSeekBar = el( 'input', { class: 'vatan-music__fs-seek-bar', type: 'range', min: '0', max: '1000', value: '0', step: '1', 'aria-label': 'Seek', 'on:input': this.onSeek.bind( this ) } );
		const fsSeek = el( 'div', { class: 'vatan-music__fs-seek' }, [ fsSeekCur, fsSeekBar, fsSeekDur ] );

		const fsBtnPrev = el( 'button', { class: 'vatan-music__fs-btn vatan-music__fs-btn--ghost', type: 'button', 'aria-label': i18n.previous || 'Previous', html: SVG.prev, 'on:click': this.prev.bind( this ) } );
		const fsBtnPlay = el( 'button', { class: 'vatan-music__fs-btn vatan-music__fs-btn--play', type: 'button', 'aria-label': i18n.play || 'Play', html: SVG.play, 'on:click': this.toggle.bind( this ) } );
		const fsBtnNext = el( 'button', { class: 'vatan-music__fs-btn vatan-music__fs-btn--ghost', type: 'button', 'aria-label': i18n.next || 'Next', html: SVG.next, 'on:click': this.next.bind( this ) } );
		const fsControls = el( 'div', { class: 'vatan-music__fs-controls' }, [ fsBtnPrev, fsBtnPlay, fsBtnNext ] );

		// Player panel (now-playing)
		const fsPlayerInfo = el( 'div', { class: 'vatan-music__fs-player-info' }, [
			fsTitle, fsArtist,
		] );
		const fsVisual = el( 'div', { class: 'vatan-music__fs-player-visual' } );
		// Add 12 visualizer bars
		for ( let i = 0; i < 12; i++ ) {
			fsVisual.appendChild( el( 'div', { class: 'vatan-music__viz-bar' } ) );
		}
		const fsPlayerRow = el( 'div', { class: 'vatan-music__fs-player-row' }, [
			fsArt, el( 'div', { class: 'vatan-music__fs-player-side' }, [ fsPlayerInfo, fsVisual ] ),
		] );
		const fsPlayer = el( 'div', { class: 'vatan-music__fs-player' }, [
			fsHeader, fsPlayerRow, fsSeek, fsControls,
		] );

		// Browser panel (tabs + content)
		const tabBrowse = el( 'button', { class: 'vatan-music__fs-tab is-active', type: 'button', 'data-tab': 'browse', html: SVG.home + '<span>' + ( i18n.browse || 'Browse' ) + '</span>', 'on:click': () => this.setTab( 'browse' ) } );
		const tabSearch = el( 'button', { class: 'vatan-music__fs-tab', type: 'button', 'data-tab': 'search', html: SVG.search + '<span>' + ( i18n.search || 'Search' ) + '</span>', 'on:click': () => this.setTab( 'search' ) } );
		const tabQueue  = el( 'button', { class: 'vatan-music__fs-tab', type: 'button', 'data-tab': 'queue', html: SVG.queue + '<span>' + ( i18n.queue || 'Queue' ) + '</span>', 'on:click': () => this.setTab( 'queue' ) } );
		const fsTabs = el( 'div', { class: 'vatan-music__fs-tabs' }, [ tabBrowse, tabSearch, tabQueue ] );
		const fsContent = el( 'div', { class: 'vatan-music__fs-content' } );
		const fsBrowser = el( 'div', { class: 'vatan-music__fs-browser' }, [ fsTabs, fsContent ] );

		const fs = el( 'div', { class: 'vatan-music__fs', role: 'dialog', 'aria-label': i18n.nowPlaying || 'Music player', 'aria-hidden': 'true', hidden: '' }, [
			fsBg, fsPlayer, fsBrowser,
		] );

		// Swipe down to close
		let touchStartY = 0;
		fs.addEventListener( 'touchstart', function ( e ) { touchStartY = e.touches[0].clientY; }, { passive: true } );
		fs.addEventListener( 'touchend', function ( e ) {
			const dy = e.changedTouches[0].clientY - touchStartY;
			if ( dy > 100 ) this.closeFs();
		}.bind( this ) );

		this.root.appendChild( pill );
		this.root.appendChild( fs );

		this.dom = {
			pill, pillCoverImg, pillCoverSvg, pillTitle, pillArtist, pillBtnPlay,
			fs, fsBg, fsArt, fsTitle, fsArtist, fsBtnPlay,
			fsSeekCur, fsSeekDur, fsSeekBar, fsContent,
			tabBrowse, tabSearch, tabQueue,
		};
	};

	Player.prototype.bindAudio = function () {
		const self = this;
		[ 'play', 'playing', 'pause' ].forEach( function ( ev ) {
			self.audio.addEventListener( ev, function () { self.updatePlayButton(); } );
		} );
		this.audio.addEventListener( 'ended', function () { self.next(); } );
		this.audio.addEventListener( 'timeupdate', function () { self.updateProgress(); } );
		this.audio.addEventListener( 'loadedmetadata', function () { self.updateProgress(); } );
		this.audio.addEventListener( 'error', function () {
			self.dom.pillTitle.textContent = i18n.loadingFailed || 'Loading failed.';
			showToast( i18n.loadingFailed || 'Loading failed.' );
		} );
	};

	Player.prototype.bindMediaSession = function () {
		if ( ! ( 'mediaSession' in navigator ) ) return;
		const self = this;
		try {
			const handlers = {
				'play':          function () { self.toggle(); },
				'pause':         function () { self.toggle(); },
				'previoustrack': function () { self.prev(); },
				'nexttrack':     function () { self.next(); },
				'stop':          function () { self.audio.pause(); },
				'seekto':        function ( d ) { if ( d && typeof d.seekTime === 'number' && isFinite( self.audio.duration ) ) self.audio.currentTime = Math.max( 0, Math.min( d.seekTime, self.audio.duration ) ); },
				'seekbackward':  function ( d ) { self.audio.currentTime = Math.max( 0, self.audio.currentTime - ( ( d && d.seekOffset ) || 10 ) ); },
				'seekforward':   function ( d ) { if ( isFinite( self.audio.duration ) ) self.audio.currentTime = Math.min( self.audio.duration, self.audio.currentTime + ( ( d && d.seekOffset ) || 10 ) ); },
			};
			for ( var action in handlers ) navigator.mediaSession.setActionHandler( action, handlers[ action ] );
		} catch ( e ) {}
	};

	Player.prototype.bindKeyboard = function () {
		const self = this;
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable ) return;
			switch ( e.code ) {
				case 'Space': e.preventDefault(); self.toggle(); break;
				case 'ArrowLeft': e.preventDefault(); e.shiftKey ? self.prev() : self.seekRelative( -10 ); break;
				case 'ArrowRight': e.preventDefault(); e.shiftKey ? self.next() : self.seekRelative( 10 ); break;
				case 'Escape': if ( self.fsOpen ) self.closeFs(); break;
			}
		} );
	};

	Player.prototype.seekRelative = function ( sec ) {
		if ( ! isFinite( this.audio.duration ) ) return;
		this.audio.currentTime = Math.max( 0, Math.min( this.audio.duration, this.audio.currentTime + sec ) );
	};

	/* ---------- Playback ---------- */

	Player.prototype.playTrack = function ( track ) {
		if ( ! track ) return;
		const url = safeUrl( track.audio_url );
		if ( ! url ) { showToast( i18n.loadingFailed || 'Loading failed.' ); return; }
		this.audio.src = url;
		const p = this.audio.play();
		if ( p && typeof p.catch === 'function' ) p.catch( function () {} );
		this.updateNowPlaying( track );
		this.updateMediaSessionMetadata( track );
	};

	Player.prototype.playQueue = function ( tracks, startIndex ) {
		if ( ! Array.isArray( tracks ) || ! tracks.length ) return;
		this.queue = tracks.filter( function ( t ) { return t && safeUrl( t.audio_url ); } );
		this.index = Math.max( 0, Math.min( startIndex | 0, this.queue.length - 1 ) );
		this.playTrack( this.queue[ this.index ] );
		if ( this.tab === 'queue' ) this.renderContent();
	};

	Player.prototype.toggle = function () {
		if ( ! this.audio.src ) {
			if ( this.feed && this.feed.recent_tracks && this.feed.recent_tracks.length ) {
				this.playQueue( this.feed.recent_tracks, 0 );
			}
			return;
		}
		if ( this.audio.paused ) {
			var p = this.audio.play();
			if ( p && typeof p.catch === 'function' ) p.catch( function () {} );
		} else {
			this.audio.pause();
		}
	};

	Player.prototype.next = function () {
		if ( this.index < 0 || this.index >= this.queue.length - 1 ) { this.audio.pause(); return; }
		this.index++;
		this.playTrack( this.queue[ this.index ] );
		if ( this.tab === 'queue' ) this.renderContent();
	};

	Player.prototype.prev = function () {
		if ( this.audio.currentTime > 3 ) { this.audio.currentTime = 0; return; }
		if ( this.index > 0 ) { this.index--; this.playTrack( this.queue[ this.index ] ); }
		else { this.audio.currentTime = 0; }
		if ( this.tab === 'queue' ) this.renderContent();
	};

	Player.prototype.onSeek = function ( e ) {
		const v = parseFloat( e.target.value ) / 1000;
		if ( this.audio.duration && isFinite( this.audio.duration ) ) {
			this.audio.currentTime = v * this.audio.duration;
		}
	};

	/* ---------- UI state ---------- */

	Player.prototype.updatePlayButton = function () {
		const playing = ! this.audio.paused && ! this.audio.ended;
		const icon = playing ? SVG.pause : SVG.play;
		const label = playing ? ( i18n.pause || 'Pause' ) : ( i18n.play || 'Play' );
		[ this.dom.pillBtnPlay, this.dom.fsBtnPlay ].forEach( function ( b ) {
			if ( ! b ) return;
			b.innerHTML = icon;
			b.setAttribute( 'aria-label', label );
		} );
		this.root.classList.toggle( 'is-playing', playing );
		if ( 'mediaSession' in navigator ) navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';
	};

	Player.prototype.updateNowPlaying = function ( track ) {
		const cover = safeUrl( track.cover_url ) || PLACEHOLDER_COVER;
		this.dom.pillCoverImg.src = cover;
		this.dom.fsArt.src = cover;
		this.dom.fsBg.style.backgroundImage = 'url(' + cover + ')';
		this.dom.pillTitle.textContent = track.title || '';
		this.dom.fsTitle.textContent = track.title || '';
		const artistName = track.artist && track.artist.title ? track.artist.title : '';
		this.dom.pillArtist.textContent = artistName;
		this.dom.fsArtist.textContent = artistName;
	};

	Player.prototype.updateProgress = function () {
		const d = this.audio.duration;
		const t = this.audio.currentTime;
		let pct = 0;
		if ( isFinite( d ) && d > 0 ) {
			pct = ( t / d ) * 100;
			this.dom.fsSeekBar.value = String( Math.round( ( t / d ) * 1000 ) );
		}
		this.dom.fsSeekBar.style.setProperty( '--mp-seek-pct', pct + '%' );
		this.dom.fsSeekCur.textContent = formatTime( t );
		this.dom.fsSeekDur.textContent = formatTime( d );
		// Update pill progress ring
		this.dom.pillCoverSvg.innerHTML = progressRing( pct );
		// MediaSession position
		if ( 'mediaSession' in navigator && typeof navigator.mediaSession.setPositionState === 'function' && isFinite( d ) && d > 0 ) {
			try { navigator.mediaSession.setPositionState( { duration: d, position: t, playbackRate: this.audio.playbackRate || 1 } ); } catch ( e ) {}
		}
	};

	Player.prototype.updateMediaSessionMetadata = function ( track ) {
		if ( ! ( 'mediaSession' in navigator ) || typeof window.MediaMetadata !== 'function' ) return;
		const cover = safeUrl( track.cover_url );
		try {
			navigator.mediaSession.metadata = new window.MediaMetadata( {
				title: track.title || '',
				artist: ( track.artist && track.artist.title ) || '',
				album: ( track.album && track.album.title ) || '',
				artwork: cover ? [ { src: cover, sizes: '512x512', type: 'image/png' } ] : [],
			} );
		} catch ( e ) {}
	};

	/* ---------- Full-screen ---------- */

	Player.prototype.openFs = function () {
		this.fsOpen = true;
		this.dom.fs.removeAttribute( 'hidden' );
		document.body.classList.add( 'vatan-music-fs-open' );
		requestAnimationFrame( function () { this.dom.fs.setAttribute( 'aria-hidden', 'false' ); }.bind( this ) );
		this.renderContent();
	};

	Player.prototype.closeFs = function () {
		this.fsOpen = false;
		this.dom.fs.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'vatan-music-fs-open' );
		setTimeout( function () { this.dom.fs.setAttribute( 'hidden', '' ); }.bind( this ), 350 );
	};

	Player.prototype.setTab = function ( name ) {
		this.tab = name;
		this.dom.tabBrowse.classList.toggle( 'is-active', name === 'browse' );
		this.dom.tabSearch.classList.toggle( 'is-active', name === 'search' );
		this.dom.tabQueue.classList.toggle( 'is-active', name === 'queue' );
		this.albumOpen = null;
		this.artistOpen = null;
		this.renderContent();
	};

	Player.prototype.setLoading = function ( v ) {
		this.loading = v;
		this.root.classList.toggle( 'is-loading', v );
	};

	/* ---------- Data ---------- */

	Player.prototype.loadFeed = function () {
		const self = this;
		if ( feedCache && ( Date.now() - feedCacheTime < FEED_CACHE_TTL ) ) {
			self.feed = feedCache;
			if ( self.fsOpen && self.tab === 'browse' ) self.renderContent();
			return;
		}
		this.setLoading( true );
		fetchJson( 'feed' ).then( function ( feed ) {
			feedCache = feed;
			feedCacheTime = Date.now();
			self.feed = feed;
			self.setLoading( false );
			if ( self.fsOpen && self.tab === 'browse' ) self.renderContent();
		} ).catch( function () {
			self.setLoading( false );
		} );
	};

	let searchTimer = 0;
	Player.prototype.onSearchInput = function ( e ) {
		const q = ( e.target.value || '' ).trim();
		clearTimeout( searchTimer );
		if ( ! q ) { this.searchHit = null; this.renderContent(); return; }
		const self = this;
		this.setLoading( true );
		searchTimer = setTimeout( function () {
			fetchJson( 'search', { q: q, limit: 8 } ).then( function ( hit ) {
				self.searchHit = hit;
				self.setLoading( false );
				self.renderContent();
			} ).catch( function () {
				self.searchHit = { tracks: [], albums: [], artists: [] };
				self.setLoading( false );
				self.renderContent();
			} );
		}, 250 );
	};

	Player.prototype.openAlbum = function ( id ) {
		const self = this;
		this.setLoading( true );
		fetchJson( 'albums/' + ( id | 0 ) ).then( function ( album ) {
			self.albumOpen = album;
			self.artistOpen = null;
			self.setLoading( false );
			self.renderContent();
		} ).catch( function () { self.setLoading( false ); showToast( i18n.loadingFailed || 'Loading failed.' ); } );
	};

	Player.prototype.openArtist = function ( id ) {
		const self = this;
		this.setLoading( true );
		fetchJson( 'artists/' + ( id | 0 ) ).then( function ( artist ) {
			self.artistOpen = artist;
			self.albumOpen = null;
			self.setLoading( false );
			self.renderContent();
		} ).catch( function () { self.setLoading( false ); showToast( i18n.loadingFailed || 'Loading failed.' ); } );
	};

	Player.prototype.playAlbumById = function ( id ) {
		const self = this;
		fetchJson( 'albums/' + ( id | 0 ) ).then( function ( album ) {
			var tracks = ( album && album.tracks ) || [];
			if ( tracks.length ) self.playQueue( tracks, 0 );
		} ).catch( function () {} );
	};

	Player.prototype.playGenre = function ( slug ) {
		const self = this;
		this.setLoading( true );
		fetchJson( 'tracks', { genre: slug, per_page: 20 } ).then( function ( tracks ) {
			self.setLoading( false );
			if ( ! tracks.length ) { showToast( i18n.noResults || 'No results.' ); return; }
			self.playQueue( tracks, 0 );
		} ).catch( function () { self.setLoading( false ); } );
	};

	/* ---------- Render ---------- */

	Player.prototype.renderContent = function () {
		const v = this.dom.fsContent;
		v.textContent = '';
		if ( this.loading ) { v.appendChild( this.renderSkeleton() ); return; }
		if ( this.albumOpen ) return this.renderAlbumDetail( v );
		if ( this.artistOpen ) return this.renderArtistDetail( v );
		if ( this.tab === 'browse' ) return this.renderBrowse( v );
		if ( this.tab === 'search' ) return this.renderSearch( v );
		if ( this.tab === 'queue' ) return this.renderQueue( v );
	};

	Player.prototype.renderSkeleton = function () {
		const s = el( 'div', { class: 'vatan-music__fs-skeleton' } );
		for ( let i = 0; i < 5; i++ ) {
			s.appendChild( el( 'div', { class: 'vatan-music__fs-skeleton-row' }, [
				el( 'div', { class: 'vatan-music__fs-skeleton-cover' } ),
				el( 'div', { class: 'vatan-music__fs-skeleton-text' }, [
					el( 'div', { class: 'vatan-music__fs-skeleton-line' } ),
					el( 'div', { class: 'vatan-music__fs-skeleton-line vatan-music__fs-skeleton-line--short' } ),
				] ),
			] ) );
		}
		return s;
	};

	Player.prototype.renderBrowse = function ( v ) {
		const f = this.feed;
		if ( ! f ) { v.textContent = '…'; return; }
		const self = this;

		// Featured albums rail
		if ( f.featured_albums && f.featured_albums.length ) {
			v.appendChild( this.renderHeading( i18n.featuredAlbums || 'Featured albums' ) );
			v.appendChild( el( 'div', { class: 'vatan-music__fs-rail' }, f.featured_albums.map( function ( a ) {
				return self.renderCard( a.cover_url, a.title, a.artist && a.artist.title || '', false, function () { self.openAlbum( a.id ); } );
			} ) ) );
		}

		// Featured artists rail
		if ( f.featured_artists && f.featured_artists.length ) {
			v.appendChild( this.renderHeading( i18n.featuredArtists || 'Featured artists' ) );
			v.appendChild( el( 'div', { class: 'vatan-music__fs-rail' }, f.featured_artists.map( function ( a ) {
				return self.renderCard( a.photo_url, a.title, a.country || '', true, function () { self.openArtist( a.id ); } );
			} ) ) );
		}

		// Recent tracks
		if ( f.recent_tracks && f.recent_tracks.length ) {
			v.appendChild( this.renderHeading( i18n.recentTracks || 'Recent tracks' ) );
			const list = el( 'div', { class: 'vatan-music__fs-list' } );
			f.recent_tracks.forEach( function ( t, i ) {
				list.appendChild( self.renderRow( t, function () { self.playQueue( f.recent_tracks, i ); } ) );
			} );
			v.appendChild( list );
		}

		// Recent albums rail
		if ( f.recent_albums && f.recent_albums.length ) {
			v.appendChild( this.renderHeading( i18n.recentAlbums || 'Recent albums' ) );
			v.appendChild( el( 'div', { class: 'vatan-music__fs-rail' }, f.recent_albums.map( function ( a ) {
				return self.renderCard( a.cover_url, a.title, a.artist && a.artist.title || '', false, function () { self.openAlbum( a.id ); } );
			} ) ) );
		}

		// Genres
		if ( f.genres && f.genres.length ) {
			v.appendChild( this.renderHeading( i18n.genres || 'Genres' ) );
			const chips = el( 'div', { class: 'vatan-music__fs-chips' } );
			f.genres.forEach( function ( g ) {
				chips.appendChild( el( 'button', { class: 'vatan-music__fs-chip', type: 'button', 'on:click': function () { self.playGenre( g.slug ); } }, [
					g.emoji ? el( 'span', null, [ g.emoji ] ) : null,
					el( 'span', null, [ g.name ] ),
					el( 'span', { style: 'color:var(--mp-muted);font-size:11px' }, [ '(' + ( g.track_count | 0 ) + ')' ] ),
				] ) );
			} );
			v.appendChild( chips );
		}
	};

	Player.prototype.renderSearch = function ( v ) {
		const self = this;
		const searchWrap = el( 'div', { class: 'vatan-music__fs-search' } );
		const searchIcon = el( 'span', { class: 'vatan-music__fs-search-icon', html: SVG.search } );
		const searchInput = el( 'input', { class: 'vatan-music__fs-search-input', type: 'search', placeholder: i18n.searchPh || 'Search music…', 'aria-label': i18n.search || 'Search', 'on:input': this.onSearchInput.bind( this ) } );
		searchWrap.appendChild( el( 'div', { class: 'vatan-music__fs-search-wrap' }, [ searchIcon, searchInput ] ) );
		v.appendChild( searchWrap );
		searchInput.focus();

		const h = this.searchHit;
		if ( ! h || ( ! h.tracks.length && ! h.albums.length && ! h.artists.length ) ) {
			if ( h ) v.appendChild( el( 'p', { class: 'vatan-music__fs-empty', text: i18n.noResults || 'No results.' } ) );
			return;
		}
		if ( h.tracks.length ) {
			v.appendChild( this.renderHeading( i18n.tracks || 'Tracks' ) );
			const list = el( 'div', { class: 'vatan-music__fs-list' } );
			h.tracks.forEach( function ( t, i ) {
				list.appendChild( self.renderRow( t, function () { self.playQueue( h.tracks, i ); } ) );
			} );
			v.appendChild( list );
		}
		if ( h.albums.length ) {
			v.appendChild( this.renderHeading( i18n.albums || 'Albums' ) );
			v.appendChild( el( 'div', { class: 'vatan-music__fs-grid' }, h.albums.map( function ( a ) {
				return self.renderCard( a.cover_url, a.title, a.artist && a.artist.title || '', false, function () { self.openAlbum( a.id ); } );
			} ) ) );
		}
		if ( h.artists.length ) {
			v.appendChild( this.renderHeading( i18n.artists || 'Artists' ) );
			v.appendChild( el( 'div', { class: 'vatan-music__fs-grid' }, h.artists.map( function ( a ) {
				return self.renderCard( a.photo_url, a.title, a.country || '', true, function () { self.openArtist( a.id ); } );
			} ) ) );
		}
	};

	Player.prototype.renderQueue = function ( v ) {
		if ( ! this.queue.length ) {
			v.appendChild( el( 'p', { class: 'vatan-music__fs-empty', text: i18n.queueEmpty || 'Queue is empty.' } ) );
			return;
		}
		const self = this;
		const list = el( 'div', { class: 'vatan-music__fs-list' } );
		this.queue.forEach( function ( t, i ) {
			const row = self.renderRow( t, function () { self.index = i; self.playTrack( t ); self.renderContent(); } );
			if ( i === self.index ) row.classList.add( 'is-playing' );
			list.appendChild( row );
		} );
		v.appendChild( list );
	};

	Player.prototype.renderAlbumDetail = function ( v ) {
		const a = this.albumOpen;
		if ( ! a ) return;
		const self = this;

		v.appendChild( this.renderBackHeader() );

		const cover = el( 'img', { class: 'vatan-music__fs-detail-cover', alt: '', src: safeUrl( a.cover_url ) || PLACEHOLDER_COVER, loading: 'lazy' } );
		const title = el( 'h2', { class: 'vatan-music__fs-detail-title', text: a.title || '' } );
		const meta  = el( 'p', { class: 'vatan-music__fs-detail-meta', text: a.artist && a.artist.title ? a.artist.title : '' } );
		v.appendChild( el( 'div', { class: 'vatan-music__fs-detail-header' }, [ cover, el( 'div', { class: 'vatan-music__fs-detail-info' }, [ title, meta ] ) ] ) );

		const tracks = a.tracks || [];
		if ( tracks.length ) {
			v.appendChild( el( 'button', { class: 'vatan-music__fs-playall', type: 'button', html: SVG.play + ( i18n.play || 'Play' ), 'on:click': function () { self.playQueue( tracks, 0 ); } } ) );
			const list = el( 'div', { class: 'vatan-music__fs-list' } );
			tracks.forEach( function ( t, i ) {
				list.appendChild( self.renderRow( t, function () { self.playQueue( tracks, i ); } ) );
			} );
			v.appendChild( list );
		} else {
			v.appendChild( el( 'p', { class: 'vatan-music__fs-empty', text: i18n.noResults || 'No results.' } ) );
		}
	};

	Player.prototype.renderArtistDetail = function ( v ) {
		const a = this.artistOpen;
		if ( ! a ) return;
		const self = this;

		v.appendChild( this.renderBackHeader() );

		const cover = el( 'img', { class: 'vatan-music__fs-detail-cover vatan-music__fs-detail-cover--round', alt: '', src: safeUrl( a.photo_url ) || PLACEHOLDER_COVER, loading: 'lazy' } );
		const title = el( 'h2', { class: 'vatan-music__fs-detail-title', text: a.title || '' } );
		const meta  = el( 'p', { class: 'vatan-music__fs-detail-meta', text: a.country || '' } );
		v.appendChild( el( 'div', { class: 'vatan-music__fs-detail-header' }, [ cover, el( 'div', { class: 'vatan-music__fs-detail-info' }, [ title, meta ] ) ] ) );

		const topTracks = a.top_tracks || [];
		if ( topTracks.length ) {
			v.appendChild( el( 'button', { class: 'vatan-music__fs-playall', type: 'button', html: SVG.play + ' ' + ( i18n.play || 'Play' ), 'on:click': function () { self.playQueue( topTracks, 0 ); } } ) );
			v.appendChild( this.renderHeading( i18n.tracks || 'Tracks' ) );
			const list = el( 'div', { class: 'vatan-music__fs-list' } );
			topTracks.forEach( function ( t, i ) {
				list.appendChild( self.renderRow( t, function () { self.playQueue( topTracks, i ); } ) );
			} );
			v.appendChild( list );
		}

		const albums = a.albums || [];
		if ( albums.length ) {
			v.appendChild( this.renderHeading( i18n.albums || 'Albums' ) );
			v.appendChild( el( 'div', { class: 'vatan-music__fs-grid' }, albums.map( function ( al ) {
				return self.renderCard( al.cover_url, al.title, '', false, function () { self.openAlbum( al.id ); } );
			} ) ) );
		}

		if ( ! topTracks.length && ! albums.length ) {
			v.appendChild( el( 'p', { class: 'vatan-music__fs-empty', text: i18n.noResults || 'No results.' } ) );
		}
	};

	/* ---------- Render helpers ---------- */

	Player.prototype.renderHeading = function ( text ) {
		return el( 'h3', { class: 'vatan-music__fs-heading', text: text } );
	};

	Player.prototype.renderBackHeader = function () {
		const self = this;
		return el( 'div', { class: 'vatan-music__fs-detail-header', style: 'padding:8px 0 4px' }, [
			el( 'button', { class: 'vatan-music__fs-back', type: 'button', 'aria-label': i18n.browse || 'Back', html: SVG.back, 'on:click': function () { self.albumOpen = null; self.artistOpen = null; self.renderContent(); } } ),
		] );
	};

	Player.prototype.renderCard = function ( coverUrl, title, meta, round, onClick ) {
		return el( 'button', { class: 'vatan-music__fs-card', type: 'button', 'on:click': function ( e ) { e.preventDefault(); e.stopPropagation(); onClick( e ); } }, [
			el( 'img', { class: 'vatan-music__fs-card-cover' + ( round ? ' vatan-music__fs-card-cover--round' : '' ), alt: '', src: safeUrl( coverUrl ) || PLACEHOLDER_COVER, loading: 'lazy' } ),
			el( 'div', { class: 'vatan-music__fs-card-title', text: title || '' } ),
			el( 'div', { class: 'vatan-music__fs-card-meta', text: meta || '' } ),
		] );
	};

	Player.prototype.renderRow = function ( track, onClick ) {
		return el( 'button', { class: 'vatan-music__fs-row', type: 'button', 'on:click': onClick }, [
			el( 'img', { class: 'vatan-music__fs-row-cover', alt: '', src: safeUrl( track.cover_url ) || PLACEHOLDER_COVER, loading: 'lazy' } ),
			el( 'div', { class: 'vatan-music__fs-row-body' }, [
				el( 'div', { class: 'vatan-music__fs-row-title', text: track.title || '' } ),
				el( 'div', { class: 'vatan-music__fs-row-meta', text: ( track.artist && track.artist.title ) || '' } ),
			] ),
			el( 'div', { class: 'vatan-music__fs-row-right' }, [
				track.duration ? el( 'span', null, [ formatTime( track.duration ) ] ) : null,
				track.is_live ? el( 'span', { class: 'vatan-music__fs-row-live' }, [ ICON.live ] ) : null,
			] ),
		] );
	};

	/* ============================================================
	   Boot
	   ============================================================ */
	let instance = null;
	const ICON = { live: i18n.live || 'LIVE' };

	function boot() {
		const root = document.querySelector( '[data-vatan-music-root]' );
		if ( ! root || root.dataset.vatanMusicBooted === '1' ) return;
		root.dataset.vatanMusicBooted = '1';
		instance = new Player( root );
		bindGlobalActions();
	}

	function bindGlobalActions() {
		document.addEventListener( 'click', function ( e ) {
			if ( ! instance ) return;
			const trigger = e.target.closest( '[data-vatan-music-action]' );
			if ( ! trigger ) return;
			const action = trigger.getAttribute( 'data-vatan-music-action' );
			const idAttr = trigger.getAttribute( 'data-vatan-music-album-id' ) || '0';
			const albumId = parseInt( idAttr, 10 ) || 0;
			if ( ! albumId ) return;
			if ( action === 'play-album' ) { e.preventDefault(); e.stopPropagation(); instance.playAlbumById( albumId ); }
			else if ( action === 'open-album' ) { e.preventDefault(); instance.openFs(); instance.openAlbum( albumId ); }
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
