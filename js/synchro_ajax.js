/**
 * AJAX synchronization handler for WooSync module
 * Processes WooCommerce -> Dolibarr sync page by page to avoid PHP timeouts
 * Supports multiple element types (societe, product, commande)
 *
 * Uses a shared progress bar — only one sync can run at a time.
 * All sync buttons are disabled while a sync is running.
 */

// Registry of all sync instances, used to disable/enable all buttons
var _synchroInstances = [];
var _synchroRunning = false;

// Prevent closing the window while a sync is running
jQuery(window).on('beforeunload', function() {
	if (_synchroRunning) return ' ';
});

/**
 * Create a sync instance for a given element type
 *
 * @param {string} elementType   Element type identifier (societe, product, commande)
 * @param {object} config        Configuration object with siteId, ajaxUrl, collectOptions, langs
 * @returns {EcommerceSynchroAjaxInstance}
 */
function createSynchroAjaxInstance(elementType, config) {
	var instance = new EcommerceSynchroAjaxInstance(elementType, config);
	_synchroInstances.push(instance);
	return instance;
}

function EcommerceSynchroAjaxInstance(elementType, config) {
	this.elementType = elementType;
	this.config = config;
	this.currentPage = 0;
	this.totalRemote = -1;
	this.totalProcessed = 0;
	this.totalSuccess = 0;
	this.totalErrors = 0;
	this.errors = [];
	this.aborted = false;
	this.currentXhr = null;
	this._retried = false;
	this.options = {};
}

EcommerceSynchroAjaxInstance.prototype = {

	/**
	 * Disable all sync buttons and purge/debug links
	 */
	disableAllButtons: function() {
		jQuery('input[id^="submit_synchro_"], input[name^="submit_synchro_"], input[id^="submit_dtoe_synchro_"], input[name^="submit_dtoe_synchro_"]').addClass('butActionRefused').prop('disabled', true);
		jQuery('.submit_reset_data_links, .submit_reset_data_all, .submit_reset_last_update_date, #showtools').addClass('ecommerceng-link-disabled');
	},

	/**
	 * Re-enable all sync buttons and purge/debug links
	 */
	enableAllButtons: function() {
		jQuery('input[id^="submit_synchro_"], input[name^="submit_synchro_"], input[id^="submit_dtoe_synchro_"], input[name^="submit_dtoe_synchro_"]').removeClass('butActionRefused').prop('disabled', false);
		jQuery('.submit_reset_data_links, .submit_reset_data_all, .submit_reset_last_update_date, #showtools').removeClass('ecommerceng-link-disabled');
	},

	/**
	 * Start synchronization
	 */
	start: function() {
		var self = this;
		var form = jQuery('#form_count');

		// Collect options via config callback and add element type
		this.options = this.config.collectOptions(form);
		this.options.element = this.elementType;

		// Reset state
		this.currentPage = 0;
		this.totalRemote = -1;
		this.totalProcessed = 0;
		this.totalSuccess = 0;
		this.totalErrors = 0;
		this.errors = [];
		this.aborted = false;
		this.currentXhr = null;

		// Show shared progress UI
		jQuery('#synchro_progress').show();
		jQuery('#synchro_cancel').show();
		jQuery('#synchro_log').empty();

		// Update status icon to spinning
		jQuery('#synchro_status .fas').removeClass('fa-check fa-exclamation-triangle').addClass('fa-sync fa-spin');

		// Reset progress bar
		this.setProgressBar(0);
		this.setStatusText(this.config.langs.processing.replace('%s', '1').replace('%s', '0'));

		// Disable all sync buttons and prevent window close
		_synchroRunning = true;
		this.disableAllButtons();

		// Start first page
		this.processPage(1);
	},

	/**
	 * Process a single page
	 */
	processPage: function(page) {
		var self = this;

		if (this.aborted) {
			this.finish();
			return;
		}

		this.currentPage = page;

		var postData = jQuery.extend({
			action: 'batch',
			id: this.config.siteId,
			page: page,
			already_processed: this.totalProcessed
		}, this.options);

		this.currentXhr = jQuery.ajax({
			url: this.config.ajaxUrl,
			type: 'POST',
			data: postData,
			dataType: 'json',
			timeout: 600000, // 10 min
			success: function(response) {
				if (response.status === 'error') {
					if (response.token_expired) {
						self.addLogLine(page, 0, 0, [self.config.langs.sessionExpired || 'Session expired'], true);
						self.finish();
						return;
					}
					self.addLogLine(page, 0, 0, [response.message], true);
					self.finish();
					return;
				}

				// Update total from first page
				if (page === 1 && response.total > 0) {
					self.totalRemote = response.total;
				}

				// Freeze the resolved date filter after the first page so every subsequent
				// request sends an identical filter and only the page number advances.
				if (page === 1) {
					self.options.frozen_from_date = (typeof response.from_date !== 'undefined') ? response.from_date : 0;
					self.options.frozen_to_date = (typeof response.to_date !== 'undefined') ? response.to_date : 0;
				}

				// Accumulate results
				self.totalProcessed += response.processed || 0;
				self.totalSuccess += response.success || 0;
				self.totalErrors += (response.errors ? response.errors.length : 0);
				if (response.errors && response.errors.length > 0) {
					self.errors = self.errors.concat(response.errors);
				}

				// Update UI
				self.updateUI();
				self.addLogLine(page, response.success || 0, response.errors ? response.errors.length : 0, response.errors, false, response.warnings);

				// Continue or finish
				if (response.has_more && !self.aborted) {
					self.processPage(page + 1);
				} else {
					self.finish();
				}
			},
			error: function(xhr, status, error) {
				if (status === 'abort') return; // abort() handles everything

				// Retry once on network error
				if (!self._retried) {
					self._retried = true;
					self.addLogLine(page, 0, 0, [self.config.langs.networkError], false);
					setTimeout(function() {
						self.processPage(page);
					}, 2000);
				} else {
					self._retried = false;
					self.addLogLine(page, 0, 0, [self.config.langs.networkError + ' (abandon)'], true);
					self.finish();
				}
			}
		});
	},

	/**
	 * Update progress bar and counters
	 */
	updateUI: function() {
		var percent = 0;
		if (this.totalRemote > 0) {
			percent = Math.min(Math.round((this.totalProcessed / this.totalRemote) * 100), 100);
		}
		this.setProgressBar(percent);

		var statusText = this.config.langs.processing
			.replace('%s', this.currentPage)
			.replace('%s', this.totalProcessed);
		this.setStatusText(statusText);

		var counterText = this.totalProcessed;
		if (this.totalRemote > 0) counterText += '/' + this.totalRemote;
		jQuery('#synchro_counter').text(counterText);
	},

	/**
	 * Set progress bar percentage
	 */
	setProgressBar: function(percent) {
		var bar = jQuery('#synchro_progress_bar');
		bar.attr('title', percent + '%');
		bar.find('.progress-bar')
			.css('width', percent + '%')
			.attr('aria-valuenow', percent);
	},

	/**
	 * Set status text
	 */
	setStatusText: function(text) {
		jQuery('#synchro_status_text').text(text);
	},

	/**
	 * Add a log line for a completed page
	 */
	addLogLine: function(page, success, errors, errorMessages, isFatal, warningMessages) {
		var log = jQuery('#synchro_log');
		var hasWarnings = warningMessages && warningMessages.length > 0;
		var line = '<div>';

		if (isFatal) {
			line += '<span class="fas fa-times" style="color: #bc0000;"></span> ';
		} else if (errors > 0) {
			line += '<span class="fas fa-exclamation-triangle" style="color: #bc0000;"></span> ';
		} else if (hasWarnings) {
			line += '<span class="fas fa-exclamation-triangle" style="color: #bca000;"></span> ';
		} else {
			line += '<span class="fas fa-check" style="color: #446548;"></span> ';
		}

		line += 'Page ' + page + ' : ';
		if (success > 0) {
			line += '<span class="badge badge-status4">' + success + '</span> ';
		}
		if (errors > 0) {
			line += '<span class="badge badge-status8">' + errors + '</span> ';
		}
		if (hasWarnings) {
			line += '<span class="badge badge-status1">' + warningMessages.length + ' warn.</span> ';
		}

		if (errorMessages && errorMessages.length > 0) {
			for (var i = 0; i < errorMessages.length; i++) {
				line += '<br><span class="opacitymedium" style="margin-left: 20px; font-size: 0.85em; color: #bc0000;">' + errorMessages[i] + '</span>';
			}
		}
		if (hasWarnings) {
			for (var i = 0; i < warningMessages.length; i++) {
				line += '<br><span class="opacitymedium" style="margin-left: 20px; font-size: 0.85em; color: #bca000;">' + warningMessages[i] + '</span>';
			}
		}

		line += '</div>';
		log.append(line);
		// Auto-scroll to bottom
		log.scrollTop(log[0].scrollHeight);
	},

	/**
	 * Finish synchronization: call finish action and update UI
	 */
	finish: function() {
		var self = this;
		this.currentXhr = null;

		// Update icon
		if (this.totalErrors > 0 || this.aborted) {
			jQuery('#synchro_status .fas').removeClass('fa-sync fa-spin fa-check').addClass('fa-exclamation-triangle');
		} else {
			jQuery('#synchro_status .fas').removeClass('fa-sync fa-spin fa-exclamation-triangle').addClass('fa-check');
		}

		// Hide cancel button
		jQuery('#synchro_cancel').hide();

		// Set progress to 100% if completed normally
		if (!this.aborted && this.totalRemote > 0) {
			this.setProgressBar(100);
		}

		// Final status text
		var finalText;
		if (this.aborted) {
			finalText = this.config.langs.aborted;
		} else {
			finalText = this.config.langs.finished
				.replace('%s', this.totalSuccess)
				.replace('%s', this.totalErrors);
		}
		this.setStatusText(finalText);
		jQuery('#synchro_counter').text('');

		// Re-enable all sync buttons and allow window close
		_synchroRunning = false;
		this.enableAllButtons();

		// Call finish action
		jQuery.ajax({
			url: self.config.ajaxUrl,
			type: 'POST',
			data: {
				action: 'finish',
				id: self.config.siteId,
				total_success: self.totalSuccess,
				total_errors: self.totalErrors
			},
			dataType: 'json'
		});
	},

	/**
	 * Abort synchronization (user clicked cancel)
	 */
	abort: function() {
		this.aborted = true;
		this.setStatusText(this.config.langs.cancelling || 'Annulation en cours...');
		jQuery('#synchro_cancel').hide();
		if (this.currentXhr) {
			this.currentXhr.abort();
			this.currentXhr = null;
		}
		// Send a finish request — it will block on the PHP session lock until the
		// server-side sync process ends. When it returns, the server is truly done.
		var self = this;
		jQuery.ajax({
			url: self.config.ajaxUrl,
			type: 'POST',
			data: {
				action: 'finish',
				id: self.config.siteId,
				total_success: self.totalSuccess,
				total_errors: self.totalErrors
			},
			dataType: 'json',
			timeout: 620000,
			complete: function() {
				_synchroRunning = false;
				jQuery('#synchro_status .fas').removeClass('fa-sync fa-spin fa-check').addClass('fa-exclamation-triangle');
				self.setStatusText(self.config.langs.aborted);
				jQuery('#synchro_counter').text('');
				self.enableAllButtons();
			}
		});
	}
};
