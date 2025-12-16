/**
 * Creator Dashboard JavaScript
 *
 * @package CreatorCore
 */

(function($) {
	'use strict';

	// Dashboard state
	const state = {
		conversations: [],
		currentPage: 1,
		hasMore: true,
		isLoading: false,
		deleteId: null
	};

	// DOM elements
	let $conversationsList;
	let $loadMoreWrap;
	let $loadMoreBtn;
	let $modal;
	let $deleteTitle;

	/**
	 * Initialize dashboard
	 */
	function init() {
		// Cache DOM elements
		$conversationsList = $('#creator-conversations-list');
		$loadMoreWrap = $('#creator-load-more-wrap');
		$loadMoreBtn = $('#creator-load-more');
		$modal = $('#creator-delete-modal');
		$deleteTitle = $('#creator-delete-title');

		// Render initial data
		renderLicenseStatus();
		renderUsageDisplay();
		renderHealthIndicators();

		// Check if license was just saved (settings-updated parameter)
		checkLicenseUpdateFeedback();

		// Load conversations
		loadConversations();

		// Bind events
		bindEvents();
	}

	/**
	 * Bind event handlers
	 */
	function bindEvents() {
		// Load more conversations
		$loadMoreBtn.on('click', function() {
			state.currentPage++;
			loadConversations(true);
		});

		// Conversation click (delegation)
		$conversationsList.on('click', '.creator-conversation-row', function(e) {
			// Ignore if clicking delete button
			if ($(e.target).closest('.creator-delete-btn').length) {
				return;
			}

			const chatId = $(this).data('id');
			window.location.href = creatorDashboard.chatUrl + '&chat=' + chatId;
		});

		// Delete button click
		$conversationsList.on('click', '.creator-delete-btn', function(e) {
			e.stopPropagation();
			const $row = $(this).closest('.creator-conversation-row');
			state.deleteId = $row.data('id');
			const title = $row.find('.creator-conversation-title').text();
			$deleteTitle.text('"' + title + '"');
			showModal();
		});

		// Modal cancel
		$('#creator-cancel-delete, .creator-modal-overlay').on('click', function() {
			hideModal();
		});

		// Modal confirm delete
		$('#creator-confirm-delete').on('click', function() {
			if (state.deleteId) {
				deleteConversation(state.deleteId);
			}
		});

		// Change license key toggle
		$('#creator-change-license').on('click', function() {
			$('#creator-change-license-form').slideDown();
			$(this).hide();
		});

		$('#creator-cancel-change').on('click', function() {
			$('#creator-change-license-form').slideUp();
			$('#creator-change-license').show();
		});

		// Escape key closes modal
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $modal.is(':visible')) {
				hideModal();
			}
		});
	}

	/**
	 * Render license status
	 */
	function renderLicenseStatus() {
		const license = creatorDashboard.license;
		const $container = $('#creator-license-status');

		if (!license.hasKey) {
			return;
		}

		let badgeClass = 'status-' + license.status;
		let badgeIcon = 'dashicons-yes-alt';
		let badgeText = '';

		switch (license.status) {
			case 'active':
				badgeIcon = 'dashicons-yes-alt';
				badgeText = 'License Active';
				if (license.plan) {
					badgeText += ' - ' + capitalizeFirst(license.plan) + ' Plan';
				}
				break;
			case 'expiring':
				badgeIcon = 'dashicons-warning';
				badgeText = 'Expiring Soon';
				break;
			case 'expired':
				badgeIcon = 'dashicons-dismiss';
				badgeText = 'License Expired';
				break;
			case 'invalid':
				badgeIcon = 'dashicons-no';
				badgeText = 'Invalid License';
				break;
			default:
				badgeIcon = 'dashicons-editor-help';
				badgeText = 'Unknown Status';
		}

		let html = '<div class="creator-license-badge ' + badgeClass + '">';
		html += '<span class="dashicons ' + badgeIcon + '"></span>';
		html += badgeText;
		html += '</div>';

		html += '<div class="creator-license-info">';
		html += '<strong>Site:</strong> ' + escapeHtml(license.siteUrl) + '<br>';

		if (license.expiresAt) {
			let expiryText = license.expiresAt;
			if (license.daysLeft !== null) {
				expiryText += ' (' + license.daysLeft + ' days)';
			}
			html += '<strong>Expires:</strong> ' + escapeHtml(expiryText);
		}
		html += '</div>';

		$container.html(html);
	}

	/**
	 * Render usage display
	 */
	function renderUsageDisplay() {
		const usage = creatorDashboard.usage;
		const $container = $('#creator-usage-display');

		const percentage = usage.percentage || 0;
		const available = Math.max(0, 100 - percentage);

		let levelClass = 'level-low';
		if (percentage > 90) {
			levelClass = 'level-high';
		} else if (percentage > 70) {
			levelClass = 'level-medium';
		}

		let html = '<div class="creator-usage-label">' + creatorDashboard.i18n.creditsUsed + '</div>';

		html += '<div class="creator-usage-progress-wrap">';
		html += '<div class="creator-usage-progress">';
		html += '<div class="creator-usage-progress-bar ' + levelClass + '" style="width: 0%;"></div>';
		html += '</div>';
		html += '</div>';

		html += '<div class="creator-usage-numbers">';
		html += '<span class="creator-usage-count">' + formatNumber(usage.tokensUsed) + '</span>';
		html += '<span class="creator-usage-limit">/ ' + formatNumber(usage.tokensLimit) + '</span>';
		html += '</div>';

		html += '<div class="creator-usage-percentage ' + levelClass + '">';
		html += available.toFixed(1) + '% ' + creatorDashboard.i18n.available;
		html += '</div>';

		if (usage.resetDate) {
			html += '<div class="creator-usage-reset">';
			html += creatorDashboard.i18n.resetDate + ': ' + escapeHtml(usage.resetDate);
			html += '</div>';
		}

		$container.html(html);

		// Animate progress bar
		setTimeout(function() {
			$container.find('.creator-usage-progress-bar').css('width', percentage + '%');
		}, 100);
	}

	/**
	 * Render health indicators
	 */
	function renderHealthIndicators() {
		const health = creatorDashboard.systemHealth;
		const $container = $('#creator-health-indicators');

		let html = '';

		// Firebase
		html += renderHealthItem(health.firebase);

		// Gemini
		html += renderHealthItem(health.gemini);

		// Claude
		html += renderHealthItem(health.claude);

		$container.html(html);
	}

	/**
	 * Render a single health item
	 */
	function renderHealthItem(item) {
		let html = '<div class="creator-health-item">';
		html += '<span class="creator-health-dot status-' + item.status + '"></span>';
		html += '<span>' + escapeHtml(item.label);
		if (item.status === 'connected' || item.status === 'active') {
			html += ' Connected';
		}
		html += '</span>';
		html += '</div>';
		return html;
	}

	/**
	 * Load conversations from API
	 */
	function loadConversations(append) {
		if (state.isLoading) {
			return;
		}

		state.isLoading = true;

		if (!append) {
			$conversationsList.html('<div class="creator-loading"><span class="spinner is-active"></span> ' + creatorDashboard.i18n.loading + '</div>');
		} else {
			$loadMoreBtn.prop('disabled', true).text(creatorDashboard.i18n.loading);
		}

		$.ajax({
			url: creatorDashboard.restUrl + 'dashboard/conversations',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', creatorDashboard.restNonce);
			},
			data: {
				page: state.currentPage,
				per_page: 10
			},
			success: function(response) {
				if (response.success) {
					state.hasMore = response.has_more;

					if (append) {
						state.conversations = state.conversations.concat(response.conversations);
						appendConversations(response.conversations);
					} else {
						state.conversations = response.conversations;
						renderConversations();
					}

					// Generate titles for conversations that need them
					generateMissingTitles(response.conversations);
				}
			},
			error: function() {
				if (!append) {
					$conversationsList.html('<div class="creator-no-conversations"><span class="dashicons dashicons-warning"></span><p>' + creatorDashboard.i18n.error + '</p></div>');
				}
			},
			complete: function() {
				state.isLoading = false;
				$loadMoreBtn.prop('disabled', false).text(creatorDashboard.i18n.loadMore);
				updateLoadMoreVisibility();
			}
		});
	}

	/**
	 * Render all conversations
	 */
	function renderConversations() {
		if (state.conversations.length === 0) {
			$conversationsList.html(
				'<div class="creator-no-conversations">' +
				'<span class="dashicons dashicons-format-chat"></span>' +
				'<p>' + creatorDashboard.i18n.noConversations + '</p>' +
				'<a href="' + creatorDashboard.chatUrl + '" class="button button-primary">' + creatorDashboard.i18n.startNewChat + '</a>' +
				'</div>'
			);
			return;
		}

		let html = '';
		state.conversations.forEach(function(conv) {
			html += renderConversationRow(conv);
		});

		$conversationsList.html(html);
	}

	/**
	 * Append conversations to list
	 */
	function appendConversations(conversations) {
		conversations.forEach(function(conv) {
			const $row = $(renderConversationRow(conv));
			$row.addClass('fade-in');
			$conversationsList.append($row);
		});
	}

	/**
	 * Render a single conversation row
	 */
	function renderConversationRow(conv) {
		let html = '<div class="creator-conversation-row" data-id="' + conv.id + '">';

		// Chat icon for conversation
		html += '<div class="creator-conversation-icon">';
		html += '<span class="dashicons dashicons-format-chat"></span>';
		html += '</div>';

		html += '<div class="creator-conversation-content">';
		html += '<h4 class="creator-conversation-title">' + escapeHtml(conv.title) + '</h4>';

		if (conv.summary) {
			html += '<p class="creator-conversation-summary">' + escapeHtml(conv.summary) + '</p>';
		}

		html += '<div class="creator-conversation-meta">';
		html += '<span>' + escapeHtml(conv.date_relative) + '</span>';
		html += '<span class="separator">•</span>';
		html += '<span>' + conv.message_count + ' ' + creatorDashboard.i18n.messages + '</span>';
		html += '</div>';
		html += '</div>';

		html += '<div class="creator-conversation-actions">';
		html += '<button type="button" class="creator-delete-btn" title="' + creatorDashboard.i18n.delete + '">';
		html += '<span class="dashicons dashicons-trash"></span>';
		html += '</button>';
		html += '</div>';

		html += '</div>';

		return html;
	}

	/**
	 * Update load more button visibility
	 */
	function updateLoadMoreVisibility() {
		if (state.hasMore && state.conversations.length > 0) {
			$loadMoreWrap.show();
		} else {
			$loadMoreWrap.hide();
		}
	}

	/**
	 * Generate missing titles for conversations
	 */
	function generateMissingTitles(conversations) {
		conversations.forEach(function(conv) {
			if (conv.needs_title && conv.message_count >= 3) {
				generateTitle(conv.id);
			}
		});
	}

	/**
	 * Generate AI title for a conversation
	 */
	function generateTitle(chatId) {
		$.ajax({
			url: creatorDashboard.restUrl + 'dashboard/conversations/' + chatId + '/generate-title',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', creatorDashboard.restNonce);
			},
			success: function(response) {
				if (response.success && response.title) {
					// Update the title in the UI
					const $row = $conversationsList.find('[data-id="' + chatId + '"]');
					$row.find('.creator-conversation-title').text(response.title);

					// Update state
					const conv = state.conversations.find(c => c.id === chatId);
					if (conv) {
						conv.title = response.title;
						conv.needs_title = false;
					}
				}
			}
		});
	}

	/**
	 * Delete a conversation
	 */
	function deleteConversation(chatId) {
		const $confirmBtn = $('#creator-confirm-delete');
		$confirmBtn.prop('disabled', true).text(creatorDashboard.i18n.loading);

		$.ajax({
			url: creatorDashboard.restUrl + 'dashboard/conversations/' + chatId,
			method: 'DELETE',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', creatorDashboard.restNonce);
			},
			success: function(response) {
				if (response.success) {
					// Remove from state
					state.conversations = state.conversations.filter(c => c.id !== chatId);

					// Remove from DOM with animation
					const $row = $conversationsList.find('[data-id="' + chatId + '"]');
					$row.fadeOut(300, function() {
						$(this).remove();

						// Re-render if empty
						if (state.conversations.length === 0) {
							renderConversations();
						}
					});

					hideModal();
				}
			},
			error: function() {
				alert(creatorDashboard.i18n.error);
			},
			complete: function() {
				$confirmBtn.prop('disabled', false).text(creatorDashboard.i18n.delete);
				state.deleteId = null;
			}
		});
	}

	/**
	 * Show feedback message
	 */
	function showFeedback(message, type) {
		const $feedback = $('#creator-license-feedback');
		$feedback.removeClass('success error').addClass(type).text(message).fadeIn(200);

		// Auto-hide after 5 seconds
		setTimeout(function() {
			$feedback.fadeOut(200);
		}, 5000);
	}

	/**
	 * Check if license was just saved and show feedback
	 */
	function checkLicenseUpdateFeedback() {
		// Check for settings-updated query parameter
		const urlParams = new URLSearchParams(window.location.search);
		if (!urlParams.has('settings-updated')) {
			return;
		}

		// Check license status and show appropriate feedback
		const license = creatorDashboard.license;
		if (license.hasKey) {
			if (license.isValid) {
				showFeedback(creatorDashboard.i18n.verifySuccess, 'success');
			} else {
				showFeedback(creatorDashboard.i18n.verifyError, 'error');
			}

			// Clean URL (remove settings-updated parameter)
			const cleanUrl = window.location.pathname + '?page=creator-dashboard';
			window.history.replaceState({}, document.title, cleanUrl);
		}
	}

	/**
	 * Show delete modal
	 */
	function showModal() {
		$modal.fadeIn(200);
		$('body').addClass('creator-modal-open');
	}

	/**
	 * Hide delete modal
	 */
	function hideModal() {
		$modal.fadeOut(200);
		$('body').removeClass('creator-modal-open');
		state.deleteId = null;
	}

	/**
	 * Format number with thousands separator
	 */
	function formatNumber(num) {
		return new Intl.NumberFormat().format(num);
	}

	/**
	 * Capitalize first letter
	 */
	function capitalizeFirst(str) {
		return str.charAt(0).toUpperCase() + str.slice(1);
	}

	/**
	 * Escape HTML entities
	 */
	function escapeHtml(str) {
		if (!str) return '';
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);
