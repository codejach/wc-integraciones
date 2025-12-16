(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	
    $(document).ready(function() {
        const $toggleBtn = $('#toggle_secret');
        const $secretField = $('#meli_secret_key');

        if ($toggleBtn.length && $secretField.length) {
            $toggleBtn.on('click', function() {
                const isPassword = $secretField.attr('type') === 'password';
                $secretField.attr('type', isPassword ? 'text' : 'password');
                $(this).text(isPassword ? '🙈 Ocultar' : '👁 Mostrar');
            });
        }

		// Manejo del guardado de SKU con temporizador y opción de cancelar
		document.querySelectorAll('.sku-selector').forEach(function (select) {
			select.addEventListener('change', function () {
				const detalleId = this.dataset.detalleId;
				const sku = this.value;
				const row = this.closest('tr');

				let timerSpan = row.querySelector('.save-timer');
				let cancelBtn = row.querySelector('.cancel-btn');
				let counter = 5;

				timerSpan.textContent = `Guardando en ${counter}s...`;
				cancelBtn.style.display = 'inline';

				const countdown = setInterval(() => {
					counter--;
					if (counter <= 0) {
						clearInterval(countdown);
						cancelBtn.style.display = 'none';
						timerSpan.textContent = 'Guardando...';

						fetch('/wp-json/meli/v1/asignar-sku', {
							method: 'POST',
							headers: {'Content-Type': 'application/json'},
							body: JSON.stringify({detalle_id: detalleId, sku})
						}).then(r => r.json()).then(data => {
							timerSpan.textContent = data.success ? '✅ Guardado' : '❌ Error';
						})
						.catch(() => {
							timerSpan.textContent = '❌ Error. Inténtalo de nuevo.';
						});
					} else {
						timerSpan.textContent = `Guardando en ${counter}s...`;
					}
				}, 1000);

				cancelBtn.onclick = () => {
					clearInterval(countdown);
					cancelBtn.style.display = 'none';
					timerSpan.textContent = '⏸ Cancelado';
				};
			});
		});
    });

})( jQuery );