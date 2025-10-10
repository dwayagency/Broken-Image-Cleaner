<?php
/**
 * Plugin Name: Eliminare immagini rotte
 * Description: Verifica e rimuove automaticamente le immagini rotte (404) dai tuoi post WordPress
 * Version:     2.2.0
 * Author:      DWAY Agency
 * Text Domain: broken-image-cleaner
 */

if (!defined('ABSPATH')) exit;

class DWAY_Broken_Image_Cleaner {
    const NONCE = 'dtbic_nonce';
    const SLUG  = 'dt-broken-image-cleaner';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
    }

    public function add_admin_page() {
        add_menu_page(
            'Broken Image Cleaner',
            'Image Cleaner',
            'manage_options',
            self::SLUG,
            [$this, 'render_page'],
            'dashicons-format-image',
            65
        );
    }

    public function enqueue_admin_styles($hook) {
        if ($hook !== 'toplevel_page_' . self::SLUG) {
            return;
        }
        
        wp_add_inline_style('wp-admin', '
            .bic-container { max-width: 1200px; margin: 20px 0; }
            .bic-card { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; margin-bottom: 20px; }
            .bic-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
            .bic-stat { background: #f6f7f7; padding: 15px; border-radius: 4px; text-align: center; }
            .bic-stat-value { font-size: 32px; font-weight: 600; color: #2271b1; margin: 10px 0; }
            .bic-stat-label { font-size: 13px; color: #646970; text-transform: uppercase; }
            .bic-broken-list { background: #fff; border-left: 4px solid #d63638; padding: 15px; margin: 10px 0; }
            .bic-url-tag { display: inline-block; background: #f0f0f1; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-family: monospace; margin: 2px; }
            .bic-success { border-left-color: #00a32a; }
            .bic-progress-bar { background: #fff; height: 24px; border-radius: 12px; overflow: hidden; margin: 10px 0; }
            .bic-progress-fill { background: linear-gradient(90deg, #2271b1, #72aee6); height: 100%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; font-weight: 600; }
        ');
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti.');
        }

        $did_run   = false;
        $results   = [];
        $limit     = isset($_POST['limit']) ? max(1, intval($_POST['limit'])) : 100;
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        $category  = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $offset    = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;
        $dry_run   = !isset($_POST['run']) || isset($_POST['dry_run']);
        $applied_changes = false;

        // Calcola totale post disponibili
        $total_posts = $this->count_posts($post_type, $category);

        // Gestisce lo svuotamento delle modifiche accumulate
        if (isset($_POST['clear_changes']) && check_admin_referer(self::NONCE)) {
            delete_transient('bic_pending_changes');
            $pending_changes = false;
        }
        // Gestisce l'applicazione diretta delle modifiche
        elseif (isset($_POST['apply_changes']) && check_admin_referer(self::NONCE)) {
            $did_run = true;
            $results = $this->apply_stored_changes();
            
            if (!empty($results['log'])) {
                $applied_changes = true;
                $dry_run = false; // Forza dry_run a false quando si applicano le modifiche
            }
        }
        // Scansione normale
        elseif (isset($_POST['run']) && check_admin_referer(self::NONCE)) {
            $did_run = true;
            $results = $this->scan_and_clean($limit, $post_type, $category, $offset, $dry_run);
            
            // Salva i risultati se è dry-run e ci sono immagini rotte
            if ($dry_run && !empty($results['log'])) {
                $this->store_changes($results['log']);
                // Ricarica pending_changes dopo l'aggiunta
                $pending_changes = get_transient('bic_pending_changes');
            } elseif (!$dry_run) {
                // Pulisce i dati salvati se non è dry-run
                delete_transient('bic_pending_changes');
            }
        }

        // Recupera eventuali modifiche in attesa
        $pending_changes = get_transient('bic_pending_changes');

        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-format-image" style="font-size: 32px; width: 32px; height: 32px;"></span>
                Broken Image Cleaner
            </h1>
            <p class="description" style="font-size: 14px;">
                Trova e rimuovi automaticamente le immagini rotte (che restituiscono errore 404) dai tuoi contenuti WordPress.
            </p>

            <div class="bic-container">
                
                <!-- Form Configurazione -->
                <div class="bic-card">
                    <h2>⚙️ Configurazione Scansione</h2>
                    
                    <form method="post" id="bic-form">
                        <?php wp_nonce_field(self::NONCE); ?>
                        <input type="hidden" name="offset" id="offset" value="<?php echo esc_attr($offset); ?>" />
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="post_type">Tipo di Contenuto</label>
                                </th>
                                <td>
                                    <select name="post_type" id="post_type" class="regular-text">
                                        <option value="post" <?php selected($post_type, 'post'); ?>>Post</option>
                                        <option value="page" <?php selected($post_type, 'page'); ?>>Pagine</option>
                                        <?php
                                        $custom_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
                                        foreach ($custom_types as $type) {
                                            echo '<option value="' . esc_attr($type->name) . '" ' . selected($post_type, $type->name, false) . '>' . esc_html($type->label) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="category">Categoria</label>
                                </th>
                                <td>
                                    <?php $categories = get_categories(['hide_empty' => false]); ?>
                                    <select name="category" id="category" class="regular-text">
                                        <option value="0">Tutte le categorie</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($category, $cat->term_id); ?>>
                                                <?php echo esc_html($cat->name); ?> (<?php echo intval($cat->count); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Filtra per categoria specifica (opzionale)</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="limit">Post per Batch</label>
                                </th>
                                <td>
                                    <input type="number" name="limit" id="limit" value="<?php echo esc_attr($limit); ?>" min="1" max="500" class="small-text">
                                    <p class="description">Numero di post da analizzare per volta (consigliato: 50-200)</p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Modalità</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="dry_run" value="1" <?php checked($dry_run); ?>>
                                        <strong>Modalità Anteprima (Dry Run)</strong>
                                    </label>
                                    <p class="description">Mostra solo un'anteprima senza modificare i post. Disattiva per applicare le modifiche.</p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if ($total_posts > 0): ?>
                            <div class="notice notice-info inline">
                                <p><strong>📊 Post totali trovati:</strong> <?php echo intval($total_posts); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($pending_changes && !$did_run): ?>
                            <?php 
                            $total_pending_broken = 0;
                            foreach ($pending_changes as $change) {
                                $total_pending_broken += count($change['broken_urls']);
                            }
                            ?>
                            <div class="notice notice-warning inline">
                                <p>
                                    <strong>⚠️ Modifiche accumulate!</strong> 
                                    Hai <strong><?php echo count($pending_changes); ?> post</strong> con <strong><?php echo $total_pending_broken; ?> immagini rotte</strong> pronte per essere eliminate. 
                                    <br><small>Le scansioni in modalità anteprima si accumulano. Scorri in basso per eliminare tutto o svuota la coda.</small>
                                </p>
                                <form method="post" style="display: inline-block; margin-top: 10px;">
                                    <?php wp_nonce_field(self::NONCE); ?>
                                    <button type="submit" name="clear_changes" class="button" onclick="return confirm('Vuoi svuotare tutte le modifiche accumulate? Dovrai fare una nuova scansione.');">
                                        <span class="dashicons dashicons-dismiss" style="margin-top: 4px;"></span>
                                        Svuota Modifiche Accumulate
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <p class="submit">
                            <button type="submit" name="run" class="button button-primary button-hero">
                                <span class="dashicons dashicons-search" style="margin-top: 8px;"></span>
                                <?php echo $offset > 0 ? 'Continua Scansione' : 'Avvia Scansione'; ?>
                            </button>
                            <?php if ($offset > 0): ?>
                                <button type="button" class="button button-large" onclick="document.getElementById('offset').value='0';document.getElementById('bic-form').submit();">
                                    <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
                                    Ricomincia da Capo
                                </button>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>

            <?php if ($did_run): ?>
                <?php 
                $next_offset = $offset + $results['scanned'];
                $has_more = $next_offset < $total_posts;
                $progress_percent = $total_posts > 0 ? min(100, round(($next_offset / $total_posts) * 100)) : 100;
                ?>

                <!-- Progresso -->
                <?php if ($total_posts > $limit): ?>
                <div class="bic-card" style="background: #f0f6fc; border-color: #c3d6e8;">
                    <h2>📈 Progresso Scansione</h2>
                    <p style="margin: 0 0 10px 0; font-size: 16px;"><strong><?php echo intval($next_offset); ?> / <?php echo intval($total_posts); ?> post analizzati</strong></p>
                    <div class="bic-progress-bar">
                        <div class="bic-progress-fill" style="width: <?php echo intval($progress_percent); ?>%;">
                            <?php echo intval($progress_percent); ?>%
                        </div>
                    </div>
                    <?php if ($has_more): ?>
                        <p style="margin: 15px 0 0 0; color: #d63638;">
                            <strong>⚠️ Rimangono ancora <?php echo intval($total_posts - $next_offset); ?> post da analizzare</strong>
                        </p>
                    <?php else: ?>
                        <p style="margin: 15px 0 0 0; color: #00a32a;">
                            <strong>✅ Scansione completata!</strong>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Statistiche -->
                <div class="bic-card">
                    <h2>📊 Risultati Batch Corrente</h2>
                    
                    <?php if ($dry_run && $pending_changes): ?>
                        <div class="notice notice-info inline" style="margin-bottom: 15px;">
                            <p style="margin: 5px 0;">
                                <strong>ℹ️ Modalità accumulo attiva:</strong> 
                                I risultati di questa scansione si aggiungono a quelli precedenti. 
                                Totale accumulato: <strong><?php 
                                $tot = 0; 
                                foreach ($pending_changes as $c) $tot += count($c['broken_urls']); 
                                echo intval($tot); 
                                ?> immagini</strong> in <strong><?php echo count($pending_changes); ?> post</strong>.
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="bic-stats">
                        <div class="bic-stat">
                            <div class="bic-stat-label">Post Analizzati</div>
                            <div class="bic-stat-value"><?php echo intval($results['scanned']); ?></div>
                        </div>
                        <div class="bic-stat">
                            <div class="bic-stat-label">Immagini Totali</div>
                            <div class="bic-stat-value"><?php echo intval($results['images_found']); ?></div>
                        </div>
                        <div class="bic-stat">
                            <div class="bic-stat-label">Immagini Rotte</div>
                            <div class="bic-stat-value" style="color: <?php echo $results['broken_found'] > 0 ? '#d63638' : '#00a32a'; ?>;">
                                <?php echo intval($results['broken_found']); ?>
                            </div>
                        </div>
                        <?php if (!$dry_run): ?>
                        <div class="bic-stat">
                            <div class="bic-stat-label">Post Aggiornati</div>
                            <div class="bic-stat-value" style="color: #00a32a;">
                                <?php echo intval($results['posts_updated']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($applied_changes): ?>
                        <div class="notice notice-success inline" style="background: #d4edda; border-color: #28a745;">
                            <p>
                                <strong>✅ Eliminazione Completata!</strong> 
                                Tutte le <strong><?php echo intval($results['broken_found']); ?> immagini rotte accumulate</strong> 
                                sono state rimosse da <strong><?php echo intval($results['posts_updated']); ?> post</strong>.
                                <br><small>La coda di modifiche accumulate è stata svuotata.</small>
                            </p>
                        </div>
                    <?php elseif ($dry_run): ?>
                        <div class="notice notice-warning inline">
                            <p><strong>⚠️ Modalità Anteprima:</strong> Nessuna modifica è stata salvata.</p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-success inline">
                            <p><strong>✅ Modifiche Applicate:</strong> I post sono stati aggiornati correttamente.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pulsante Continua -->
                <?php if ($has_more): ?>
                <div class="bic-card" style="text-align: center; background: #fff3cd; border-color: #ffc107;">
                    <form method="post">
                        <?php wp_nonce_field(self::NONCE); ?>
                        <input type="hidden" name="offset" value="<?php echo intval($next_offset); ?>" />
                        <input type="hidden" name="limit" value="<?php echo intval($limit); ?>" />
                        <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>" />
                        <input type="hidden" name="category" value="<?php echo intval($category); ?>" />
                        <?php if ($dry_run): ?>
                            <input type="hidden" name="dry_run" value="1" />
                        <?php endif; ?>
                        <button type="submit" name="run" class="button button-primary button-hero" style="margin: 10px 0;">
                            <span class="dashicons dashicons-controls-forward" style="margin-top: 8px;"></span>
                            Continua Scansione (prossimi <?php echo min($limit, $total_posts - $next_offset); ?> post)
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Pulsante Applica Modifiche (solo se dry-run e non già applicato) -->
                <?php if ($dry_run && $pending_changes && !$applied_changes): ?>
                    <?php 
                    $total_accumulated_broken = 0;
                    $total_accumulated_posts = count($pending_changes);
                    foreach ($pending_changes as $change) {
                        $total_accumulated_broken += count($change['broken_urls']);
                    }
                    ?>
                <div class="bic-card" style="text-align: center; background: #d63638; border-color: #b32d2e; color: #fff;">
                    <h2 style="color: #fff; margin-top: 0;">🗑️ Pronto per Eliminare TUTTO</h2>
                    <p style="font-size: 16px; margin: 15px 0;">
                        <strong>Totale accumulato:</strong> <strong><?php echo intval($total_accumulated_broken); ?> immagini rotte</strong> in <strong><?php echo intval($total_accumulated_posts); ?> post</strong>.
                    </p>
                    <?php if (!empty($results['log'])): ?>
                        <p style="font-size: 14px; margin: 10px 0; opacity: 0.9;">
                            Questa scansione ha trovato <strong><?php echo intval($results['broken_found']); ?> nuove immagini rotte</strong> in <strong><?php echo count($results['log']); ?> post</strong>.
                        </p>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('Sei sicuro di voler eliminare TUTTE le <?php echo intval($total_accumulated_broken); ?> immagini rotte accumulate da tutte le scansioni? Questa azione non può essere annullata.');">
                        <?php wp_nonce_field(self::NONCE); ?>
                        <button type="submit" name="apply_changes" class="button button-hero" style="background: #fff; color: #d63638; border-color: #fff; margin: 10px 0;">
                            <span class="dashicons dashicons-trash" style="margin-top: 8px;"></span>
                            Elimina TUTTE le <?php echo intval($total_accumulated_broken); ?> Immagini Rotte
                        </button>
                    </form>
                    <p style="font-size: 13px; margin: 10px 0 0 0; opacity: 0.9;">
                        ⚠️ Verranno eliminati i risultati di TUTTE le scansioni accumulate
                    </p>
                </div>
                <?php endif; ?>

                <!-- Dettagli -->
                <?php if (!empty($results['log'])): ?>
                <div class="bic-card">
                    <h2>🔍 Immagini Rotte Trovate</h2>
                    
                    <?php foreach ($results['log'] as $detail): ?>
                        <div class="bic-broken-list <?php echo !$dry_run ? 'bic-success' : ''; ?>">
                            <h3 style="margin-top: 0;">
                                Post #<?php echo intval($detail['post_id']); ?>: 
                                <a href="<?php echo get_edit_post_link($detail['post_id']); ?>" target="_blank">
                                    <?php echo esc_html($detail['post_title']); ?>
                                </a>
                            </h3>
                            <p><strong>Immagini rotte trovate:</strong></p>
                            <div>
                                <?php foreach ($detail['broken_urls'] as $url): ?>
                                    <span class="bic-url-tag"><?php echo esc_html($url); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php elseif ($results['scanned'] > 0): ?>
                <div class="bic-card">
                    <div class="notice notice-success inline">
                        <p>✅ <strong>Ottimo!</strong> Nessuna immagine rotta trovata nei post analizzati in questo batch.</p>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>

            <!-- Info -->
            <div class="bic-card" style="background: #f0f6fc; border-color: #c3d6e8;">
                <h3>💡 Come Funziona</h3>
                <ul style="line-height: 1.8;">
                    <li>Il plugin scansiona il contenuto dei post pubblicati cercando tag <code>&lt;img&gt;</code></li>
                    <li>Per ogni immagine, verifica se l'URL restituisce un errore 404</li>
                    <li>Se l'immagine è dentro un tag <code>&lt;figure&gt;</code>, rimuove l'intero blocco</li>
                    <li>Altrimenti rimuove solo il tag <code>&lt;img&gt;</code></li>
                    <li><strong>Scansione progressiva:</strong> Analizza i post in batch per evitare timeout del server</li>
                    <li><strong>Consiglio:</strong> Usa sempre prima la modalità Anteprima per vedere cosa verrà rimosso</li>
                </ul>
            </div>

            <!-- Modifiche in Attesa (se non c'è stata scansione ma ci sono pending changes) -->
            <?php if ($pending_changes && !$did_run): ?>
                <?php 
                $total_broken = 0;
                foreach ($pending_changes as $change) {
                    $total_broken += count($change['broken_urls']);
                }
                ?>
                
                <div class="bic-card" style="text-align: center; background: #d63638; border-color: #b32d2e; color: #fff;">
                    <h2 style="color: #fff; margin-top: 0;">🗑️ Modifiche Accumulate - Pronte per l'Eliminazione</h2>
                    <p style="font-size: 18px; margin: 15px 0; font-weight: bold;">
                        TOTALE: <strong><?php echo intval($total_broken); ?> immagini rotte</strong> in <strong><?php echo count($pending_changes); ?> post</strong>
                    </p>
                    <div style="background: rgba(255,255,255,0.2); padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <p style="margin: 0; font-size: 14px;">
                            💡 Tutte le scansioni in modalità anteprima sono state accumulate.<br>
                            Puoi continuare a scansionare per aggiungere altri post, oppure eliminare tutto ora.
                        </p>
                    </div>
                    <form method="post" onsubmit="return confirm('Sei sicuro di voler eliminare TUTTE le <?php echo intval($total_broken); ?> immagini rotte accumulate? Questa azione non può essere annullata.');">
                        <?php wp_nonce_field(self::NONCE); ?>
                        <button type="submit" name="apply_changes" class="button button-hero" style="background: #fff; color: #d63638; border-color: #fff; margin: 10px 0; font-size: 16px; padding: 8px 24px;">
                            <span class="dashicons dashicons-trash" style="margin-top: 8px;"></span>
                            Elimina TUTTE le <?php echo intval($total_broken); ?> Immagini Rotte
                        </button>
                    </form>
                </div>

                <div class="bic-card">
                    <h2>🔍 Dettaglio Immagini Rotte in Attesa</h2>
                    
                    <?php foreach ($pending_changes as $detail): ?>
                        <div class="bic-broken-list">
                            <h3 style="margin-top: 0;">
                                Post #<?php echo intval($detail['post_id']); ?>: 
                                <a href="<?php echo get_edit_post_link($detail['post_id']); ?>" target="_blank">
                                    <?php echo esc_html($detail['post_title']); ?>
                                </a>
                            </h3>
                            <p><strong>Immagini rotte trovate:</strong></p>
                            <div>
                                <?php foreach ($detail['broken_urls'] as $url): ?>
                                    <span class="bic-url-tag"><?php echo esc_html($url); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            </div>
        </div>
        <?php
    }

    private function count_posts($post_type, $category = 0) {
        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ];

        if ($category > 0) {
            $args['cat'] = $category;
        }

        $query = new WP_Query($args);
        return $query->found_posts;
    }

    private function scan_and_clean($limit, $post_type, $category = 0, $offset = 0, $dry_run = true) {
        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
            'fields'         => 'all',
        ];

        if ($category > 0) {
            $args['cat'] = $category;
        }

        $q = new WP_Query($args);

        $scanned       = 0;
        $images_found  = 0;
        $broken_found  = 0;
        $posts_updated = 0;
        $log           = [];

        if ($q->have_posts()) {
            while ($q->have_posts()) {
                $q->the_post();
                $post_id    = get_the_ID();
                $post_title = get_the_title($post_id);
                $content    = get_post_field('post_content', $post_id, 'raw');

                $res = $this->process_content($content);
                $scanned++;

                $images_found += $res['images_total'];
                $broken_found += count($res['broken_urls']);

                if (!empty($res['broken_urls'])) {
                    $log[] = [
                        'post_id'     => $post_id,
                        'post_title'  => $post_title,
                        'broken_urls' => $res['broken_urls'],
                    ];
                }

                if (!$dry_run && $res['modified'] && isset($res['new_content'])) {
                    $update = [
                        'ID'           => $post_id,
                        'post_content' => $res['new_content'],
                    ];
                    $result = wp_update_post($update, true);
                    if (!is_wp_error($result)) {
                        $posts_updated++;
                    }
                }
            }
            wp_reset_postdata();
        }

        return [
            'scanned'       => $scanned,
            'images_found'  => $images_found,
            'broken_found'  => $broken_found,
            'posts_updated' => $posts_updated,
            'log'           => $log,
        ];
    }

    private function process_content($html) {
        if (trim($html) === '') {
            return [
                'images_total' => 0,
                'broken_urls'  => [],
                'modified'     => false,
            ];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="__wrap">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);
        $imgs  = $xpath->query('//img[@src]');
        $images_total = $imgs->length;

        $broken_urls = [];
        $to_remove   = [];

        foreach ($imgs as $img) {
            /** @var DOMElement $img */
            $src = $img->getAttribute('src');
            if (!$src) continue;

            if ($this->is_404($src)) {
                $broken_urls[] = $src;
                $parent = $img->parentNode;
                while ($parent && $parent->nodeName !== 'figure' && $parent->nodeName !== 'div') {
                    $parent = $parent->parentNode;
                }
                if ($parent && $parent->nodeName === 'figure') {
                    $to_remove[] = $parent;
                } else {
                    $to_remove[] = $img;
                }
            }
        }

        foreach ($to_remove as $node) {
            $node->parentNode->removeChild($node);
        }

        $wrapper = $dom->getElementById('__wrap');
        $new_html = '';
        if ($wrapper) {
            foreach (iterator_to_array($wrapper->childNodes) as $child) {
                $new_html .= $dom->saveHTML($child);
            }
        } else {
            $new_html = $html;
        }

        return [
            'images_total' => $images_total,
            'broken_urls'  => $broken_urls,
            'modified'     => $new_html !== $html,
            'new_content'  => $new_html,
        ];
    }

    private function is_404($url) {
        if (strpos($url, '//') === 0) {
            $url = (is_ssl() ? 'https:' : 'http:') . $url;
        } elseif (parse_url($url, PHP_URL_SCHEME) === null) {
            $url = home_url($url);
        }

        $response = wp_remote_head($url, [
            'timeout'     => 8,
            'redirection' => 3,
        ]);

        $code = $this->response_code($response);

        if ($code === null) {
            $response = wp_remote_get($url, [
                'timeout'     => 8,
                'redirection' => 3,
            ]);
            $code = $this->response_code($response);
        }

        return ($code === 404);
    }

    private function response_code($response) {
        if (is_wp_error($response)) return null;
        $code = wp_remote_retrieve_response_code($response);
        if (empty($code)) return null;
        return intval($code);
    }

    private function store_changes($log) {
        // Recupera le modifiche già esistenti
        $existing_changes = get_transient('bic_pending_changes');
        if (!is_array($existing_changes)) {
            $existing_changes = [];
        }

        // Array per tracciare i post già presenti
        $existing_post_ids = [];
        foreach ($existing_changes as $change) {
            $existing_post_ids[] = $change['post_id'];
        }

        // Aggiungi solo i nuovi post (evita duplicati)
        $new_changes = $existing_changes;
        
        foreach ($log as $item) {
            $post_id = $item['post_id'];
            
            // Salta se il post è già stato processato
            if (in_array($post_id, $existing_post_ids)) {
                continue;
            }
            
            $content = get_post_field('post_content', $post_id, 'raw');
            $processed = $this->process_content($content);
            
            if ($processed['modified']) {
                $new_changes[] = [
                    'post_id' => $post_id,
                    'post_title' => $item['post_title'],
                    'new_content' => $processed['new_content'],
                    'broken_urls' => $item['broken_urls']
                ];
            }
        }
        
        set_transient('bic_pending_changes', $new_changes, DAY_IN_SECONDS);
    }

    private function apply_stored_changes() {
        $changes = get_transient('bic_pending_changes');
        
        if (empty($changes)) {
            return [
                'scanned' => 0,
                'images_found' => 0,
                'broken_found' => 0,
                'posts_updated' => 0,
                'log' => []
            ];
        }

        $posts_updated = 0;
        $broken_found = 0;
        $log = [];

        foreach ($changes as $change) {
            $result = wp_update_post([
                'ID' => $change['post_id'],
                'post_content' => $change['new_content']
            ], true);

            if (!is_wp_error($result)) {
                $posts_updated++;
                $broken_found += count($change['broken_urls']);
                $log[] = [
                    'post_id' => $change['post_id'],
                    'post_title' => $change['post_title'],
                    'broken_urls' => $change['broken_urls']
                ];
            }
        }

        // Rimuove i dati salvati dopo l'applicazione
        delete_transient('bic_pending_changes');

        return [
            'scanned' => count($changes),
            'images_found' => 0,
            'broken_found' => $broken_found,
            'posts_updated' => $posts_updated,
            'log' => $log
        ];
    }
}

new DWAY_Broken_Image_Cleaner();
