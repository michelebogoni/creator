/**
 * Creator Core - Simple Chat Scripts (Phase 2/3)
 *
 * Chat loop with code execution: Message -> Firebase -> AI -> Response -> Execute
 *
 * @package CreatorCore
 */

(function($) {
    'use strict';

    /**
     * Simple Chat Manager (Phase 2)
     */
    const CreatorSimpleChat = {
        conversationHistory: [],
        isThinking: false,

        /**
         * Initialize simple chat
         */
        init: function() {
            this.conversationHistory = [];
            this.bindEvents();
            console.log('[CreatorSimpleChat] Initialized');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Simple chat form
            $(document).on('submit', '#creator-simple-chat-form', this.handleSubmit.bind(this));

            // Enter key submit
            $(document).on('keydown', '#creator-simple-input', this.handleKeydown.bind(this));
        },

        /**
         * Handle form submission
         */
        handleSubmit: function(e) {
            e.preventDefault();

            const $input = $('#creator-simple-input');
            const message = $input.val().trim();

            if (!message || this.isThinking) {
                return;
            }

            // Add user message to UI
            this.addMessage('user', message);

            // Clear input
            $input.val('');

            // Send message
            this.sendMessage(message);
        },

        /**
         * Handle keydown (Enter to submit)
         */
        handleKeydown: function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $('#creator-simple-chat-form').trigger('submit');
            }
        },

        /**
         * Send message to simple /chat endpoint
         */
        sendMessage: function(message) {
            const self = this;

            // Show thinking status
            this.showThinking();

            // Add to conversation history
            this.conversationHistory.push({
                role: 'user',
                content: message
            });

            // Send to simple /chat endpoint
            $.ajax({
                url: creatorChat.restUrl + 'chat',
                type: 'POST',
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': creatorChat.restNonce
                },
                data: JSON.stringify({
                    message: message,
                    conversation_id: 'simple-' + Date.now(),
                    conversation_history: this.conversationHistory
                }),
                success: function(response) {
                    self.hideThinking();

                    if (response.success && response.response) {
                        const aiMessage = response.response.message || response.response;

                        // Add AI response to UI
                        self.addMessage('assistant', aiMessage, response.response);

                        // Add to conversation history
                        self.conversationHistory.push({
                            role: 'assistant',
                            content: aiMessage
                        });

                        // Log usage info
                        console.log('[CreatorSimpleChat] Response received:', {
                            model: response.model,
                            tokens: response.tokens_used,
                            type: response.response.type,
                            step: response.response.step
                        });
                    } else {
                        self.showError(response.error || 'Unknown error');
                    }
                },
                error: function(xhr, status, error) {
                    self.hideThinking();
                    const errorMsg = xhr.responseJSON?.message || xhr.responseJSON?.error || error || 'Request failed';
                    self.showError(errorMsg);
                    console.error('[CreatorSimpleChat] Error:', errorMsg);
                }
            });
        },

        /**
         * Add message to UI
         */
        addMessage: function(role, content, metadata) {
            const $messages = $('#creator-simple-messages');

            const isUser = role === 'user';
            const avatarClass = isUser ? 'user' : 'assistant';
            const avatarIcon = isUser ? '👤' : '🤖';

            let html = '<div class="creator-simple-message ' + avatarClass + '">';
            html += '<div class="creator-simple-avatar">' + avatarIcon + '</div>';
            html += '<div class="creator-simple-content">';
            html += '<div class="creator-simple-text">' + this.escapeHtml(content) + '</div>';

            // Show metadata for AI responses
            if (!isUser && metadata) {
                html += '<div class="creator-simple-meta">';
                if (metadata.step) html += '<span class="meta-step">Step: ' + metadata.step + '</span>';
                if (metadata.type) html += '<span class="meta-type">Type: ' + metadata.type + '</span>';
                if (metadata.status) html += '<span class="meta-status">' + metadata.status + '</span>';
                html += '</div>';

                // Phase 3: Show execution result if present
                if (metadata.execution_result) {
                    html += this.formatExecutionResult(metadata.execution_result);
                }
            }

            html += '</div></div>';

            $messages.append(html);
            this.scrollToBottom();
        },

        /**
         * Format execution result for display
         */
        formatExecutionResult: function(result) {
            let html = '<div class="creator-execution-result">';

            if (result.success) {
                html += '<div class="execution-success">';
                html += '<span class="execution-icon">✅</span>';
                html += '<span class="execution-label">Code executed successfully</span>';
                html += '</div>';

                // Show output if present
                if (result.output && result.output.trim()) {
                    html += '<div class="execution-output">';
                    html += '<strong>Output:</strong><br>';
                    html += '<code>' + this.escapeHtml(result.output) + '</code>';
                    html += '</div>';
                }

                // Show result data if present
                if (result.result && typeof result.result === 'object') {
                    html += '<div class="execution-data">';
                    html += '<strong>Result:</strong><br>';
                    html += '<code>' + this.escapeHtml(JSON.stringify(result.result, null, 2)) + '</code>';
                    html += '</div>';
                }
            } else {
                html += '<div class="execution-error">';
                html += '<span class="execution-icon">❌</span>';
                html += '<span class="execution-label">Execution failed</span>';
                html += '</div>';

                if (result.error) {
                    html += '<div class="execution-error-message">';
                    html += '<code>' + this.escapeHtml(result.error) + '</code>';
                    html += '</div>';
                }
            }

            html += '</div>';
            return html;
        },

        /**
         * Show thinking indicator
         */
        showThinking: function() {
            this.isThinking = true;

            const $messages = $('#creator-simple-messages');
            const html = '<div class="creator-simple-thinking" id="thinking-indicator">' +
                '<div class="creator-simple-avatar">🤖</div>' +
                '<div class="creator-simple-content">' +
                '<div class="creator-thinking-dots">' +
                '<span>Thinking</span>' +
                '<span class="dot">.</span>' +
                '<span class="dot">.</span>' +
                '<span class="dot">.</span>' +
                '</div></div></div>';

            $messages.append(html);
            this.scrollToBottom();

            // Disable input
            $('#creator-simple-input').prop('disabled', true);
            $('#creator-simple-send').prop('disabled', true);
        },

        /**
         * Hide thinking indicator
         */
        hideThinking: function() {
            this.isThinking = false;
            $('#thinking-indicator').remove();

            // Enable input
            $('#creator-simple-input').prop('disabled', false);
            $('#creator-simple-send').prop('disabled', false);
            $('#creator-simple-input').focus();
        },

        /**
         * Show error message
         */
        showError: function(message) {
            const $messages = $('#creator-simple-messages');
            const html = '<div class="creator-simple-message error">' +
                '<div class="creator-simple-avatar">⚠️</div>' +
                '<div class="creator-simple-content">' +
                '<div class="creator-simple-text error-text">' + this.escapeHtml(message) + '</div>' +
                '</div></div>';

            $messages.append(html);
            this.scrollToBottom();
        },

        /**
         * Scroll messages to bottom
         */
        scrollToBottom: function() {
            const $messages = $('#creator-simple-messages');
            $messages.scrollTop($messages[0].scrollHeight);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when document ready
    $(document).ready(function() {
        // Only init if simple chat container exists
        if ($('#creator-simple-chat').length) {
            CreatorSimpleChat.init();
        }
    });

    // Expose globally for debugging
    window.CreatorSimpleChat = CreatorSimpleChat;

})(jQuery);
