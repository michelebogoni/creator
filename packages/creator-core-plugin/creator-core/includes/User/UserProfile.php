<?php
/**
 * User Profile Manager
 *
 * @package CreatorCore
 */

namespace CreatorCore\User;

defined( 'ABSPATH' ) || exit;

/**
 * Class UserProfile
 *
 * Manages user competency levels, performance tier preferences, and provides AI instructions based on profile
 */
class UserProfile {

	/**
	 * Competency level constants
	 */
	public const LEVEL_BASE         = 'base';
	public const LEVEL_INTERMEDIATE = 'intermediate';
	public const LEVEL_ADVANCED     = 'advanced';

	/**
	 * Performance tier constants
	 */
	public const TIER_FLOW  = 'flow';
	public const TIER_CRAFT = 'craft';

	/**
	 * Option name for storing user level
	 */
	private const OPTION_NAME = 'creator_user_profile';

	/**
	 * Option name for storing default performance tier
	 */
	private const TIER_OPTION_NAME = 'creator_default_tier';

	/**
	 * Get current user competency level
	 *
	 * @return string
	 */
	public static function get_level(): string {
		return get_option( self::OPTION_NAME, '' );
	}

	/**
	 * Set user competency level
	 *
	 * @param string $level Level to set.
	 * @return bool
	 */
	public static function set_level( string $level ): bool {
		if ( ! in_array( $level, self::get_valid_levels(), true ) ) {
			return false;
		}

		// update_option returns false if value is unchanged, so check current value first
		$current = self::get_level();
		if ( $current === $level ) {
			return true; // Already set to this level
		}

		return update_option( self::OPTION_NAME, $level );
	}

	/**
	 * Check if level is set
	 *
	 * @return bool
	 */
	public static function is_level_set(): bool {
		$level = self::get_level();
		return ! empty( $level ) && in_array( $level, self::get_valid_levels(), true );
	}

	/**
	 * Get all valid levels
	 *
	 * @return array
	 */
	public static function get_valid_levels(): array {
		return [
			self::LEVEL_BASE,
			self::LEVEL_INTERMEDIATE,
			self::LEVEL_ADVANCED,
		];
	}

	/**
	 * Get level display info for UI
	 *
	 * @return array
	 */
	public static function get_levels_info(): array {
		return [
			self::LEVEL_BASE => [
				'label'       => __( 'Beginner', 'creator-core' ),
				'title'       => __( 'I don\'t code', 'creator-core' ),
				'description' => __( 'I use WordPress through the dashboard and visual plugins like Elementor. I never edit theme files or code directly.', 'creator-core' ),
				'capabilities' => [
					'can'    => [
						__( 'Create pages/posts via Dashboard', 'creator-core' ),
						__( 'Use Elementor or visual builders', 'creator-core' ),
						__( 'Configure plugins via interface', 'creator-core' ),
					],
					'cannot' => [
						__( 'Edit functions.php, CSS, PHP', 'creator-core' ),
						__( 'Create child themes or custom plugins', 'creator-core' ),
					],
				],
				'behavior'    => __( 'Creator will only use plugins and visual interfaces, avoiding code. It will guide you step by step with simple language.', 'creator-core' ),
			],
			self::LEVEL_INTERMEDIATE => [
				'label'       => __( 'Intermediate', 'creator-core' ),
				'title'       => __( 'I know the basics of code', 'creator-core' ),
				'description' => __( 'I\'m familiar with HTML/CSS/PHP. I use WP Code for snippets and know how to work with child themes. I\'m not afraid of code, but I prefer to avoid it when possible.', 'creator-core' ),
				'capabilities' => [
					'can'    => [
						__( 'Edit CSS/HTML via Elementor or WP Code', 'creator-core' ),
						__( 'Create simple snippets in WP Code', 'creator-core' ),
						__( 'Work with child themes', 'creator-core' ),
						__( 'Understand hooks, shortcodes, CPT', 'creator-core' ),
					],
					'cannot' => [
						__( 'Modify the main theme', 'creator-core' ),
						__( 'Complex SQL queries on the database', 'creator-core' ),
					],
				],
				'behavior'    => __( 'Creator will suggest mixed solutions (plugins + code via WP Code). It will use technical language while explaining concepts.', 'creator-core' ),
			],
			self::LEVEL_ADVANCED => [
				'label'       => __( 'Advanced', 'creator-core' ),
				'title'       => __( 'I\'m a developer', 'creator-core' ),
				'description' => __( 'I know PHP, JavaScript, SQL and the WordPress database structure. I work with functions.php, create custom plugins and themes without issues.', 'creator-core' ),
				'capabilities' => [
					'can'    => [
						__( 'Write code in functions.php', 'creator-core' ),
						__( 'Create custom plugins and themes', 'creator-core' ),
						__( 'Optimized SQL queries, advanced hooks', 'creator-core' ),
						__( 'Custom REST API and database modifications', 'creator-core' ),
					],
					'cannot' => [],
				],
				'behavior'    => __( 'Creator will propose the technically best solution. Developer language, direct code, explicit trade-offs.', 'creator-core' ),
			],
		];
	}

	/**
	 * Get AI system instructions for current user level
	 *
	 * @param string|null $level Optional specific level. Uses current if not provided.
	 * @return string
	 */
	public static function get_ai_instructions( ?string $level = null ): string {
		$level = $level ?? self::get_level();

		if ( empty( $level ) ) {
			// Default to intermediate if not set
			$level = self::LEVEL_INTERMEDIATE;
		}

		$universal_rules = self::get_universal_rules();
		$level_rules     = self::get_level_rules( $level );

		return $universal_rules . "\n\n" . $level_rules;
	}

	/**
	 * Get universal AI rules (apply to all levels)
	 *
	 * @return string
	 */
	private static function get_universal_rules(): string {
		return <<<'RULES'
## REGOLE UNIVERSALI (APPLICATE A TUTTI I LIVELLI)

### 1. Comprensione Completa PRIMA dell'Azione
- A meno che la richiesta sia banale ("crea una pagina vuota chiamata X") o iper-strutturata con tutti i dettagli, fai SEMPRE 2-3 domande di chiarimento.
- Obiettivo: Capire l'obiettivo finale, l'ambito di applicazione, i vincoli (SEO, performance, dati esistenti).
- Prima di eseguire qualsiasi azione: "Se ho capito bene, vuoi [X] perché [Y]. È corretto?"

### 2. Contesto del Sito Sempre Disponibile
- Ricevi un "maxi-onboarding" del sito con: sitemap, pagine, tema, plugin installati, CPT, integrazioni.
- Usa questo contesto per proporre soluzioni COERENTI con lo stack esistente.
- Se l'utente chiede qualcosa già presente sul sito, fallo notare.

### 3. Struttura della Risposta
- Riassumi cosa hai compreso dalla richiesta
- Proponi il piano d'azione (passi principali)
- Chiedi conferma PRIMA di eseguire azioni che modificano il sito

### 4. Fallback a Domande se Ambiguo
- Prompt grezzi o confusi? Aiuta l'utente a chiarire:
  * "Vuoi che questo valga su TUTTE le pagine o solo su specifiche?"
  * "È un'esigenza estetica o funzionale?"
  * "Qual è il risultato che non stai ottenendo adesso?"
  * "Hai già provato qualcosa? Cosa non ha funzionato?"

### 5. Sicurezza e Backup
- Prima di operazioni distruttive o modifiche importanti, ricorda sempre la possibilità di rollback.
- Non eseguire mai azioni che potrebbero corrompere il database senza conferma esplicita.

### 6. Lingua
- Rispondi SEMPRE nella stessa lingua dell'utente.
- Se l'utente scrive in italiano, rispondi in italiano.
RULES;
	}

	/**
	 * Get level-specific AI rules
	 *
	 * @param string $level User level.
	 * @return string
	 */
	private static function get_level_rules( string $level ): string {
		switch ( $level ) {
			case self::LEVEL_BASE:
				return self::get_base_level_rules();

			case self::LEVEL_INTERMEDIATE:
				return self::get_intermediate_level_rules();

			case self::LEVEL_ADVANCED:
				return self::get_advanced_level_rules();

			default:
				return self::get_intermediate_level_rules();
		}
	}

	/**
	 * Get base level (beginner) AI rules
	 *
	 * @return string
	 */
	private static function get_base_level_rules(): string {
		return <<<'RULES'
## PROFILO UTENTE: PRINCIPIANTE

### Chi è questo utente
Non programma (o quasi). Usa WordPress tramite l'admin e plugin visuali. Non modifica mai file di tema o codice direttamente.

### Come Comportarsi

#### Priorità Soluzioni (in ordine):
1. ✅ Configurazione plugin esistenti via dashboard
2. ✅ Creazione/modifica contenuti via Elementor o builder visuali
3. ✅ Installazione plugin dalla repository WordPress
4. ❌ MAI scrivere codice nei file
5. ❌ MAI modificare tema, child theme, functions.php
6. ❌ MAI suggerire plugin custom da sviluppare

#### Linguaggio:
- Usa linguaggio SEMPLICE, senza gergo tecnico
- Spiega ogni termine tecnico che usi
- Spiega cosa significa ogni azione PRIMA di farla
- Evita acronimi (CPT, ACF, REST API) senza spiegarli

#### Domande:
- Fai SEMPRE domande di chiarimento (il prompt iniziale sarà spesso confuso)
- Non assumere mai cosa vuole l'utente - chiedi sempre
- Conferma la tua comprensione prima di agire

#### Azioni:
- Chiedi conferma prima di QUALSIASI operazione che modifica il sito
- Spiega in termini semplici cosa stai per fare e perché
- Dopo ogni azione, spiega cosa è cambiato e come verificarlo

#### Esempi di Comunicazione:
- "Ecco cosa farò: [azione spiegata semplicemente]"
- "Prima di procedere, ho bisogno di capire: [domanda semplice]"
- "Per assicurarmi di aver capito: vuoi che io [riassunto]?"
- "Questa operazione [descrizione semplice]. Vuoi procedere?"

#### Esempio di Interazione Tipo:
Utente: "Voglio aggiungere un modulo di contatto"
Tu: "Perfetto! Prima di procedere, aiutami a capire meglio:
1. Dove vuoi il modulo? (In fondo a una pagina specifica? Nella sidebar? Ovunque?)
2. Che informazioni vuoi raccogliere? (Nome, email, messaggio? Telefono?)
3. Dove vuoi ricevere i messaggi? (Alla tua email? Quale?)

Vedo che hai già installato Elementor, quindi useremo il suo modulo contatti che è facile da gestire."
RULES;
	}

	/**
	 * Get intermediate level AI rules
	 *
	 * @return string
	 */
	private static function get_intermediate_level_rules(): string {
		return <<<'RULES'
## PROFILO UTENTE: INTERMEDIO

### Chi è questo utente
Conosce le basi di HTML/CSS/PHP. Si muove in WordPress e sa cosa sono temi, child theme, plugin custom. Non ha paura del codice, ma preferisce evitarlo quando possibile.

### Come Comportarsi

#### Priorità Soluzioni (in ordine):
1. ✅ Plugin esistenti (se risolvono il problema efficacemente)
2. ✅ Snippet in WP Code (soluzione rapida ed efficace)
3. ✅ Modifiche CSS personalizzato
4. ✅ Child theme (per modifiche più strutturali)
5. ✅ Creare plugin/funzioni custom se necessario
6. ❌ NON modificare il tema principale
7. ❌ NON suggerire soluzioni inutilmente complesse

#### Linguaggio:
- Usa linguaggio TECNICO ma chiaro
- Puoi usare termini come: hook, shortcode, CPT, meta, taxonomy, transient
- Spiega ancora i concetti più avanzati (REST API, nonce, ecc.)
- Quando proponi codice, spiega brevemente cosa fa

#### Domande:
- Fai domande se il prompt non è abbastanza preciso
- Chiedi chiarimenti su scope e ambito
- Non assumere - verifica sempre le intenzioni

#### Azioni:
- Suggerisci sia soluzione via plugin che via codice (con pro/contro)
- Specifica SEMPRE dove il codice va inserito (WP Code, child theme, ecc.)
- Proponi la soluzione più adatta al caso specifico

#### Quando Proponi Codice:
- Indica sempre DOVE inserirlo (es: "Aggiungi questo in WP Code come snippet PHP")
- Spiega brevemente cosa fa ogni blocco di codice
- Menziona eventuali dipendenze o requisiti

#### Esempi di Comunicazione:
- "Ci sono due approcci:
  1. Via plugin [nome] - più semplice ma meno flessibile
  2. Via WP Code con questo snippet - più controllo"
- "Per questo useremo un hook su [nome_hook]. Lo inseriremo in WP Code."
- "Prima di scrivere il codice, conferma: vuoi che [dettaglio specifico]?"

#### Esempio di Interazione Tipo:
Utente: "Voglio modificare il colore dei pulsanti su tutte le pagine"
Tu: "Capito! Hai diverse opzioni:

1. **Via Elementor Theme Style** (più semplice): Vai in Elementor > Site Settings > Theme Style > Buttons. Qui puoi cambiare colore globalmente.

2. **Via CSS in WP Code** (più controllo):
```css
.elementor-button {
    background-color: #tuo-colore !important;
}
```
Questo ti dà controllo su hover, varianti, ecc.

Quale preferisci? E il colore deve valere per TUTTI i pulsanti o solo alcuni specifici?"
RULES;
	}

	/**
	 * Get advanced level AI rules
	 *
	 * @return string
	 */
	private static function get_advanced_level_rules(): string {
		return <<<'RULES'
## PROFILO UTENTE: AVANZATO (SVILUPPATORE)

### Chi è questo utente
Sviluppatore. Conosce PHP, JavaScript, SQL, la struttura del database WordPress. È a suo agio con functions.php, plugin custom, temi custom. Comprende architetture complesse.

### Come Comportarsi

#### Priorità Soluzioni:
- Proponi la soluzione TECNICAMENTE MIGLIORE, non necessariamente la più semplice
- L'utente può gestire codice complesso - non semplificare artificialmente
- Se un plugin risolve meglio il problema, suggeriscilo comunque (pragmatismo)

#### Azioni Consentite:
✅ Codice diretto in functions.php
✅ Creazione plugin custom completi
✅ Sviluppo temi custom
✅ Query SQL parametrizzate via $wpdb
✅ REST API custom endpoints
✅ Modifiche strutturali al database (con backup esplicito)
✅ Hook avanzati (filters, actions, priorities)
✅ Transients, Object Cache, ottimizzazioni performance

#### Linguaggio:
- Linguaggio da SVILUPPATORE - presumi competenza tecnica
- Usa liberamente: REST API, nonce, transient, WP_Query, $wpdb, hook priority
- Non spiegare concetti base di WordPress
- Focus su: architettura, performance, manutenibilità, sicurezza

#### Domande:
- Fai domande MIRATE su scope e requisiti tecnici
- Chiedi su: scalabilità, performance, compatibilità plugin
- Verifica vincoli architetturali

#### Quando Proponi Soluzioni:
- Menziona sempre i TRADE-OFF: manutenzione vs performance, complessità vs flessibilità
- Suggerisci best practices (nonce, sanitization, prepared statements)
- Indica potenziali problemi di compatibilità con altri plugin

#### Struttura Codice:
- Codice production-ready con gestione errori
- Commenti solo dove necessario (logica complessa)
- Namespace e OOP quando appropriato
- Hooks con priority corrette

#### Esempi di Comunicazione:
- "Propongo questa architettura: [dettagli tecnici]"
- "Trade-off: [performance vs manutenzione]"
- "Attenzione: questo potrebbe conflittare con [plugin] perché [motivo tecnico]"
- "Per ottimizzare le performance, considera [tecnica specifica]"

#### Esempio di Interazione Tipo:
Utente: "Crea un endpoint REST per esportare i product in CSV"
Tu: "Perfetto. Prima di implementare, alcuni dettagli:

1. **Campi da esportare**: Tutti i meta o specifici? (SKU, price, stock, custom fields ACF?)
2. **Filtri**: Solo `publish`? Range date? Categorie specifiche?
3. **Auth**: Endpoint pubblico, richiede `edit_products` cap, o API key custom?
4. **Volume**: Quanti prodotti? Se >1000, considero paginazione o job asincrono con Action Scheduler.

Per l'implementazione propongo:
- Custom endpoint via `register_rest_route` con namespace custom
- Streaming output per CSV (memory efficient)
- Rate limiting se pubblico
- Transient cache per query pesanti

Confermi i requisiti e procedo?"
RULES;
	}

	/**
	 * Get level label for display
	 *
	 * @param string $level Level key.
	 * @return string
	 */
	public static function get_level_label( string $level ): string {
		$levels = self::get_levels_info();
		return $levels[ $level ]['label'] ?? ucfirst( $level );
	}

	// ==================== PERFORMANCE TIER METHODS ====================

	/**
	 * Get current default performance tier
	 *
	 * @return string
	 */
	public static function get_default_tier(): string {
		$tier = get_option( self::TIER_OPTION_NAME, self::TIER_FLOW );
		return in_array( $tier, self::get_valid_tiers(), true ) ? $tier : self::TIER_FLOW;
	}

	/**
	 * Set default performance tier
	 *
	 * @param string $tier Tier to set.
	 * @return bool
	 */
	public static function set_default_tier( string $tier ): bool {
		if ( ! in_array( $tier, self::get_valid_tiers(), true ) ) {
			return false;
		}

		$current = self::get_default_tier();
		if ( $current === $tier ) {
			return true;
		}

		return update_option( self::TIER_OPTION_NAME, $tier );
	}

	/**
	 * Check if default tier is set
	 *
	 * @return bool
	 */
	public static function is_tier_set(): bool {
		return get_option( self::TIER_OPTION_NAME ) !== false;
	}

	/**
	 * Get all valid tiers
	 *
	 * @return array
	 */
	public static function get_valid_tiers(): array {
		return [
			self::TIER_FLOW,
			self::TIER_CRAFT,
		];
	}

	/**
	 * Get tier display info for UI
	 *
	 * @return array
	 */
	public static function get_tiers_info(): array {
		return [
			self::TIER_FLOW => [
				'label'       => __( 'Flow', 'creator-core' ),
				'icon'        => '✍🏼',
				'credits'     => 0.5,
				'time'        => '20-30 sec',
				'quality'     => '85%',
				'title'       => __( 'Fast & Efficient', 'creator-core' ),
				'description' => __( 'Optimized for speed and iterative work. Best for content editing, CSS changes, quick snippets, and standard modifications.', 'creator-core' ),
				'best_for'    => [
					__( 'Iterative content editing', 'creator-core' ),
					__( 'CSS and style modifications', 'creator-core' ),
					__( 'Quick code snippets', 'creator-core' ),
					__( 'Configuration changes', 'creator-core' ),
					__( 'Standard template modifications', 'creator-core' ),
				],
				'chain'       => 'Gemini 2.5 Flash → Claude 4 Sonnet',
			],
			self::TIER_CRAFT => [
				'label'       => __( 'Craft', 'creator-core' ),
				'icon'        => '⚙️',
				'credits'     => 2.0,
				'time'        => '45-60 sec',
				'quality'     => '95%',
				'title'       => __( 'Maximum Quality', 'creator-core' ),
				'description' => __( 'Full-power AI chain for complex tasks. Best for critical code, architecture planning, custom templates, and multi-plugin integrations.', 'creator-core' ),
				'best_for'    => [
					__( 'Complex CPT + ACF + Elementor operations', 'creator-core' ),
					__( 'Site architecture planning', 'creator-core' ),
					__( 'Custom Elementor templates', 'creator-core' ),
					__( 'Multi-plugin integrations', 'creator-core' ),
					__( 'Critical code generation', 'creator-core' ),
				],
				'chain'       => 'Gemini 2.5 Flash → Gemini 2.5 Pro → Claude 4.5 Opus',
			],
		];
	}

	/**
	 * Get tier label for display
	 *
	 * @param string $tier Tier key.
	 * @return string
	 */
	public static function get_tier_label( string $tier ): string {
		$tiers = self::get_tiers_info();
		return $tiers[ $tier ]['label'] ?? ucfirst( $tier );
	}

	/**
	 * Get tier credit cost
	 *
	 * @param string $tier Tier key.
	 * @return float
	 */
	public static function get_tier_credits( string $tier ): float {
		$tiers = self::get_tiers_info();
		return $tiers[ $tier ]['credits'] ?? 0.5;
	}
}
