/**
 * Toggle the hover swap with pointer events so the selected effect
 * (fade / fade+zoom) and duration always run, even when a parent
 * layout prevents CSS :hover from reaching the block.
 */
(function () {
	function bindSwap(el) {
		if (el.getAttribute('data-crnm-ihs-bound') === '1') {
			return;
		}

		el.setAttribute('data-crnm-ihs-bound', '1');

		var activate = function () {
			el.classList.add('is-hovered');
		};

		var deactivate = function () {
			el.classList.remove('is-hovered');
		};

		el.addEventListener('pointerenter', activate);
		el.addEventListener('pointerleave', deactivate);
		el.addEventListener('focusin', activate);
		el.addEventListener('focusout', function (event) {
			if (!el.contains(event.relatedTarget)) {
				deactivate();
			}
		});
	}

	function init() {
		document.querySelectorAll('.crnm-image-hover-swap').forEach(bindSwap);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
