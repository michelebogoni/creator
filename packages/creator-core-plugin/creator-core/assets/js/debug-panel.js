/**
 * Creator Core - Debug Panel
 *
 * @package CreatorCore
 */

(function($) {
    'use strict';

    const DebugPanel = {
        panel: null,
        sessionsList: null,
        logsContent: null,
        currentSession: null,

        init: function() {
            this.panel = $('#creator-debug-panel');
            this.sessionsList = $('#creator-debug-sessions-list');
            this.logsContent = $('#creator-debug-logs-content');

            this.bindEvents();
        },

        bindEvents: function() {
            // Open debug panel
            $('#creator-debug-btn').on('click', () => {
                this.open();
            });

            // Close debug panel
            $('#creator-debug-close, .creator-debug-panel-overlay').on('click', () => {
                this.close();
            });

            // Refresh logs
            $('#creator-debug-refresh').on('click', () => {
                this.loadSessions();
                if (this.currentSession) {
                    this.loadSessionLogs(this.currentSession);
                }
            });

            // Clear logs
            $('#creator-debug-clear').on('click', () => {
                if (confirm('Are you sure you want to clear all debug logs?')) {
                    this.clearLogs();
                }
            });

            // ESC key to close
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.panel.is(':visible')) {
                    this.close();
                }
            });
        },

        open: function() {
            this.panel.show();
            this.loadSessions();
        },

        close: function() {
            this.panel.hide();
        },

        loadSessions: function() {
            this.sessionsList.html('<p class="loading">Loading sessions...</p>');

            $.ajax({
                url: creatorChat.restUrl + 'debug/sessions',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': creatorChat.restNonce
                },
                success: (response) => {
                    if (response.success && response.sessions.length > 0) {
                        this.renderSessions(response.sessions);
                    } else {
                        this.sessionsList.html('<p class="placeholder">No debug sessions found. Send a message to generate logs.</p>');
                    }
                },
                error: (xhr) => {
                    this.sessionsList.html('<p class="error">Error loading sessions: ' + xhr.responseJSON?.message || 'Unknown error' + '</p>');
                }
            });
        },

        renderSessions: function(sessions) {
            let html = '';

            sessions.forEach((session) => {
                const isActive = this.currentSession === session.session_id ? 'active' : '';
                html += `
                    <div class="creator-debug-session-item ${isActive}" data-session-id="${session.session_id}">
                        <div class="creator-debug-session-time">${this.formatTime(session.timestamp)}</div>
                        <div class="creator-debug-session-message">${this.escapeHtml(session.user_message || 'No message')}</div>
                    </div>
                `;
            });

            this.sessionsList.html(html);

            // Bind click events
            this.sessionsList.find('.creator-debug-session-item').on('click', (e) => {
                const sessionId = $(e.currentTarget).data('session-id');
                this.selectSession(sessionId);
            });

            // Auto-select first session if none selected
            if (!this.currentSession && sessions.length > 0) {
                this.selectSession(sessions[0].session_id);
            }
        },

        selectSession: function(sessionId) {
            this.currentSession = sessionId;

            // Update active state
            this.sessionsList.find('.creator-debug-session-item').removeClass('active');
            this.sessionsList.find(`[data-session-id="${sessionId}"]`).addClass('active');

            // Load logs
            this.loadSessionLogs(sessionId);
        },

        loadSessionLogs: function(sessionId) {
            this.logsContent.html('<p class="loading">Loading logs...</p>');

            $.ajax({
                url: creatorChat.restUrl + 'debug/session/' + sessionId,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': creatorChat.restNonce
                },
                success: (response) => {
                    if (response.success && response.logs.length > 0) {
                        this.renderLogs(response.logs);
                    } else {
                        this.logsContent.html('<p class="placeholder">No logs found for this session.</p>');
                    }
                },
                error: (xhr) => {
                    this.logsContent.html('<p class="error">Error loading logs: ' + (xhr.responseJSON?.message || 'Unknown error') + '</p>');
                }
            });
        },

        renderLogs: function(logs) {
            let html = '';

            logs.forEach((log) => {
                const typeClass = 'type-' + (log.type || 'unknown');
                html += `
                    <div class="creator-debug-log-entry ${typeClass}">
                        <div class="creator-debug-log-header">
                            <span class="creator-debug-log-type">${this.escapeHtml(log.type || 'Unknown')}</span>
                            ${log.iteration ? `<span class="creator-debug-log-iteration">Iteration ${log.iteration}</span>` : ''}
                            <span class="creator-debug-log-time">${this.formatTime(log.timestamp)}</span>
                        </div>
                        ${this.renderLogDetails(log)}
                    </div>
                `;
            });

            this.logsContent.html(html);
        },

        renderLogDetails: function(log) {
            let html = '<div class="creator-debug-log-details">';

            switch (log.type) {
                case 'SESSION_START':
                    html += `<p><strong>Chat ID:</strong> ${log.chat_id || 'N/A'}</p>`;
                    html += `<p><strong>User Message:</strong> ${this.escapeHtml(log.user_message || 'N/A')}</p>`;
                    break;

                case 'CONTEXT':
                    html += '<details><summary>WordPress Context</summary>';
                    html += `<pre>${this.formatJson(log.context)}</pre>`;
                    html += '</details>';
                    break;

                case 'AI_REQUEST':
                    html += `<p><strong>Message:</strong></p>`;
                    html += `<pre>${this.escapeHtml(log.message || 'N/A')}</pre>`;
                    if (log.documentation) {
                        html += `<p><strong>Documentation:</strong> ${log.documentation.join(', ')}</p>`;
                    }
                    break;

                case 'AI_RESPONSE':
                    if (log.is_error) {
                        html += `<p class="error"><strong>Error:</strong> ${this.escapeHtml(log.error_message || 'Unknown error')}</p>`;
                    } else {
                        html += '<details open><summary>Response</summary>';
                        html += `<pre>${this.formatJson(log.response)}</pre>`;
                        html += '</details>';
                    }
                    break;

                case 'PROCESSED_RESPONSE':
                    html += `<p><strong>Type:</strong> ${log.response_type || 'N/A'}</p>`;
                    html += `<p><strong>Step:</strong> ${log.step || 'N/A'}</p>`;
                    html += `<p><strong>Status:</strong> ${this.escapeHtml(log.status || 'N/A')}</p>`;
                    html += `<p><strong>Continue Auto:</strong> ${log.continue_automatically ? 'Yes' : 'No'}</p>`;
                    if (log.has_code) {
                        html += '<details><summary>Code Preview</summary>';
                        html += `<pre>${this.escapeHtml(log.code_preview || 'N/A')}</pre>`;
                        html += '</details>';
                    }
                    if (log.message) {
                        html += '<details><summary>Message</summary>';
                        html += `<pre>${this.escapeHtml(log.message)}</pre>`;
                        html += '</details>';
                    }
                    break;

                case 'CODE_EXECUTION':
                    html += `<p><strong>Success:</strong> ${log.success ? 'Yes' : 'No'}</p>`;
                    html += '<details open><summary>Code</summary>';
                    html += `<pre>${this.escapeHtml(log.code || 'N/A')}</pre>`;
                    html += '</details>';
                    html += '<details open><summary>Result</summary>';
                    html += `<pre>${this.formatJson(log.result)}</pre>`;
                    html += '</details>';
                    break;

                case 'DOCUMENTATION':
                    html += `<p><strong>Requested:</strong> ${(log.plugins_requested || []).join(', ')}</p>`;
                    html += `<p><strong>Received:</strong> ${(log.docs_received || []).join(', ')}</p>`;
                    if (log.doc_sizes) {
                        html += '<details><summary>Doc Sizes</summary>';
                        html += `<pre>${this.formatJson(log.doc_sizes)}</pre>`;
                        html += '</details>';
                    }
                    break;

                case 'RETRY':
                    html += `<p><strong>Retry Count:</strong> ${log.retry_count || 0}</p>`;
                    html += '<details open><summary>Error</summary>';
                    html += `<pre>${this.formatJson(log.error)}</pre>`;
                    html += '</details>';
                    break;

                case 'SESSION_END':
                    html += `<p><strong>Total Iterations:</strong> ${log.total_iterations || 0}</p>`;
                    html += `<p><strong>Final Type:</strong> ${log.final_type || 'N/A'}</p>`;
                    html += `<p><strong>Final Status:</strong> ${this.escapeHtml(log.final_status || 'N/A')}</p>`;
                    if (log.steps_summary && log.steps_summary.length > 0) {
                        html += '<details><summary>Steps Summary</summary>';
                        html += `<pre>${this.formatJson(log.steps_summary)}</pre>`;
                        html += '</details>';
                    }
                    break;

                default:
                    html += `<pre>${this.formatJson(log)}</pre>`;
            }

            html += '</div>';
            return html;
        },

        clearLogs: function() {
            $.ajax({
                url: creatorChat.restUrl + 'debug/clear',
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': creatorChat.restNonce
                },
                success: (response) => {
                    if (response.success) {
                        this.currentSession = null;
                        this.sessionsList.html('<p class="placeholder">Logs cleared. Send a message to generate new logs.</p>');
                        this.logsContent.html('<p class="placeholder">Select a session to view logs</p>');
                    } else {
                        alert('Failed to clear logs: ' + (response.message || 'Unknown error'));
                    }
                },
                error: (xhr) => {
                    alert('Error clearing logs: ' + (xhr.responseJSON?.message || 'Unknown error'));
                }
            });
        },

        formatTime: function(timestamp) {
            if (!timestamp) return 'N/A';
            const date = new Date(timestamp.replace(' ', 'T'));
            return date.toLocaleString();
        },

        formatJson: function(obj) {
            try {
                return this.escapeHtml(JSON.stringify(obj, null, 2));
            } catch (e) {
                return this.escapeHtml(String(obj));
            }
        },

        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        DebugPanel.init();
    });

})(jQuery);
