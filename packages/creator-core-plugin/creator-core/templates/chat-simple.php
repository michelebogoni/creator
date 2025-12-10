<?php
/**
 * Simple Chat Template (Phase 2)
 *
 * Basic chat interface for testing: Message -> Firebase -> AI -> Response
 *
 * @package CreatorCore
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
    <div id="creator-simple-chat">
        <div class="creator-simple-header">
            <h2>Creator - Simple Chat</h2>
            <p>Phase 2: Basic Chat Loop Test</p>
        </div>

        <div id="creator-simple-messages">
            <!-- Welcome message -->
            <div class="creator-simple-message assistant">
                <div class="creator-simple-avatar">🤖</div>
                <div class="creator-simple-content">
                    <div class="creator-simple-text">Ciao! Sono Creator, il tuo assistente AI per WordPress. Chiedimi qualcosa, ad esempio: "Dimmi che versione di WordPress ho"</div>
                </div>
            </div>
        </div>

        <div class="creator-simple-input-area">
            <form id="creator-simple-chat-form">
                <input type="text" id="creator-simple-input" placeholder="Scrivi il tuo messaggio..." autocomplete="off" />
                <button type="submit" id="creator-simple-send">Invia</button>
            </form>
        </div>

        <div class="creator-simple-footer">
            Endpoint: <code>POST /wp-json/creator/v1/chat</code> |
            Route semplificata per test Fase 2
        </div>
    </div>
</div>
