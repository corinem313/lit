/**
 * Extends core/image lightbox: fill imageRef before overlay layout (Cover + carousel slides).
 *
 * @see wp-includes/js/dist/script-modules/interactivity/index.js (universalUnlock)
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

const universalUnlock =
	'I acknowledge that using a private store means my plugin will inevitably break on the next store release.';

let state;
let actions;
let callbacks;

try {
	const imageStore = store( 'core/image', {}, { lock: universalUnlock } );
	state = imageStore.state;
	actions = imageStore.actions;
	callbacks = imageStore.callbacks;
} catch ( error ) {
	// eslint-disable-next-line no-console
	console.warn( '[4wp-smart-link] Lightbox gallery extension skipped.', error );
}

/**
 * @param {string} imageId Metadata key.
 * @return {string}
 */
function escapeImageId( imageId ) {
	if ( typeof CSS !== 'undefined' && CSS.escape ) {
		return CSS.escape( imageId );
	}

	return String( imageId ).replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' );
}

/**
 * @param {string} imageId Metadata key.
 * @return {HTMLImageElement|null}
 */
function findImageElementForId( imageId ) {
	const safeId = escapeImageId( imageId );
	const keyed = document.querySelector( `[data-wp-key="${ safeId }"]` );

	if ( keyed ) {
		const region = keyed.closest( '[data-wp-interactive="core/image"]' );
		const scoped = region || keyed.parentElement;

		if ( scoped ) {
			const fromKey =
				scoped.querySelector( 'img.forwp-smart-link-featured-image-lightbox__ref' ) ||
				scoped.querySelector( 'img.wp-block-cover__image-background' ) ||
				scoped.querySelector( 'img.forwp-smart-link-cover-lightbox__ref' ) ||
				scoped.querySelector( 'figure.wp-lightbox-container img' ) ||
				scoped.querySelector( 'img' );

			if ( fromKey ) {
				return fromKey;
			}
		}
	}

	const regions = document.querySelectorAll(
		'[data-wp-interactive="core/image"]'
	);

	for ( const region of regions ) {
		const raw = region.getAttribute( 'data-wp-context' );

		if ( ! raw ) {
			continue;
		}

		let ctx;

		try {
			ctx = JSON.parse( raw );
		} catch {
			continue;
		}

		if ( ctx.imageId !== imageId ) {
			continue;
		}

		const img =
			region.querySelector( 'img.forwp-smart-link-featured-image-lightbox__ref' ) ||
			region.querySelector( 'img.wp-block-cover__image-background' ) ||
			region.querySelector( 'img.forwp-smart-link-cover-lightbox__ref' ) ||
			region.querySelector( 'figure.wp-lightbox-container img' ) ||
			region.querySelector( '.wp-lightbox-container img' ) ||
			region.querySelector( 'img' );

		if ( img ) {
			return img;
		}
	}

	return null;
}

/**
 * @param {string} imageId Metadata key.
 */
function ensureButtonRef( imageId ) {
	if ( ! imageId || ! state?.metadata?.[ imageId ] ) {
		return;
	}

	const entry = state.metadata[ imageId ];

	if ( entry.buttonRef ) {
		return;
	}

	const safeId = escapeImageId( imageId );
	const keyed = document.querySelector( `[data-wp-key="${ safeId }"]` );

	if ( ! keyed ) {
		return;
	}

	const trigger = keyed.querySelector( '.lightbox-trigger' );

	if ( trigger ) {
		entry.buttonRef = trigger;
	}
}

/**
 * @param {Record<string, unknown>} meta Image metadata.
 * @return {string}
 */
function srcFromMetadata( meta ) {
	if ( typeof meta.uploadedSrc === 'string' && meta.uploadedSrc ) {
		return meta.uploadedSrc;
	}
	if ( typeof meta.currentSrc === 'string' && meta.currentSrc ) {
		return meta.currentSrc;
	}
	return '';
}

/**
 * @param {Record<string, unknown>} meta Image metadata entry.
 * @return {HTMLImageElement|null}
 */
function probeImageFromMetadata( meta ) {
	const src = srcFromMetadata( meta );

	if ( ! src ) {
		return null;
	}

	const probe = new Image();
	probe.src = src;

	if ( probe.naturalWidth > 0 && probe.naturalHeight > 0 ) {
		return probe;
	}

	return null;
}

/**
 * @param {string} imageId Metadata key.
 */
function ensureImageRef( imageId ) {
	if ( ! imageId || ! state?.metadata?.[ imageId ] ) {
		return;
	}

	const entry = state.metadata[ imageId ];

	if ( entry.imageRef?.complete ) {
		return;
	}

	const domImg = findImageElementForId( imageId );

	if ( domImg ) {
		entry.imageRef = domImg;
		entry.currentSrc = domImg.currentSrc || domImg.src;
		if ( typeof entry.uploadedSrc !== 'string' || ! entry.uploadedSrc ) {
			entry.uploadedSrc = entry.currentSrc;
		}
		return;
	}

	const probe = probeImageFromMetadata( entry );

	if ( probe ) {
		entry.imageRef = probe;
		entry.currentSrc = srcFromMetadata( entry );
	}
}

function ensureGalleryImageRefs() {
	if ( ! state?.selectedGalleryId || ! state.metadata ) {
		return;
	}

	for ( const [ imageId, meta ] of Object.entries( state.metadata ) ) {
		if ( meta?.galleryId === state.selectedGalleryId ) {
			ensureImageRef( imageId );
		}
	}
}

/**
 * @param {string} imageId Metadata key.
 * @return {string}
 */
function captionFromDom( imageId ) {
	const safeId = escapeImageId( imageId );
	const figure = document.querySelector(
		`[data-wp-interactive="core/image"][data-wp-key="${ safeId }"]`
	);

	if ( ! figure ) {
		return '';
	}

	const caption = figure.querySelector( 'figcaption' );

	return caption?.textContent?.trim() || '';
}

/**
 * @param {string} imageId Metadata key.
 */
function ensureCaption( imageId ) {
	if ( ! imageId || ! state?.metadata?.[ imageId ] ) {
		return;
	}

	const entry = state.metadata[ imageId ];

	if ( typeof entry.caption === 'string' && entry.caption ) {
		return;
	}

	const fromDom = captionFromDom( imageId );

	if ( fromDom ) {
		entry.caption = fromDom;
	}
}

/**
 * Core overlay figures are Preact-managed and CSS hides
 * `.wp-lightbox-overlay .wp-block-image figcaption { display: none }`.
 * Keep our caption on body, outside that tree.
 *
 * @return {HTMLElement}
 */
function getLightboxCaptionHost() {
	let el = document.getElementById( 'forwp-smart-link-lightbox-caption' );

	if ( ! el ) {
		el = document.createElement( 'p' );
		el.id = 'forwp-smart-link-lightbox-caption';
		el.className = 'forwp-smart-link-lightbox-caption';
		el.hidden = true;
		document.body.appendChild( el );
	}

	return el;
}

function syncLightboxCaption() {
	const el = getLightboxCaptionHost();
	const caption =
		( state.overlayEnabled &&
			state.selectedImageId &&
			state.metadata?.[ state.selectedImageId ]?.caption ) ||
		'';

	el.textContent = caption;
	el.hidden = ! caption;
}

/**
 * @param {Record<string, unknown>} meta Selected image metadata.
 */
function applyCenteredOverlayStyles( meta ) {
	const imageRef = meta.imageRef;
	const parsedWidth = parseFloat( meta.targetWidth );
	const parsedHeight = parseFloat( meta.targetHeight );
	const naturalWidth =
		meta.targetWidth &&
		meta.targetWidth !== 'none' &&
		! Number.isNaN( parsedWidth )
			? parsedWidth
			: imageRef?.naturalWidth || 1200;
	const naturalHeight =
		meta.targetHeight &&
		meta.targetHeight !== 'none' &&
		! Number.isNaN( parsedHeight )
			? parsedHeight
			: imageRef?.naturalHeight || 800;

	let imgMaxWidth = naturalWidth;
	let imgMaxHeight = naturalHeight;

	let horizontalPadding = 80;
	let verticalPadding = 160;

	if ( window.innerWidth > 960 ) {
		horizontalPadding = state.hasNavigation ? 320 : 80;
		verticalPadding = 80;
	} else if ( window.innerWidth <= 480 ) {
		horizontalPadding = 0;
		verticalPadding = 160;
	}

	const targetMaxWidth = Math.min(
		window.innerWidth - horizontalPadding,
		imgMaxWidth
	);
	const targetMaxHeight = Math.min(
		window.innerHeight - verticalPadding,
		imgMaxHeight
	);
	const imgRatio = imgMaxWidth / imgMaxHeight;
	const targetContainerRatio = targetMaxWidth / targetMaxHeight;
	let containerWidth = imgMaxWidth;
	let containerHeight = imgMaxHeight;

	if ( imgRatio > targetContainerRatio ) {
		containerWidth = targetMaxWidth;
		containerHeight = containerWidth / imgRatio;
	} else {
		containerHeight = targetMaxHeight;
		containerWidth = containerHeight * imgRatio;
	}

	const centerX = window.innerWidth / 2;
	const centerY = window.innerHeight / 2;

	state.overlayStyles = `
		--wp--lightbox-initial-top-position: ${ centerY }px;
		--wp--lightbox-initial-left-position: ${ centerX }px;
		--wp--lightbox-container-width: ${ containerWidth + 1 }px;
		--wp--lightbox-container-height: ${ containerHeight + 1 }px;
		--wp--lightbox-image-width: ${ containerWidth }px;
		--wp--lightbox-image-height: ${ containerHeight }px;
		--wp--lightbox-scale: 1;
		--wp--lightbox-scrollbar-width: ${
			window.innerWidth - document.documentElement.clientWidth
		}px;
	`;
}

/**
 * @param {Record<string, unknown>|undefined} meta Selected image metadata.
 * @return {boolean}
 */
function isCoverLightboxSlide( meta ) {
	const classNames = meta?.imgClassNames;

	return (
		typeof classNames === 'string' &&
		classNames.includes( 'wp-block-cover__image-background' )
	);
}

/**
 * @param {HTMLElement|null} img Cover background image.
 * @return {boolean}
 */
function isCoverLightboxImage( img ) {
	if ( ! img ) {
		return false;
	}

	return (
		img.classList.contains( 'wp-block-cover__image-background' ) ||
		img.classList.contains( 'forwp-smart-link-cover-lightbox__ref' )
	);
}

function runSetOverlayStyles() {
	if ( ! state?.overlayEnabled ) {
		return;
	}

	ensureGalleryImageRefs();
	ensureImageRef( state.selectedImageId );

	const meta = state.selectedImage;

	if ( ! meta ) {
		return;
	}

	if ( ! meta.imageRef ) {
		const probe = probeImageFromMetadata( meta );

		if ( probe ) {
			meta.imageRef = probe;
			meta.currentSrc = srcFromMetadata( meta );
		}
	}

	/*
	 * Always compute styles here — do not call core setOverlayStyles through the
	 * store proxy (nested call loses Interactivity scope).
	 */
	applyCenteredOverlayStyles( meta );
}

if ( callbacks?.setOverlayStyles && state ) {
	callbacks.setOverlayStyles = function forwpSetOverlayStyles() {
		runSetOverlayStyles();
	};
}

/*
 * Do not call core showLightbox() from a wrapper — nested action proxies can
 * clear the Interactivity scope stack (getContext → reading 'context' of undefined).
 * Reimplement open: fill imageRef from DOM, set selectedGalleryId from metadata.
 */
if ( actions?.showLightbox && state ) {
	actions.showLightbox = function forwpShowLightbox() {
		let imageId;

		try {
			imageId = getContext()?.imageId;
		} catch {
			return;
		}

		if ( ! imageId || ! state.metadata?.[ imageId ] ) {
			return;
		}

		ensureImageRef( imageId );
		ensureButtonRef( imageId );
		ensureCaption( imageId );

		const meta = state.metadata[ imageId ];

		if ( ! meta.imageRef ) {
			const domImg = findImageElementForId( imageId );
			if ( domImg ) {
				meta.imageRef = domImg;
				meta.currentSrc = domImg.currentSrc || domImg.src;
			}
		}

		if ( ! meta.imageRef ) {
			const probe = probeImageFromMetadata( meta );
			if ( probe ) {
				meta.imageRef = probe;
				meta.currentSrc = srcFromMetadata( meta );
			}
		}

		if ( ! meta.imageRef ) {
			return;
		}

		if ( typeof meta.uploadedSrc !== 'string' || ! meta.uploadedSrc ) {
			meta.uploadedSrc =
				meta.currentSrc ||
				meta.imageRef.currentSrc ||
				meta.imageRef.src;
		}

		state.scrollTopReset = document.documentElement.scrollTop;
		state.scrollLeftReset = document.documentElement.scrollLeft;
		state.selectedImageId = imageId;
		state.selectedGalleryId = meta.galleryId || null;
		state.overlayEnabled = true;

		try {
			callbacks.setOverlayStyles();
		} catch {
			applyCenteredOverlayStyles( meta );
		}

		syncLightboxCaption();
		requestAnimationFrame( syncLightboxCaption );
	};
}

/*
 * Never call core callbacks/actions via the store proxy from inside a wrapper —
 * nested invocation clears the Interactivity scope (getElement/getContext crash).
 * Register imageRef (+ button position for non-Cover) here.
 */
if ( callbacks?.setButtonStyles && state ) {
	callbacks.setButtonStyles = function forwpSetButtonStyles() {
		let ref;
		let imageId;

		try {
			ref = getElement()?.ref;
			imageId = getContext()?.imageId;
		} catch {
			return;
		}

		if ( ! ref || ! imageId || ! state.metadata?.[ imageId ] ) {
			return;
		}

		const entry = state.metadata[ imageId ];
		entry.imageRef = ref;
		entry.currentSrc = ref.currentSrc || ref.src;

		if ( typeof entry.uploadedSrc !== 'string' || ! entry.uploadedSrc ) {
			entry.uploadedSrc = entry.currentSrc;
		}

		/* Cover: trigger position is CSS-only. */
		if ( isCoverLightboxImage( ref ) ) {
			return;
		}

		const {
			naturalWidth,
			naturalHeight,
			offsetWidth,
			offsetHeight,
		} = ref;

		if ( naturalWidth === 0 || naturalHeight === 0 ) {
			return;
		}

		const figure = ref.parentElement;

		if ( ! figure ) {
			return;
		}

		const figureWidth = figure.clientWidth;
		let figureHeight = figure.clientHeight;
		const caption = figure.querySelector( 'figcaption' );

		if ( caption ) {
			const captionComputedStyle = window.getComputedStyle( caption );

			if ( ! [ 'absolute', 'fixed' ].includes( captionComputedStyle.position ) ) {
				figureHeight =
					figureHeight -
					caption.offsetHeight -
					parseFloat( captionComputedStyle.marginTop ) -
					parseFloat( captionComputedStyle.marginBottom );
			}
		}

		const buttonOffsetTop = figureHeight - offsetHeight;
		const buttonOffsetRight = figureWidth - offsetWidth;
		let buttonTop = buttonOffsetTop + 16;
		let buttonRight = buttonOffsetRight + 16;

		if ( entry.scaleAttr === 'contain' ) {
			const naturalRatio = naturalWidth / naturalHeight;
			const offsetRatio = offsetWidth / offsetHeight;

			if ( naturalRatio >= offsetRatio ) {
				const referenceHeight = offsetWidth / naturalRatio;
				buttonTop =
					( offsetHeight - referenceHeight ) / 2 + buttonOffsetTop + 16;
				buttonRight = buttonOffsetRight + 16;
			} else {
				const referenceWidth = offsetHeight * naturalRatio;
				buttonTop = buttonOffsetTop + 16;
				buttonRight =
					( offsetWidth - referenceWidth ) / 2 + buttonOffsetRight + 16;
			}
		}

		entry.buttonTop = buttonTop;
		entry.buttonRight = buttonRight;
	};
}

/*
 * After gallery navigation, buttonRef can be missing on Cover slides; core hideLightbox()
 * then throws on focus() and never clears selectedImageId — handleScroll locks scroll.
 */
if ( actions?.hideLightbox && state ) {
	actions.hideLightbox = function forwpHideLightbox() {
		if ( ! state.overlayEnabled ) {
			return;
		}

		state.overlayEnabled = false;
		syncLightboxCaption();

		setTimeout( function () {
			ensureButtonRef( state.selectedImageId );

			const buttonRef = state.selectedImage?.buttonRef;

			if ( buttonRef?.focus ) {
				try {
					buttonRef.focus( { preventScroll: true } );
				} catch {
					// Focus is optional; state reset below is required for scroll unlock.
				}
			}

			state.selectedImageId = null;
			state.selectedGalleryId = null;
			syncLightboxCaption();
		}, 450 );
	};
}

if ( actions?.handleScroll && state ) {
	actions.handleScroll = function forwpHandleScroll() {
		if ( ! state.overlayEnabled ) {
			return;
		}

		window.scrollTo( state.scrollLeftReset, state.scrollTopReset );
	};
}

if ( actions?.showNextImage && state ) {
	actions.showNextImage = function forwpShowNextImage( event ) {
		if ( event?.stopPropagation ) {
			event.stopPropagation();
		}

		if ( ! state.galleryImages?.length ) {
			return;
		}

		const nextIndex = state.hasNextImage
			? state.selectedImageIndex + 1
			: 0;
		state.selectedImageId = state.galleryImages[ nextIndex ];
		ensureImageRef( state.selectedImageId );
		ensureButtonRef( state.selectedImageId );
		ensureCaption( state.selectedImageId );
		callbacks.setOverlayStyles();
		syncLightboxCaption();
	};
}

if ( actions?.showPreviousImage && state ) {
	actions.showPreviousImage = function forwpShowPreviousImage( event ) {
		if ( event?.stopPropagation ) {
			event.stopPropagation();
		}

		if ( ! state.galleryImages?.length ) {
			return;
		}

		const nextIndex = state.hasPreviousImage
			? state.selectedImageIndex - 1
			: state.galleryImages.length - 1;
		state.selectedImageId = state.galleryImages[ nextIndex ];
		ensureImageRef( state.selectedImageId );
		ensureButtonRef( state.selectedImageId );
		ensureCaption( state.selectedImageId );
		callbacks.setOverlayStyles();
		syncLightboxCaption();
	};
}
