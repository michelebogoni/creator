/**
 * Creator Core - Chat Interface Scripts
 *
 * @package CreatorCore
 */

(function($) {
    'use strict';

    /**
     * Chat Interface Manager
     */
    const CreatorChat = {
        chatId: null,
        isTyping: false,
        messageQueue: [],
        attachedFiles: [], // Store attached files as base64

        /**
         * Initialize chat interface
         */
        init: function() {
            this.chatId = creatorChat.chatId || null;
            this.attachedFiles = [];
            this.bindEvents();
            this.initTextarea();
            this.scrollToBottom();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Send message form
            $('#creator-chat-form').on('submit', this.handleSubmit.bind(this));

            // Message input
            $('#creator-message-input').on('input', this.handleInput.bind(this));
            $('#creator-message-input').on('keydown', this.handleKeydown.bind(this));

            // Suggestion buttons
            $('.creator-suggestion').on('click', this.handleSuggestion.bind(this));

            // Edit title button
            $('.creator-edit-title').on('click', this.handleEditTitle.bind(this));

            // Action buttons in messages
            $(document).on('click', '.creator-action-btn', this.handleActionButton.bind(this));

            // Retry failed action
            $(document).on('click', '.creator-retry-action', this.handleRetryAction.bind(this));

            // Rollback action
            $(document).on('click', '.creator-rollback-action', this.handleRollback.bind(this));

            // Capability tabs
            $(document).on('click', '.creator-tab', this.handleTabClick.bind(this));

            // Model toggle in chat header
            $('input[name="chat_model"]').on('change', this.handleModelToggle.bind(this));

            // File attachment
            $('#creator-attach-btn').on('click', this.handleAttachClick.bind(this));
            $('#creator-file-input').on('change', this.handleFileSelect.bind(this));
            $(document).on('click', '.creator-attachment-remove', this.handleAttachmentRemove.bind(this));
        },

        /**
         * Handle model toggle selection
         */
        handleModelToggle: function(e) {
            const $radio = $(e.currentTarget);
            const $option = $radio.closest('.creator-model-toggle-option');

            // Remove active class from all options
            $('.creator-model-toggle-option').removeClass('active');

            // Add active class to selected option
            $option.addClass('active');

            // Update data attribute on container
            $('.creator-chat-container').data('model', $radio.val());
        },

        /**
         * Get selected model
         */
        getSelectedModel: function() {
            const selected = $('input[name="chat_model"]:checked').val();
            return selected || $('.creator-chat-container').data('model') || 'gemini';
        },

        /**
         * Handle capability tab click
         */
        handleTabClick: function(e) {
            const $tab = $(e.currentTarget);
            const tabName = $tab.data('tab');

            // Update active tab
            $('.creator-tab').removeClass('active');
            $tab.addClass('active');

            // Show/hide corresponding suggestions
            $('[data-tab-content]').hide();
            $('[data-tab-content="' + tabName + '"]').show();
        },

        /**
         * Initialize auto-growing textarea
         */
        initTextarea: function() {
            const $textarea = $('#creator-message-input');

            $textarea.on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        },

        /**
         * Handle form submission
         */
        handleSubmit: function(e) {
            e.preventDefault();

            const $input = $('#creator-message-input');
            const message = $input.val().trim();

            // Allow sending with only files (no message) or with message
            if ((!message && this.attachedFiles.length === 0) || this.isTyping) {
                return;
            }

            // Send message with files
            this.sendMessage(message, this.attachedFiles.slice());

            // Clear input and attachments
            $input.val('').trigger('input');
            this.clearAttachments();
        },

        /**
         * Handle input changes
         */
        handleInput: function(e) {
            const $btn = $('.creator-send-btn');
            const hasValue = $(e.target).val().trim().length > 0;
            const hasFiles = this.attachedFiles && this.attachedFiles.length > 0;

            // Enable send if there's text OR files
            $btn.prop('disabled', (!hasValue && !hasFiles) || this.isTyping);
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeydown: function(e) {
            // Submit on Enter (without Shift)
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $('#creator-chat-form').trigger('submit');
            }
        },

        /**
         * Handle suggestion click
         */
        handleSuggestion: function(e) {
            const suggestion = $(e.currentTarget).text();
            $('#creator-message-input').val(suggestion).trigger('input');
            $('#creator-chat-form').trigger('submit');
        },

        /**
         * Send message to API
         */
        sendMessage: function(message, files = []) {
            const self = this;

            // Build message with attachment info for display
            const messageData = {
                role: 'user',
                content: message || '',
                sender_name: creatorChat.userName,
                timestamp: new Date().toISOString()
            };

            // Add attachment info for display
            if (files && files.length > 0) {
                messageData.attachments = files.map(f => ({
                    name: f.name,
                    type: f.type,
                    size: f.size
                }));
            }

            // Add user message to UI immediately
            this.addMessage(messageData);

            // Hide welcome message if present
            $('.creator-welcome-message').fadeOut();

            // Show typing indicator
            this.showTypingIndicator();

            // Store files for sending
            this.pendingFiles = files;

            // If no chat exists, create one first
            if (!this.chatId) {
                this.createChatAndSendMessage(message);
                return;
            }

            // Send to existing chat
            this.sendMessageToChat(this.chatId, message);
        },

        /**
         * Create a new chat and send the first message
         */
        createChatAndSendMessage: function(message) {
            const self = this;
            const selectedModel = this.getSelectedModel();

            $.ajax({
                url: creatorChat.restUrl + 'chats',
                type: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': creatorChat.restNonce
                },
                data: JSON.stringify({
                    title: message.substring(0, 50) + (message.length > 50 ? '...' : ''),
                    ai_model: selectedModel
                }),
                success: function(response) {
                    if (response.success && response.chat && response.chat.id) {
                        self.chatId = response.chat.id;
                        self.updateUrl(response.chat.id);

                        // Hide model toggle after chat is created (model is now locked)
                        $('.creator-model-toggle').fadeOut(function() {
                            // Show model badge instead
                            const modelBadge = '<div class="creator-model-badge">' +
                                '<span class="creator-model-badge-icon">' + (selectedModel === 'gemini' ? '🔷' : '🟠') + '</span>' +
                                '<span class="creator-model-badge-label">' + (selectedModel === 'gemini' ? 'Gemini 3 Pro' : 'Claude Sonnet 4') + '</span>' +
                            '</div>';
                            $(this).replaceWith(modelBadge);
                        });

                        self.sendMessageToChat(response.chat.id, message);
                    } else {
                        self.hideTypingIndicator();
                        const errorMsg = response.message || 'Failed to create chat';
                        self.showError(errorMsg, self.isLicenseError(errorMsg));
                    }
                },
                error: function(xhr) {
                    self.hideTypingIndicator();
                    const error = xhr.responseJSON?.message || 'Failed to create chat';
                    self.showError(error, self.isLicenseError(error));
                }
            });
        },

        /**
         * Send message to an existing chat
         */
        sendMessageToChat: function(chatId, message) {
            const self = this;

            // Build request data
            const requestData = {
                content: message || ''
            };

            // Include files if present
            if (this.pendingFiles && this.pendingFiles.length > 0) {
                requestData.files = this.pendingFiles;
                this.pendingFiles = []; // Clear after including
            }

            $.ajax({
                url: creatorChat.restUrl + 'chats/' + chatId + '/messages',
                type: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': creatorChat.restNonce
                },
                data: JSON.stringify(requestData),
                success: function(response) {
                    self.hideTypingIndicator();

                    if (response.success) {
                        // Update chat ID if new chat
                        if (response.chat_id) {
                            self.chatId = response.chat_id;
                            self.updateUrl(response.chat_id);
                        }

                        // Add AI response
                        self.addMessage({
                            role: 'assistant',
                            content: response.response,
                            timestamp: new Date().toISOString(),
                            actions: response.actions || []
                        });

                        // Process any actions
                        if (response.actions && response.actions.length > 0) {
                            self.processActions(response.actions);
                        }
                    } else {
                        const errorMsg = response.message || 'Failed to send message';
                        self.showError(errorMsg, self.isLicenseError(errorMsg));
                    }
                },
                error: function(xhr) {
                    self.hideTypingIndicator();
                    const error = xhr.responseJSON?.message || 'Failed to send message';
                    self.showError(error, self.isLicenseError(error));
                }
            });
        },

        /**
         * Add message to chat UI
         */
        addMessage: function(message) {
            const $messages = $('.creator-chat-messages');
            const html = this.renderMessage(message);

            $messages.append(html);
            this.scrollToBottom();
        },

        /**
         * Render message HTML
         */
        renderMessage: function(message) {
            const isUser = message.role === 'user';
            const senderName = isUser ? creatorChat.userName : 'Creator AI';
            const timeStr = this.formatTime(message.timestamp);

            let html = `
                <div class="creator-message creator-message-${message.role}">
                    <div class="creator-message-avatar">
            `;

            if (isUser) {
                html += `<img src="${creatorChat.userAvatar}" alt="${senderName}">`;
            } else {
                html += `
                    <div class="creator-ai-avatar">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </div>
                `;
            }

            html += `
                    </div>
                    <div class="creator-message-content">
                        <div class="creator-message-header">
                            <span class="creator-message-sender">${this.escapeHtml(senderName)}</span>
                            <span class="creator-message-time">${timeStr}</span>
                        </div>
                        <div class="creator-message-body">
                            ${this.formatMessageContent(message.content, message.isError)}
                        </div>
            `;

            // Add action cards if present
            if (message.actions && message.actions.length > 0) {
                html += '<div class="creator-message-actions">';
                message.actions.forEach(function(action) {
                    html += CreatorChat.renderActionCard(action);
                });
                html += '</div>';
            }

            html += `
                    </div>
                </div>
            `;

            return html;
        },

        /**
         * Render action card HTML
         */
        renderActionCard: function(action) {
            const statusClass = 'creator-status-' + (action.status || 'pending');
            const statusText = this.getStatusText(action.status);
            const iconClass = this.getActionIcon(action.type);

            // Encode action data for storage in data attribute
            const actionDataEncoded = this.escapeHtml(JSON.stringify(action));

            let html = `
                <div class="creator-action-card" data-action-id="${action.id || ''}" data-action='${actionDataEncoded}'>
                    <div class="creator-action-header">
                        <div class="creator-action-icon">
                            <span class="dashicons ${iconClass}"></span>
                        </div>
                        <span class="creator-action-title">${this.escapeHtml(action.title || this.getActionTitle(action.type, action.params))}</span>
                        <span class="creator-action-status ${statusClass}">
                            ${statusText}
                        </span>
                    </div>
            `;

            if (action.target) {
                html += `<div class="creator-action-target">${this.escapeHtml(action.target)}</div>`;
            }

            if (action.status === 'failed' && action.error) {
                html += `
                    <div class="creator-action-error">
                        <span class="dashicons dashicons-warning"></span>
                        ${this.escapeHtml(action.error)}
                    </div>
                `;
            }

            // Add action buttons
            html += '<div class="creator-action-buttons">';

            if (action.status === 'pending') {
                html += `
                    <button class="creator-btn creator-btn-primary creator-btn-sm creator-action-btn"
                            data-action="execute">
                        <span class="dashicons dashicons-yes"></span> Execute
                    </button>
                    <button class="creator-btn creator-btn-secondary creator-btn-sm creator-action-btn"
                            data-action="skip">
                        Skip
                    </button>
                `;
            } else if (action.status === 'failed') {
                html += `
                    <button class="creator-btn creator-btn-secondary creator-btn-sm creator-retry-action">
                        <span class="dashicons dashicons-update"></span> Retry
                    </button>
                `;
            } else if (action.status === 'completed' && action.can_rollback) {
                html += `
                    <button class="creator-btn creator-btn-outline creator-btn-sm creator-rollback-action">
                        <span class="dashicons dashicons-undo"></span> Rollback
                    </button>
                `;
            }

            html += '</div></div>';

            return html;
        },

        /**
         * Get status text
         */
        getStatusText: function(status) {
            const statusMap = {
                'pending': '<span class="dashicons dashicons-clock"></span> Pending',
                'executing': '<span class="dashicons dashicons-update creator-spin"></span> Executing',
                'completed': '<span class="dashicons dashicons-yes-alt"></span> Completed',
                'failed': '<span class="dashicons dashicons-dismiss"></span> Failed'
            };
            return statusMap[status] || status;
        },

        /**
         * Get action icon
         */
        getActionIcon: function(type) {
            const iconMap = {
                'create_post': 'dashicons-edit',
                'create_page': 'dashicons-admin-page',
                'update_post': 'dashicons-update',
                'delete_post': 'dashicons-trash',
                'update_option': 'dashicons-admin-settings',
                'upload_media': 'dashicons-admin-media',
                'install_plugin': 'dashicons-plugins-checked',
                'update_elementor': 'dashicons-welcome-widgets-menus',
                'update_acf': 'dashicons-database',
                'update_rankmath': 'dashicons-chart-line',
                'update_woocommerce': 'dashicons-cart'
            };
            return iconMap[type] || 'dashicons-admin-generic';
        },

        /**
         * Process actions from response
         */
        processActions: function(actions) {
            const self = this;

            // Check for actions that should be auto-executed
            actions.forEach(function(action, index) {
                if (action.status === 'ready') {
                    // Auto-execute actions marked as ready
                    console.log('Auto-executing action:', action);

                    // Find the action card and execute it
                    setTimeout(function() {
                        const $card = $('.creator-action-card').eq(index);
                        if ($card.length) {
                            self.executeActionDirectly(action, $card);
                        }
                    }, 500); // Small delay for UI to render
                } else {
                    console.log('Action pending user confirmation:', action);
                }
            });
        },

        /**
         * Execute action directly (for auto-execution)
         */
        executeActionDirectly: function(action, $card) {
            const self = this;

            // Update status
            $card.find('.creator-action-status')
                .removeClass('creator-status-pending')
                .addClass('creator-status-executing')
                .html('<span class="dashicons dashicons-update creator-spin"></span> Executing');

            // Disable buttons
            $card.find('.creator-action-buttons button').prop('disabled', true);

            $.ajax({
                url: creatorChat.restUrl + 'actions/execute',
                type: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': creatorChat.restNonce
                },
                data: JSON.stringify({
                    action: action,
                    chat_id: self.chatId
                }),
                success: function(response) {
                    if (response.success) {
                        $card.find('.creator-action-status')
                            .removeClass('creator-status-executing')
                            .addClass('creator-status-completed')
                            .html('<span class="dashicons dashicons-yes-alt"></span> Completed');

                        // Show result info if available (data contains URLs etc.)
                        if (response.data) {
                            self.showActionResult(response.data, $card);
                        }

                        // Show rollback button if snapshot was created
                        if (response.snapshot_id) {
                            $card.find('.creator-action-buttons').html(`
                                <button class="creator-btn creator-btn-outline creator-btn-sm creator-rollback-action"
                                        data-snapshot-id="${response.snapshot_id}">
                                    <span class="dashicons dashicons-undo"></span> Rollback
                                </button>
                            `);
                        } else {
                            $card.find('.creator-action-buttons').empty();
                        }
                    } else {
                        self.handleActionError($card, response.message || response.error);
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || xhr.responseJSON?.error || 'Action failed';
                    self.handleActionError($card, error);
                }
            });
        },

        /**
         * Show action result (e.g., links to created content)
         */
        showActionResult: function(data, $card) {
            let resultHtml = '<div class="creator-action-result">';

            if (data.edit_url) {
                resultHtml += `<a href="${data.edit_url}" target="_blank" class="creator-btn creator-btn-sm creator-btn-link">
                    <span class="dashicons dashicons-edit"></span> Edit
                </a>`;
            }

            if (data.view_url) {
                resultHtml += `<a href="${data.view_url}" target="_blank" class="creator-btn creator-btn-sm creator-btn-link">
                    <span class="dashicons dashicons-visibility"></span> View
                </a>`;
            }

            if (data.elementor_url) {
                resultHtml += `<a href="${data.elementor_url}" target="_blank" class="creator-btn creator-btn-sm creator-btn-primary">
                    <span class="dashicons dashicons-welcome-widgets-menus"></span> Edit with Elementor
                </a>`;
            }

            resultHtml += '</div>';

            $card.find('.creator-action-buttons').before(resultHtml);
        },

        /**
         * Handle action button click
         */
        handleActionButton: function(e) {
            const $btn = $(e.currentTarget);
            const $card = $btn.closest('.creator-action-card');
            const actionId = $card.data('action-id');
            const action = $btn.data('action');

            if (action === 'execute') {
                this.executeAction(actionId, $card);
            } else if (action === 'skip') {
                $card.fadeOut();
            }
        },

        /**
         * Execute an action
         */
        executeAction: function(actionId, $card) {
            const self = this;

            // Get action data from card
            let actionData = null;
            try {
                const actionStr = $card.attr('data-action');
                if (actionStr) {
                    actionData = JSON.parse(actionStr);
                }
            } catch (e) {
                console.error('Failed to parse action data:', e);
            }

            if (!actionData) {
                self.handleActionError($card, 'No action data available');
                return;
            }

            // Update status
            $card.find('.creator-action-status')
                .removeClass('creator-status-pending')
                .addClass('creator-status-executing')
                .html('<span class="dashicons dashicons-update creator-spin"></span> Executing');

            // Disable buttons
            $card.find('.creator-action-buttons button').prop('disabled', true);

            $.ajax({
                url: creatorChat.restUrl + 'actions/execute',
                type: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': creatorChat.restNonce
                },
                data: JSON.stringify({
                    action: actionData,
                    chat_id: self.chatId
                }),
                success: function(response) {
                    if (response.success) {
                        $card.find('.creator-action-status')
                            .removeClass('creator-status-executing')
                            .addClass('creator-status-completed')
                            .html('<span class="dashicons dashicons-yes-alt"></span> Completed');

                        // Show result info if available (data contains URLs etc.)
                        if (response.data) {
                            self.showActionResult(response.data, $card);
                        }

                        // Show rollback button if snapshot was created
                        if (response.snapshot_id) {
                            $card.find('.creator-action-buttons').html(`
                                <button class="creator-btn creator-btn-outline creator-btn-sm creator-rollback-action"
                                        data-snapshot-id="${response.snapshot_id}">
                                    <span class="dashicons dashicons-undo"></span> Rollback
                                </button>
                            `);
                        } else {
                            $card.find('.creator-action-buttons').empty();
                        }
                    } else {
                        self.handleActionError($card, response.message || response.error);
                    }
                },
                error: function(xhr) {
                    const error = xhr.responseJSON?.message || xhr.responseJSON?.error || 'Action failed';
                    self.handleActionError($card, error);
                }
            });
        },

        /**
         * Get human-readable action title
         */
        getActionTitle: function(type, params) {
            const titles = {
                'create_page': 'Create Page' + (params?.title ? ': ' + params.title : ''),
                'create_post': 'Create Post' + (params?.title ? ': ' + params.title : ''),
                'update_page': 'Update Page' + (params?.title ? ': ' + params.title : ''),
                'update_post': 'Update Post' + (params?.title ? ': ' + params.title : ''),
                'delete_page': 'Delete Page',
                'delete_post': 'Delete Post',
                'create_plugin': 'Create Plugin' + (params?.name ? ': ' + params.name : ''),
                'update_elementor': 'Update Elementor',
                'update_acf': 'Update ACF Fields',
                'read_file': 'Read File' + (params?.path ? ': ' + params.path : ''),
                'write_file': 'Write File' + (params?.path ? ': ' + params.path : ''),
                'db_query': 'Database Query'
            };

            return titles[type] || type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },

        /**
         * Handle action error
         */
        handleActionError: function($card, error) {
            $card.find('.creator-action-status')
                .removeClass('creator-status-executing')
                .addClass('creator-status-failed')
                .html('<span class="dashicons dashicons-dismiss"></span> Failed');

            // Add error message
            if (!$card.find('.creator-action-error').length) {
                $card.find('.creator-action-header').after(`
                    <div class="creator-action-error">
                        <span class="dashicons dashicons-warning"></span>
                        ${this.escapeHtml(error)}
                    </div>
                `);
            }

            // Show retry button
            $card.find('.creator-action-buttons').html(`
                <button class="creator-btn creator-btn-secondary creator-btn-sm creator-retry-action">
                    <span class="dashicons dashicons-update"></span> Retry
                </button>
            `);
        },

        /**
         * Handle retry action
         */
        handleRetryAction: function(e) {
            const $card = $(e.currentTarget).closest('.creator-action-card');
            const actionId = $card.data('action-id');

            $card.find('.creator-action-error').remove();
            this.executeAction(actionId, $card);
        },

        /**
         * Handle rollback
         */
        handleRollback: function(e) {
            const $btn = $(e.currentTarget);
            const $card = $btn.closest('.creator-action-card');
            const actionId = $card.data('action-id');

            if (!confirm('Are you sure you want to rollback this action?')) {
                return;
            }

            $btn.prop('disabled', true).text('Rolling back...');

            $.ajax({
                url: creatorChat.restUrl + 'actions/' + actionId + '/rollback',
                type: 'POST',
                headers: {
                    'X-WP-Nonce': creatorChat.restNonce
                },
                success: function(response) {
                    if (response.success) {
                        $card.find('.creator-action-status')
                            .removeClass('creator-status-completed')
                            .addClass('creator-status-pending')
                            .html('<span class="dashicons dashicons-undo"></span> Rolled back');

                        $card.find('.creator-action-buttons').empty();
                    } else {
                        alert('Rollback failed: ' + response.message);
                        $btn.prop('disabled', false).html(
                            '<span class="dashicons dashicons-undo"></span> Rollback'
                        );
                    }
                },
                error: function(xhr) {
                    alert('Rollback failed: ' + (xhr.responseJSON?.message || 'Unknown error'));
                    $btn.prop('disabled', false).html(
                        '<span class="dashicons dashicons-undo"></span> Rollback'
                    );
                }
            });
        },

        /**
         * Handle edit title click
         */
        handleEditTitle: function() {
            const $title = $('.creator-chat-title');
            const currentTitle = $title.find('span').first().text();

            const newTitle = prompt('Enter new chat title:', currentTitle);

            if (newTitle && newTitle !== currentTitle) {
                $.ajax({
                    url: creatorChat.restUrl + 'chats/' + this.chatId,
                    type: 'PUT',
                    contentType: 'application/json',
                    headers: {
                        'X-WP-Nonce': creatorChat.restNonce
                    },
                    data: JSON.stringify({ title: newTitle }),
                    success: function(response) {
                        if (response.success) {
                            $title.find('span').first().text(newTitle);
                        }
                    }
                });
            }
        },

        /**
         * Show typing indicator
         */
        showTypingIndicator: function() {
            this.isTyping = true;

            const $indicator = $(`
                <div class="creator-typing-indicator" id="typing-indicator">
                    <div class="dot"></div>
                    <div class="dot"></div>
                    <div class="dot"></div>
                    <span>Creator AI is thinking...</span>
                </div>
            `);

            $('.creator-input-info').prepend($indicator);
            $('.creator-send-btn').prop('disabled', true);
        },

        /**
         * Hide typing indicator
         */
        hideTypingIndicator: function() {
            this.isTyping = false;
            $('#typing-indicator').remove();

            const hasValue = $('#creator-message-input').val().trim().length > 0;
            $('.creator-send-btn').prop('disabled', !hasValue);
        },

        /**
         * Check if error is license-related
         */
        isLicenseError: function(message) {
            if (!message) return false;
            const lowerMsg = message.toLowerCase();
            return lowerMsg.includes('license') ||
                   lowerMsg.includes('authenticated') ||
                   lowerMsg.includes('authentication') ||
                   lowerMsg.includes('site token') ||
                   lowerMsg.includes('not authorized');
        },

        /**
         * Show error message
         */
        showError: function(message, showSettingsLink) {
            const settingsUrl = creatorChat.settingsUrl || (creatorChat.adminUrl + '?page=creator-settings');
            let errorContent = '**' + message + '**';

            if (showSettingsLink) {
                errorContent += '\n\n[' + creatorChat.i18n.goToSettings + '](' + settingsUrl + ')';
            }

            this.addMessage({
                role: 'assistant',
                content: errorContent,
                isError: true,
                timestamp: new Date().toISOString()
            });
        },

        /**
         * Update URL with chat ID
         */
        updateUrl: function(chatId) {
            const url = new URL(window.location);
            url.searchParams.set('chat', chatId);
            window.history.replaceState({}, '', url);
        },

        /**
         * Scroll to bottom of messages
         */
        scrollToBottom: function() {
            const $messages = $('.creator-chat-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        },

        /**
         * Format message content
         */
        formatMessageContent: function(content, isError) {
            if (!content) return '';

            // Basic markdown-like formatting
            let formatted = this.escapeHtml(content);

            // Convert **bold** to <strong>
            formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // Convert *italic* to <em>
            formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');

            // Convert `code` to <code>
            formatted = formatted.replace(/`(.*?)`/g, '<code>$1</code>');

            // Convert [text](url) to <a href="url">text</a>
            formatted = formatted.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" class="creator-link">$1</a>');

            // Convert newlines to <br>
            formatted = formatted.replace(/\n/g, '<br>');

            // Wrap error messages in error styling
            if (isError) {
                formatted = '<span class="creator-error-text">' + formatted + '</span>';
            }

            return formatted;
        },

        /**
         * Format timestamp
         */
        formatTime: function(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) {
                return 'Just now';
            } else if (diff < 3600000) {
                const mins = Math.floor(diff / 60000);
                return mins + ' min ago';
            } else if (diff < 86400000) {
                const hours = Math.floor(diff / 3600000);
                return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
            } else {
                return date.toLocaleDateString();
            }
        },

        /**
         * Escape HTML entities
         */
        escapeHtml: function(text) {
            if (typeof text !== 'string') return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // ==========================================
        // File Attachment Methods
        // ==========================================

        /**
         * Handle attachment button click
         */
        handleAttachClick: function(e) {
            e.preventDefault();
            $('#creator-file-input').click();
        },

        /**
         * Handle file selection
         */
        handleFileSelect: function(e) {
            const files = e.target.files;
            const self = this;
            const maxFiles = creatorChat.maxFilesPerMessage || 3;
            const maxSize = creatorChat.maxFileSize || 10 * 1024 * 1024;

            if (!files || files.length === 0) return;

            // Check total files limit
            if (this.attachedFiles.length + files.length > maxFiles) {
                alert(creatorChat.i18n?.maxFilesError || 'Maximum ' + maxFiles + ' files allowed.');
                e.target.value = '';
                return;
            }

            // Process each file
            Array.from(files).forEach(function(file) {
                // Check file size
                if (file.size > maxSize) {
                    alert((creatorChat.i18n?.fileTooLarge || 'File too large:') + ' ' + file.name);
                    return;
                }

                // Read file as base64
                const reader = new FileReader();
                reader.onload = function(event) {
                    self.attachedFiles.push({
                        name: file.name,
                        type: file.type,
                        size: file.size,
                        base64: event.target.result
                    });
                    self.updateAttachmentPreview();
                    self.updateSendButton();
                };
                reader.readAsDataURL(file);
            });

            // Clear the input for next selection
            e.target.value = '';
        },

        /**
         * Handle attachment remove
         */
        handleAttachmentRemove: function(e) {
            e.preventDefault();
            const index = $(e.currentTarget).data('index');
            this.attachedFiles.splice(index, 1);
            this.updateAttachmentPreview();
            this.updateSendButton();
        },

        /**
         * Update attachment preview area
         */
        updateAttachmentPreview: function() {
            const $preview = $('#creator-attachment-preview');
            const $list = $preview.find('.creator-attachment-list');
            const $info = $('.creator-attachment-info');

            if (this.attachedFiles.length === 0) {
                $preview.hide();
                $info.hide();
                $list.empty();
                return;
            }

            $preview.show();
            $info.show();

            let html = '';
            this.attachedFiles.forEach(function(file, index) {
                const icon = this.getFileIcon(file.type);
                const size = this.formatFileSize(file.size);

                html += '<div class="creator-attachment-item" data-index="' + index + '">';
                html += '<span class="creator-attachment-icon">' + icon + '</span>';
                html += '<span class="creator-attachment-name">' + this.escapeHtml(file.name) + '</span>';
                html += '<span class="creator-attachment-size">' + size + '</span>';
                html += '<button type="button" class="creator-attachment-remove" data-index="' + index + '">';
                html += '<span class="dashicons dashicons-no-alt"></span>';
                html += '</button>';
                html += '</div>';
            }.bind(this));

            $list.html(html);
        },

        /**
         * Update send button state
         */
        updateSendButton: function() {
            const $btn = $('.creator-send-btn');
            const hasValue = $('#creator-message-input').val().trim().length > 0;
            const hasFiles = this.attachedFiles.length > 0;

            $btn.prop('disabled', (!hasValue && !hasFiles) || this.isTyping);
        },

        /**
         * Clear all attachments
         */
        clearAttachments: function() {
            this.attachedFiles = [];
            this.pendingFiles = [];
            $('#creator-file-input').val('');
            this.updateAttachmentPreview();
        },

        /**
         * Get file icon based on type
         */
        getFileIcon: function(mimeType) {
            if (mimeType.startsWith('image/')) {
                return '<span class="dashicons dashicons-format-image"></span>';
            } else if (mimeType === 'application/pdf') {
                return '<span class="dashicons dashicons-media-document"></span>';
            } else if (mimeType.includes('spreadsheet') || mimeType.includes('excel')) {
                return '<span class="dashicons dashicons-media-spreadsheet"></span>';
            } else if (mimeType.includes('word') || mimeType.includes('document')) {
                return '<span class="dashicons dashicons-media-document"></span>';
            } else if (mimeType.includes('javascript') || mimeType.includes('json') ||
                       mimeType.includes('php') || mimeType.includes('html') ||
                       mimeType.includes('css') || mimeType.includes('sql')) {
                return '<span class="dashicons dashicons-editor-code"></span>';
            } else if (mimeType === 'text/plain') {
                return '<span class="dashicons dashicons-text"></span>';
            }
            return '<span class="dashicons dashicons-media-default"></span>';
        },

        /**
         * Format file size for display
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.creator-chat-container').length) {
            CreatorChat.init();
        }
    });

})(jQuery);
