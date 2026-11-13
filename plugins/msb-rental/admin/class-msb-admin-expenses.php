<?php
/**
 * Backend-Seite Vermietung → Auslagen (1.10.0, ADM-16): eigene Auslagen erfassen, Abrechnungen ansehen,
 * freigeben (1.10.1, Vier-Augen-Prinzip), an Finom senden, als ausgezahlt markieren oder stornieren,
 * eigene Bankverbindung pflegen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSB_Admin_Expenses {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 11 );
		foreach ( array( 'save', 'delete', 'claim', 'bank', 'paid', 'send', 'cancel', 'file', 'finom', 'approve' ) as $a ) {
			add_action( 'admin_post_msb_exp_' . $a, array( __CLASS__, 'handle_' . $a ) );
		}
	}

	public static function menu() {
		add_submenu_page( 'msb-bookings', 'Auslagen der Gesellschafter', 'Auslagen', MSB_Expenses::cap(), MSB_Expenses::PAGE, array( __CLASS__, 'page' ) );
	}

	private static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => MSB_Expenses::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	private static function guard( $nonce ) {
		if ( ! current_user_can( MSB_Expenses::cap() ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( $nonce );
	}

	/**
	 * Zurück zur Seite. Die Meldung liegt kurz im Transient des Nutzers, nicht in der Adresse.
	 */
	private static function back( $tab, $msg, $extra = array() ) {
		set_transient( 'msb_exp_msg_' . get_current_user_id(), array( (string) $msg, ! empty( $extra['err'] ) ), 120 );
		unset( $extra['err'] );
		wp_safe_redirect( self::url( array_merge( array( 'tab' => $tab ), array_filter( $extra ) ) ) );
		exit;
	}

	private static function file_url( $kind, $id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=msb_exp_file&kind=' . $kind . '&id=' . (int) $id ), 'msb_exp_file_' . $kind . '_' . (int) $id );
	}

	/* ------------------------------------------------------------------
	 * Seite
	 * ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( MSB_Expenses::cap() ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'mine';
		// phpcs:enable
		$note = get_transient( 'msb_exp_msg_' . get_current_user_id() );
		delete_transient( 'msb_exp_msg_' . get_current_user_id() );
		$msg = is_array( $note ) ? (string) $note[0] : '';
		$err = is_array( $note ) && ! empty( $note[1] );
		$waiting = 0;
		foreach ( MSB_Expenses::claims() as $c ) {
			if ( MSB_Expenses::can_approve( $c, get_current_user_id() ) ) {
				$waiting++;
			}
		}
		$tabs = array(
			'mine'   => 'Meine Auslagen',
			'claims' => 'Abrechnungen' . ( $waiting ? ' (' . $waiting . ' zur Freigabe)' : '' ),
			'bank'   => 'Meine Bankverbindung',
		);
		$tab = isset( $tabs[ $tab ] ) ? $tab : 'mine';
		?>
		<div class="wrap msb-admin msb-expenses">
			<h1>Auslagen der Gesellschafter</h1>
			<p>Privat bezahlte Ausgaben für die GbR erfassen, als Auslagenabrechnung (<code>AUS-JJJJ-0001</code>) abrechnen und vom Finom-Geschäftskonto erstatten. Die Überweisung bestätigt ihr selbst in Finom. <a href="<?php echo esc_url( admin_url( 'admin.php?page=msb-docs#auslagen' ) ); ?>">Hilfe</a></p>
			<?php if ( '' !== $msg ) : ?>
				<div class="notice notice-<?php echo $err ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
			<?php endif; ?>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $k => $l ) : ?>
					<a class="nav-tab <?php echo $k === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::url( array( 'tab' => $k ) ) ); ?>"><?php echo esc_html( $l ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'claims' === $tab ) {
				self::tab_claims();
			} elseif ( 'bank' === $tab ) {
				self::tab_bank();
			} else {
				self::tab_mine();
			}
			?>
		</div>
		<script>
			document.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '[data-msb-copy]' );
				if ( ! b ) { return; }
				e.preventDefault();
				var t = b.getAttribute( 'data-msb-copy' );
				var done = function () { var o = b.textContent; b.textContent = 'Kopiert'; setTimeout( function () { b.textContent = o; }, 1500 ); };
				if ( navigator.clipboard ) { navigator.clipboard.writeText( t ).then( done ); } else { window.prompt( 'Zum Kopieren:', t ); }
			} );
			document.addEventListener( 'input', function ( e ) {
				if ( e.target && 'msb-amount' === e.target.id ) {
					var v = parseFloat( String( e.target.value ).replace( /\./g, '' ).replace( ',', '.' ) );
					document.getElementById( 'msb-250' ).style.display = v > <?php echo (int) MSB_Expenses::SMALL; ?> ? '' : 'none';
				}
			} );
			document.addEventListener( 'change', function ( e ) {
				if ( e.target && 'msb-no-receipt' === e.target.id ) {
					document.getElementById( 'msb-receipt-row' ).style.display = e.target.checked ? 'none' : '';
					document.getElementById( 'msb-reason-row' ).style.display = e.target.checked ? '' : 'none';
				}
			} );
		</script>
		<?php
	}

	private static function tab_mine() {
		$uid   = get_current_user_id();
		$open  = MSB_Expenses::open_positions( $uid );
		// phpcs:ignore WordPress.Security.NonceVerification
		$edit  = isset( $_GET['edit'] ) ? MSB_Expenses::position( absint( $_GET['edit'] ) ) : null;
		$edit  = $edit && (int) $edit['user_id'] === $uid && ! (int) $edit['claim_id'] ? $edit : null;
		$noreceipt = $edit && '' !== (string) $edit['no_receipt_reason'];
		$until = gmdate( 'Y-m-t', strtotime( current_time( 'Y-m-01' ) . ' -1 day' ) );
		$bank  = MSB_Expenses::iban_valid( MSB_Expenses::partner_iban( $uid ) );
		?>
		<h2><?php echo $edit ? 'Auslage bearbeiten' : 'Neue Auslage'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="msb-card-box">
			<input type="hidden" name="action" value="msb_exp_save">
			<input type="hidden" name="id" value="<?php echo $edit ? (int) $edit['id'] : 0; ?>">
			<?php wp_nonce_field( 'msb_exp_save' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="msb-spent-on">Datum</label></th>
					<td><input type="date" id="msb-spent-on" name="spent_on" required max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $edit ? $edit['spent_on'] : current_time( 'Y-m-d' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="msb-purpose">Lieferant / Zweck</label></th>
					<td><input type="text" id="msb-purpose" name="purpose" class="regular-text" maxlength="190" required placeholder="z. B. Baumarkt (Schrauben für den Fahrradträger)" value="<?php echo esc_attr( $edit ? $edit['purpose'] : '' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="msb-amount">Betrag (brutto)</label></th>
					<td><input type="text" id="msb-amount" name="amount" inputmode="decimal" class="small-text" required placeholder="45,90" value="<?php echo esc_attr( $edit ? number_format( (float) $edit['amount'], 2, ',', '' ) : '' ); ?>"> €
						<p class="description">Betrag laut Kassenbon, mit Mehrwertsteuer. Als Kleinunternehmer zählt nur der Bruttobetrag.</p>
						<p class="description" id="msb-250" style="<?php echo $edit && (float) $edit['amount'] > MSB_Expenses::SMALL ? '' : 'display:none'; ?>"><strong>Über <?php echo (int) MSB_Expenses::SMALL; ?> €:</strong> Auf der Rechnung sollten Name und Anschrift der GbR stehen (<?php echo esc_html( self::issuer_line() ); ?>). Kassenbons bis <?php echo (int) MSB_Expenses::SMALL; ?> € reichen ohne.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="msb-vat">Darin MwSt. (optional)</label></th>
					<td><input type="text" id="msb-vat" name="vat_info" class="small-text" maxlength="40" placeholder="19 %" value="<?php echo esc_attr( $edit ? $edit['vat_info'] : '' ); ?>">
						<p class="description">Nur zur Information, z. B. „19 %“ oder „7,33 €“.</p></td>
				</tr>
				<tr>
					<th scope="row">Beleg</th>
					<td>
						<label><input type="checkbox" id="msb-no-receipt" name="no_receipt" value="1" <?php checked( $noreceipt ); ?>> Kein Beleg vorhanden (Eigenbeleg)</label>
						<div id="msb-receipt-row" style="margin-top:8px;<?php echo $noreceipt ? 'display:none' : ''; ?>">
							<input type="file" name="receipt" accept="image/*,application/pdf">
							<?php if ( $edit && '' !== $edit['receipt_file'] ) : ?>
								<p class="description">Vorhanden: <a href="<?php echo esc_url( self::file_url( 'receipt', $edit['id'] ) ); ?>" target="_blank">Beleg ansehen</a>. Neue Datei ersetzt ihn.</p>
							<?php endif; ?>
							<p class="description">Foto vom Kassenbon (auf dem Handy direkt mit der Kamera) oder PDF, höchstens 10 MB.</p>
						</div>
						<div id="msb-reason-row" style="margin-top:8px;<?php echo $noreceipt ? '' : 'display:none'; ?>">
							<label for="msb-reason">Begründung</label><br>
							<textarea id="msb-reason" name="no_receipt_reason" rows="2" class="large-text" placeholder="z. B. Parkautomat gibt keine Quittung aus"><?php echo esc_textarea( $edit ? $edit['no_receipt_reason'] : '' ); ?></textarea>
						</div>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary"><?php echo $edit ? 'Änderung speichern' : 'Auslage speichern'; ?></button>
				<?php if ( $edit ) : ?>
					<a class="button" href="<?php echo esc_url( self::url() ); ?>">Abbrechen</a>
				<?php endif; ?>
			</p>
		</form>

		<h2>Meine offenen Auslagen</h2>
		<table class="widefat striped msb-cards">
			<thead><tr><th>Datum</th><th>Lieferant / Zweck</th><th>Beleg</th><th style="text-align:right">Betrag</th><th>Aktionen</th></tr></thead>
			<tbody>
			<?php if ( ! $open ) : ?>
				<tr><td colspan="5">Keine offenen Auslagen.</td></tr>
			<?php endif; ?>
			<?php foreach ( $open as $p ) : ?>
				<tr>
					<td class="msb-c-head"><?php echo esc_html( mysql2date( 'd.m.Y', $p['spent_on'] ) ); ?></td>
					<td data-label="Zweck"><?php echo esc_html( $p['purpose'] ); ?></td>
					<td data-label="Beleg">
						<?php if ( '' !== $p['receipt_file'] ) : ?>
							<a href="<?php echo esc_url( self::file_url( 'receipt', $p['id'] ) ); ?>" target="_blank"><?php echo 'pdf' === $p['receipt_type'] ? 'PDF' : 'Foto'; ?></a>
						<?php else : ?>
							Eigenbeleg: <?php echo esc_html( $p['no_receipt_reason'] ); ?>
						<?php endif; ?>
					</td>
					<td class="msb-c-aside" style="text-align:right"><?php echo esc_html( msb_price( $p['amount'] ) ); ?></td>
					<td class="msb-c-actions">
						<a class="button" href="<?php echo esc_url( self::url( array( 'edit' => (int) $p['id'] ) ) ); ?>">Bearbeiten</a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:24px" onsubmit="return confirm('Auslage wirklich löschen?');">
							<input type="hidden" name="action" value="msb_exp_delete">
							<input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
							<?php wp_nonce_field( 'msb_exp_delete_' . (int) $p['id'] ); ?>
							<button type="submit" class="button button-link-delete">Löschen</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
			<?php if ( $open ) : ?>
				<tfoot><tr><th colspan="3">Summe offen</th><th style="text-align:right"><?php echo esc_html( msb_price( MSB_Expenses::total( $open ) ) ); ?></th><td></td></tr></tfoot>
			<?php endif; ?>
		</table>

		<?php if ( $open ) : ?>
			<h2>Abrechnung erstellen</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msb-card-box" onsubmit="return confirm('Abrechnung jetzt erstellen? Die Auslagen bis zum Stichtag werden danach gesperrt.');">
				<input type="hidden" name="action" value="msb_exp_claim">
				<?php wp_nonce_field( 'msb_exp_claim' ); ?>
				<p><label>Alle offenen Auslagen bis einschließlich <input type="date" name="until" value="<?php echo esc_attr( $until < $open[0]['spent_on'] ? current_time( 'Y-m-d' ) : $until ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></label></p>
				<?php if ( ! $bank ) : ?>
					<p><strong>Zuerst die <a href="<?php echo esc_url( self::url( array( 'tab' => 'bank' ) ) ); ?>">Bankverbindung</a> eintragen.</strong></p>
				<?php endif; ?>
				<p><button type="submit" class="button button-primary" <?php disabled( ! $bank ); ?>>Abrechnung erstellen</button></p>
				<p class="description">Es entsteht eine PDF nach der Vorlage mit allen Fotobelegen als Folgeseiten. Danach muss der andere Gesellschafter sie freigeben (Vier-Augen-Prinzip), er bekommt dazu eine Mail. <?php echo is_email( msb_setting( 'expense_finom_email' ) ) ? 'Nach der Freigabe geht sie automatisch an das Finom-Beleg-Postfach.' : 'Ein Finom-Beleg-Postfach ist noch nicht eingetragen (Abrechnungen, ganz unten), die PDF wird deshalb nicht automatisch verschickt.'; ?></p>
			</form>
		<?php endif; ?>
		<?php
	}

	private static function tab_claims() {
		$claims = MSB_Expenses::claims();
		$uid    = get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification
		$show = isset( $_GET['claim'] ) ? absint( $_GET['claim'] ) : 0;
		?>
		<table class="widefat striped msb-cards" style="margin-top:16px">
			<thead><tr><th>Nummer</th><th>Gesellschafter</th><th>Zeitraum</th><th>Status</th><th style="text-align:right">Betrag</th><th>Aktionen</th></tr></thead>
			<tbody>
			<?php if ( ! $claims ) : ?>
				<tr><td colspan="6">Noch keine Abrechnungen.</td></tr>
			<?php endif; ?>
			<?php foreach ( $claims as $c ) : ?>
				<tr>
					<td class="msb-c-head"><a href="<?php echo esc_url( self::file_url( 'claim', $c['id'] ) ); ?>" target="_blank"><strong><?php echo esc_html( $c['number'] ); ?></strong></a> <small><?php echo esc_html( mysql2date( 'd.m.Y', $c['created_at'] ) ); ?></small></td>
					<td data-label="Gesellschafter"><?php echo esc_html( $c['data']['name'] ); ?></td>
					<td data-label="Zeitraum"><?php echo esc_html( $c['data']['period'] ); ?></td>
					<td data-label="Status">
						<?php echo esc_html( MSB_Expenses::status_text( $c ) ); ?>
						<?php if ( MSB_Expenses::is_approved( $c ) && 'cancelled' !== $c['status'] ) : ?>
							<br><small>freigegeben von <?php echo esc_html( MSB_Expenses::partner_name( $c['approved_by'] ) ); ?> am <?php echo esc_html( mysql2date( 'd.m.Y', $c['approved_at'] ) ); ?></small>
						<?php endif; ?>
						<?php if ( 'paid' === $c['status'] ) : ?>
							am <?php echo esc_html( mysql2date( 'd.m.Y', $c['paid_at'] ) ); ?>
						<?php endif; ?>
						<?php if ( $c['sent_at'] ) : ?>
							<br><small>an Finom gesendet <?php echo esc_html( mysql2date( 'd.m.Y H:i', $c['sent_at'] ) ); ?></small>
						<?php endif; ?>
						<?php if ( 'cancelled' === $c['status'] ) : ?>
							<br><small><?php echo esc_html( $c['cancel_reason'] ); ?></small>
						<?php endif; ?>
					</td>
					<td class="msb-c-aside" style="text-align:right"><?php echo esc_html( msb_price( $c['amount'] ) ); ?></td>
					<td class="msb-c-actions">
						<a class="button" href="<?php echo esc_url( self::file_url( 'claim', $c['id'] ) ); ?>" target="_blank">PDF</a>
						<?php if ( MSB_Expenses::can_approve( $c, $uid ) ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( self::url( array( 'tab' => 'claims', 'claim' => (int) $c['id'] ) ) ); ?>#msb-pay">Prüfen und freigeben</a>
						<?php elseif ( 'issued' === $c['status'] && MSB_Expenses::is_approved( $c ) ) : ?>
							<a class="button" href="<?php echo esc_url( self::url( array( 'tab' => 'claims', 'claim' => (int) $c['id'] ) ) ); ?>#msb-pay">Auszahlen</a>
						<?php elseif ( 'issued' === $c['status'] ) : ?>
							<a class="button" href="<?php echo esc_url( self::url( array( 'tab' => 'claims', 'claim' => (int) $c['id'] ) ) ); ?>#msb-pay">Ansehen</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$c = $show ? MSB_Expenses::claim( $show ) : null;
		if ( $c && 'issued' === $c['status'] ) {
			if ( MSB_Expenses::is_approved( $c ) ) {
				self::pay_box( $c );
			} else {
				self::approve_box( $c );
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		$year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) current_time( 'Y' );
		echo MSB_Expenses::box( $year ); // phpcs:ignore
		?>
		<form method="get" style="margin:-8px 0 16px">
			<input type="hidden" name="page" value="<?php echo esc_attr( MSB_Expenses::PAGE ); ?>">
			<input type="hidden" name="tab" value="claims">
			<label>Jahr <input type="number" name="year" min="2026" max="<?php echo (int) current_time( 'Y' ) + 1; ?>" value="<?php echo (int) $year; ?>" class="small-text"></label>
			<button class="button">Anzeigen</button>
		</form>
		<?php
		self::finom_box();
	}

	/**
	 * Finom-Beleg-Postfach (PAY-40). Ändern dürfen nur Nutzer mit Zugriff auf die Einstellungen (ROL-04).
	 */
	private static function finom_box() {
		$to  = (string) msb_setting( 'expense_finom_email' );
		$can = current_user_can( msb_capability( 'settings' ) );
		?>
		<div class="msb-card-box">
			<h2>Finom-Beleg-Postfach</h2>
			<?php if ( $can ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="msb_exp_finom">
					<?php wp_nonce_field( 'msb_exp_finom' ); ?>
					<p><input type="email" name="expense_finom_email" class="regular-text" value="<?php echo esc_attr( $to ); ?>" placeholder="Adresse aus Finom → Invoices"> <button type="submit" class="button">Speichern</button></p>
				</form>
			<?php else : ?>
				<p><?php echo '' !== $to ? esc_html( $to ) : 'Noch nicht eingetragen.'; ?></p>
			<?php endif; ?>
			<p class="description">Neue Abrechnungen gehen automatisch als PDF an diese Adresse, PDF-Belege als zusätzliche Anhänge, Kopie an die Admin-Adresse. Leer lassen, um nichts zu senden.</p>
		</div>
		<?php
	}

	/**
	 * Zeile mit Name und Anschrift der GbR aus den Beleg-Einstellungen (Hinweis über 250 €, PAY-44).
	 */
	private static function issuer_line() {
		$is = MSB_Documents::issuer();
		return trim( $is['name'] . ', ' . implode( ', ', (array) $is['address'] ), ', ' );
	}

	private static function cancel_form( array $c ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Abrechnung wirklich stornieren? Die Nummer bleibt belegt, die Auslagen sind danach wieder offen.');" style="margin-left:24px">
			<input type="hidden" name="action" value="msb_exp_cancel">
			<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
			<?php wp_nonce_field( 'msb_exp_cancel_' . (int) $c['id'] ); ?>
			<label>Grund <input type="text" name="reason" class="regular-text" required placeholder="z. B. falscher Betrag bei Pos. 2"></label>
			<button type="submit" class="button button-link-delete">Stornieren</button>
		</form>
		<?php
	}

	/**
	 * Freigabe durch den zweiten Gesellschafter (PAY-43).
	 */
	private static function approve_box( array $c ) {
		$can = MSB_Expenses::can_approve( $c, get_current_user_id() );
		?>
		<div class="msb-card-box" id="msb-pay">
			<h2><?php echo esc_html( $c['number'] ); ?> freigeben</h2>
			<p><?php echo esc_html( $c['data']['name'] . ' · ' . $c['data']['period'] . ' · ' . msb_price( $c['amount'] ) . ' · ' . count( (array) $c['data']['positions'] ) . ' Position(en)' ); ?> · <a href="<?php echo esc_url( self::file_url( 'claim', $c['id'] ) ); ?>" target="_blank">PDF mit allen Belegen ansehen</a></p>
			<?php if ( $can ) : ?>
				<p>Bitte Positionen und Belege prüfen. Mit der Freigabe bestätigst du die betriebliche Veranlassung (Vier-Augen-Prinzip). Danach kann ausgezahlt werden<?php echo is_email( msb_setting( 'expense_finom_email' ) ) ? ', und die Abrechnung geht an das Finom-Beleg-Postfach' : ''; ?>.</p>
			<?php else : ?>
				<p><strong>Wartet auf Freigabe durch den anderen Gesellschafter.</strong> Wer die Abrechnung bekommt oder erstellt hat, kann sie nicht selbst freigeben.</p>
			<?php endif; ?>
			<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;justify-content:space-between">
				<div>
					<?php if ( $can ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Abrechnung jetzt freigeben?');">
							<input type="hidden" name="action" value="msb_exp_approve">
							<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
							<?php wp_nonce_field( 'msb_exp_approve_' . (int) $c['id'] ); ?>
							<button type="submit" class="button button-primary">Freigeben</button>
						</form>
					<?php endif; ?>
				</div>
				<?php self::cancel_form( $c ); ?>
			</div>
		</div>
		<?php
	}

	private static function pay_box( array $c ) {
		$d       = $c['data'];
		$amount  = number_format( (float) $c['amount'], 2, ',', '' );
		$payload = MSB_Expenses::epc_payload( $d['name'], $d['iban'], $c['amount'], $d['purpose'] );
		$svg     = class_exists( 'MSB_QR' ) ? MSB_QR::svg( $payload ) : '';
		$finom   = is_email( msb_setting( 'expense_finom_email' ) );
		$fields  = array(
			'Empfänger'        => $d['name'],
			'IBAN'             => MSB_Expenses::iban_format( $d['iban'] ),
			'Betrag'           => $amount,
			'Verwendungszweck' => $d['purpose'],
		);
		?>
		<div class="msb-card-box" id="msb-pay">
			<h2><?php echo esc_html( $c['number'] ); ?> auszahlen</h2>
			<p>Freigegeben von <?php echo esc_html( MSB_Expenses::partner_name( $c['approved_by'] ) ); ?> am <?php echo esc_html( mysql2date( 'd.m.Y H:i', $c['approved_at'] ) ); ?>.</p>
			<ol>
				<li>In Finom eine Überweisung anlegen, mit diesen Angaben oder dem GiroCode:</li>
			</ol>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $l => $v ) : ?>
					<tr><th scope="row"><?php echo esc_html( $l ); ?></th><td><code><?php echo esc_html( $v ); ?><?php echo 'Betrag' === $l ? ' €' : ''; ?></code> <button type="button" class="button button-small" data-msb-copy="<?php echo esc_attr( 'IBAN' === $l ? MSB_Expenses::iban_normalize( $v ) : $v ); ?>">Kopieren</button></td></tr>
				<?php endforeach; ?>
				<?php if ( $svg ) : ?>
					<tr><th scope="row">GiroCode</th><td><img src="<?php echo esc_attr( 'data:image/svg+xml;base64,' . base64_encode( $svg ) ); // phpcs:ignore ?>" width="160" height="160" alt="GiroCode für die Überweisung"><p class="description">Mit einer Banking-App scannen, die GiroCodes kennt. Ob die Finom-App das kann, bitte einmal ausprobieren.</p></td></tr>
				<?php endif; ?>
			</table>
			<ol start="2">
				<li><?php echo $c['sent_at'] ? 'Die Abrechnung liegt seit ' . esc_html( mysql2date( 'd.m.Y H:i', $c['sent_at'] ) ) . ' im Finom-Beleg-Postfach.' : 'Die Abrechnung ist noch nicht an Finom gesendet.'; ?> In Finom unter Dokumente öffnen und mit der Überweisung verknüpfen („Link payment“).</li>
				<li>Hier als ausgezahlt markieren.</li>
			</ol>
			<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;justify-content:space-between">
				<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end">
					<?php if ( $finom ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="msb_exp_send">
							<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
							<?php wp_nonce_field( 'msb_exp_send_' . (int) $c['id'] ); ?>
							<button type="submit" class="button"><?php echo $c['sent_at'] ? 'Erneut an Finom senden' : 'An Finom senden'; ?></button>
						</form>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="msb_exp_paid">
						<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
						<?php wp_nonce_field( 'msb_exp_paid_' . (int) $c['id'] ); ?>
						<label>Überwiesen am <input type="date" name="paid_on" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></label>
						<button type="submit" class="button button-primary">Als ausgezahlt markieren</button>
					</form>
				</div>
				<?php self::cancel_form( $c ); ?>
			</div>
		</div>
		<?php
	}

	private static function tab_bank() {
		$uid  = get_current_user_id();
		$iban = MSB_Expenses::partner_iban( $uid );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="msb-card-box">
			<input type="hidden" name="action" value="msb_exp_bank">
			<?php wp_nonce_field( 'msb_exp_bank' ); ?>
			<p>Auf dieses Konto wird deine Erstattung überwiesen. Jeder Gesellschafter pflegt nur seine eigene Bankverbindung.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="msb-pname">Name (Kontoinhaber)</label></th>
					<td><input type="text" id="msb-pname" name="partner_name" class="regular-text" required value="<?php echo esc_attr( MSB_Expenses::partner_name( $uid ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="msb-iban">IBAN</label></th>
					<td><input type="text" id="msb-iban" name="iban" class="regular-text code" autocomplete="off" placeholder="DE00 0000 0000 0000 0000 00" value="">
						<p class="description"><?php echo '' !== $iban ? 'Gespeichert: ' . esc_html( MSB_Expenses::iban_mask( $iban ) ) . '. Leer lassen, um sie zu behalten.' : 'Noch keine IBAN gespeichert.'; ?></p></td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary">Speichern</button></p>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Aktionen
	 * ------------------------------------------------------------------ */

	public static function handle_save() {
		self::guard( 'msb_exp_save' );
		$in   = wp_unslash( $_POST ); // phpcs:ignore
		$id   = isset( $in['id'] ) ? absint( $in['id'] ) : 0;
		$file = null;
		if ( empty( $in['no_receipt'] ) && ! empty( $_FILES['receipt']['name'] ) ) {
			$file = MSB_Expenses::store_upload( $_FILES['receipt'] ); // phpcs:ignore
			if ( is_wp_error( $file ) ) {
				self::back( 'mine', $file->get_error_message(), array( 'err' => 1, 'edit' => $id ? $id : null ) );
			}
		}
		$res = MSB_Expenses::save_position( get_current_user_id(), $in, $file, $id );
		if ( is_wp_error( $res ) ) {
			if ( $file ) {
				@unlink( MSB_Expenses::path( $file['file'] ) ); // phpcs:ignore
			}
			self::back( 'mine', $res->get_error_message(), array( 'err' => 1, 'edit' => $id ? $id : null ) );
		}
		$p    = MSB_Expenses::position( $res );
		$hint = $p && (float) $p['amount'] > MSB_Expenses::SMALL && '' !== (string) $p['receipt_file'] ? ' Hinweis: Über ' . MSB_Expenses::SMALL . ' € sollten Name und Anschrift der GbR auf der Rechnung stehen.' : '';
		self::back( 'mine', ( $id ? 'Auslage geändert.' : 'Auslage gespeichert.' ) . $hint );
	}

	public static function handle_delete() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore
		self::guard( 'msb_exp_delete_' . $id );
		$ok = MSB_Expenses::delete_position( get_current_user_id(), $id );
		self::back( 'mine', $ok ? 'Auslage gelöscht.' : 'Die Auslage konnte nicht gelöscht werden.', $ok ? array() : array( 'err' => 1 ) );
	}

	public static function handle_claim() {
		self::guard( 'msb_exp_claim' );
		$until = isset( $_POST['until'] ) ? sanitize_text_field( wp_unslash( $_POST['until'] ) ) : '';
		$id    = MSB_Expenses::issue_claim( get_current_user_id(), $until );
		if ( is_wp_error( $id ) ) {
			self::back( 'mine', $id->get_error_message(), array( 'err' => 1 ) );
		}
		$c = MSB_Expenses::claim( $id );
		self::back( 'claims', 'Abrechnung ' . ( $c ? $c['number'] : '' ) . ' erstellt. Sie wartet jetzt auf die Freigabe durch den anderen Gesellschafter.', array( 'claim' => $id ) );
	}

	public static function handle_bank() {
		self::guard( 'msb_exp_bank' );
		$uid  = get_current_user_id();
		$name = isset( $_POST['partner_name'] ) ? sanitize_text_field( wp_unslash( $_POST['partner_name'] ) ) : '';
		$iban = isset( $_POST['iban'] ) ? sanitize_text_field( wp_unslash( $_POST['iban'] ) ) : '';
		if ( '' === trim( $iban ) ) {
			$iban = MSB_Expenses::partner_iban( $uid );
		}
		$res = MSB_Expenses::save_bank( $uid, $name, $iban );
		if ( is_wp_error( $res ) ) {
			self::back( 'bank', $res->get_error_message(), array( 'err' => 1 ) );
		}
		self::back( 'bank', 'Bankverbindung gespeichert.' );
	}

	public static function handle_paid() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore
		self::guard( 'msb_exp_paid_' . $id );
		$date = isset( $_POST['paid_on'] ) ? sanitize_text_field( wp_unslash( $_POST['paid_on'] ) ) : '';
		$ok   = MSB_Expenses::mark_paid( $id, $date );
		self::back( 'claims', $ok ? 'Als ausgezahlt markiert.' : 'Konnte nicht als ausgezahlt markiert werden.', $ok ? array() : array( 'err' => 1 ) );
	}

	public static function handle_send() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore
		self::guard( 'msb_exp_send_' . $id );
		$ok = MSB_Expenses::send( $id );
		self::back( 'claims', $ok ? 'An das Finom-Beleg-Postfach gesendet.' : 'Versand fehlgeschlagen. Ist unten unter „Finom-Beleg-Postfach“ eine Adresse eingetragen?', array( 'claim' => $id ) + ( $ok ? array() : array( 'err' => 1 ) ) );
	}

	public static function handle_cancel() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore
		self::guard( 'msb_exp_cancel_' . $id );
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$ok     = MSB_Expenses::cancel( $id, $reason );
		self::back( 'claims', $ok ? 'Abrechnung storniert. Die Auslagen sind wieder offen.' : 'Stornieren ging nicht (Grund fehlt, schon storniert oder schon ausgezahlt).', $ok ? array() : array( 'err' => 1 ) );
	}

	public static function handle_approve() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore
		self::guard( 'msb_exp_approve_' . $id );
		$ok = MSB_Expenses::approve( $id );
		$c  = $ok ? MSB_Expenses::claim( $id ) : null;
		$msg = $ok ? 'Freigegeben.' . ( $c && $c['sent_at'] ? ' Die Abrechnung ist an Finom gesendet.' : '' ) : 'Freigabe nicht möglich (eigene Abrechnung, schon freigegeben oder storniert).';
		self::back( 'claims', $msg, array( 'claim' => $id ) + ( $ok ? array() : array( 'err' => 1 ) ) );
	}

	public static function handle_finom() {
		if ( ! current_user_can( msb_capability( 'settings' ) ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'msb_exp_finom' );
		$to = isset( $_POST['expense_finom_email'] ) ? trim( sanitize_email( wp_unslash( $_POST['expense_finom_email'] ) ) ) : '';
		if ( '' !== $to && ! is_email( $to ) ) {
			self::back( 'claims', 'Die E-Mail-Adresse ist ungültig.', array( 'err' => 1 ) );
		}
		$s                        = get_option( 'msb_settings', array() );
		$s                        = is_array( $s ) ? $s : array();
		$s['expense_finom_email'] = $to;
		update_option( 'msb_settings', $s );
		self::back( 'claims', '' !== $to ? 'Finom-Beleg-Postfach gespeichert.' : 'Finom-Beleg-Postfach entfernt, es wird nichts mehr gesendet.' );
	}

	/**
	 * Abruf einer Datei aus der geschützten Ablage (Beleg einer Auslage oder PDF einer Abrechnung).
	 */
	public static function handle_file() {
		// phpcs:disable WordPress.Security.NonceVerification
		$kind = isset( $_GET['kind'] ) ? sanitize_key( $_GET['kind'] ) : '';
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		// phpcs:enable
		if ( ! current_user_can( MSB_Expenses::cap() ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'msb_exp_file_' . $kind . '_' . $id );
		$path = '';
		$name = '';
		if ( 'claim' === $kind ) {
			$c = MSB_Expenses::claim( $id );
			if ( $c ) {
				$path = MSB_Expenses::file( $id );
				$name = $c['number'] . '.pdf';
			}
		} elseif ( 'receipt' === $kind ) {
			$p = MSB_Expenses::position( $id );
			if ( $p && '' !== $p['receipt_file'] && ( (int) $p['user_id'] === get_current_user_id() || (int) $p['claim_id'] || current_user_can( 'manage_options' ) ) ) {
				$path = MSB_Expenses::path( $p['receipt_file'] );
				$name = 'beleg-' . $p['spent_on'] . '.' . pathinfo( $p['receipt_file'], PATHINFO_EXTENSION );
			}
		}
		if ( ! $path || ! file_exists( $path ) ) {
			wp_die( 'Datei nicht gefunden.' );
		}
		$types = array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'heic' => 'image/heic',
			'heif' => 'image/heif',
		);
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		nocache_headers();
		header( 'Content-Type: ' . ( isset( $types[ $ext ] ) ? $types[ $ext ] : 'application/octet-stream' ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $name ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore
		exit;
	}
}
