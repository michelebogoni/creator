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
 * Manages user competency levels and provides AI instructions based on profile
 */
class UserProfile {

	/**
	 * Competency level constants
	 */
	public const LEVEL_BASE         = 'base';
	public const LEVEL_INTERMEDIATE = 'intermediate';
	public const LEVEL_ADVANCED     = 'advanced';

	/**
	 * Option name for storing user level
	 */
	private const OPTION_NAME = 'creator_user_profile';

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
				'label'       => __( 'Principiante', 'creator-core' ),
				'title'       => __( 'Non programmo', 'creator-core' ),
				'description' => __( 'Uso WordPress tramite la dashboard e plugin visuali come Elementor. Non modifico mai file di tema o codice direttamente.', 'creator-core' ),
				'capabilities' => [
					'can'    => [
						__( 'Creare pagine/post via Dashboard', 'creator-core' ),
						__( 'Usare Elementor o builder visuali', 'creator-core' ),
						__( 'Configurare plugin tramite interfaccia', 'creator-core' ),
					],
					'cannot' => [
						__( 'Modificare functions.php, CSS, PHP', 'creator-core' ),
						__( 'Creare child theme o plugin custom', 'creator-core' ),
					],
				],
				'behavior'    => __( 'Creator userà solo plugin e interfacce visuali, evitando codice. Ti guiderà passo passo con linguaggio semplice.', 'creator-core' ),
			],
			self::LEVEL_INTERMEDIATE => [
				'label'       => __( 'Intermedio', 'creator-core' ),
				'title'       => __( 'Conosco le basi del codice', 'creator-core' ),
				'description' => __( 'Ho familiarità con HTML/CSS/PHP. Uso WP Code per snippet e so lavorare con child theme. Non ho paura del codice, ma preferisco evitarlo quando possibile.', 'creator-core' ),
				'capabilities' => [
					'can'    => [
						__( 'Modificare CSS/HTML via Elementor o WP Code', 'creator-core' ),
						__( 'Creare snippet semplici in WP Code', 'creator-core' ),
						__( 'Lavorare su child theme', 'creator-core' ),
						__( 'Comprendere hook, shortcode, CPT', 'creator-core' ),
					],
					'cannot' => [
						__( 'Modificare il tema principale', 'creator-core' ),
						__( 'Query SQL complesse sul database', 'creator-core' ),
					],
				],
				'behavior'    => __( 'Creator proporrà soluzioni miste (plugin + codice via WP Code). Userà linguaggio tecnico ma spiegando i concetti.', 'creator-core' ),
			],
			self::LEVEL_ADVANCED => [
				'label'       => __( 'Avanzato', 'creator-core' ),
				'title'       => __( 'Sono uno sviluppatore', 'creator-core' ),
				'description' => __( 'Conosco PHP, JavaScript, SQL e la struttura del database WordPress. Lavoro con functions.php, creo plugin e temi custom senza problemi.', 'creator-core' ),
				'capabilities' => [
					'can'    => [
						__( 'Scrivere codice in functions.php', 'creator-core' ),
						__( 'Creare plugin e temi custom', 'creator-core' ),
						__( 'Query SQL ottimizzate, hook avanzati', 'creator-core' ),
						__( 'REST API custom e modifiche al database', 'creator-core' ),
					],
					'cannot' => [],
				],
				'behavior'    => __( 'Creator proporrà la soluzione tecnicamente migliore. Linguaggio da sviluppatore, codice diretto, trade-off espliciti.', 'creator-core' ),
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
}
