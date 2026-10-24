<?php
/**
 * Content Board: kanban over post status, with drag between columns.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCDP_Board {

    const PAGE_SLUG    = 'scdp-board';
    const MOVE_ACTION  = 'scdp_move_status';
    const NONCE_ACTION = 'scdp_board_nonce';

    /** Cards fetched per column. */
    const PER_COLUMN = 60;

    /** How far back the Published column looks. */
    const PUBLISHED_DAYS = 14;

    private $hook = '';

    public static function init() {
        $self = new self();
        add_action( 'admin_menu', array( $self, 'add_menu' ), 7 );
        add_action( 'admin_enqueue_scripts', array( $self, 'enqueue' ) );
        add_action( 'wp_ajax_' . self::MOVE_ACTION, array( $self, 'handle_move' ) );
    }

    public function add_menu() {
        $this->hook = (string) add_submenu_page(
            SCD_Admin_Page::PAGE_SLUG,
            __( 'Content Board', 'scheduled-content-dashboard-pro' ),
            __( 'Board', 'scheduled-content-dashboard-pro' ),
            'edit_posts',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    public function enqueue( $hook ) {
        if ( ! $this->hook || $hook !== $this->hook ) {
            return;
        }

        wp_enqueue_script(
            'scdp-board',
            plugins_url( 'assets/js/board.js', SCDP_FILE ),
            array(),
            SCDP_VERSION,
            true
        );

        wp_localize_script(
            'scdp-board',
            'scdpBoard',
            array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( self::NONCE_ACTION ),
                'action'        => self::MOVE_ACTION,
                'moving'        => __( 'Moving…', 'scheduled-content-dashboard-pro' ),
                'moved'         => __( 'Moved.', 'scheduled-content-dashboard-pro' ),
                'failed'        => __( 'Could not move that post.', 'scheduled-content-dashboard-pro' ),
                'undo'          => __( 'Undo', 'scheduled-content-dashboard-pro' ),
                'confirmPublish' => __( 'Publish this post now?', 'scheduled-content-dashboard-pro' ),
                'empty'         => __( 'Nothing here', 'scheduled-content-dashboard-pro' ),
            )
        );

        wp_add_inline_style( 'common', $this->styles() );
    }

    private function styles() {
        return '
            .scdp-board { --scd-panel:#ffffff; --scd-ink:#1d2230; --scd-soft:#5b6472; --scd-line:#e6e8ee;
                --scd-line2:#d6dae3; --scd-accent:#4f46e5; --scd-accent-weak:#eef0fe; --scd-miss:#dc2626;
                --scd-miss-weak:#fdecec; --scd-col:#f6f7fa;
                font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif; color:var(--scd-ink); }
            @media (prefers-color-scheme: dark) {
                .scdp-board { --scd-panel:#161922; --scd-ink:#e8eaf0; --scd-soft:#9aa3b2; --scd-line:#252a36;
                    --scd-line2:#313747; --scd-accent:#8b85f5; --scd-accent-weak:#20223a; --scd-miss:#f87171;
                    --scd-miss-weak:#2a1a1d; --scd-col:#12141c; }
            }

            .scdp-toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin:14px 0; }
            .scdp-toolbar h1 { font-size:20px; font-weight:700; letter-spacing:-.02em; margin:0; }
            .scdp-toolbar form { display:inline-flex; gap:8px; align-items:center; }
            .scdp-toolbar select { font-size:13px; padding:4px 8px; border:1px solid var(--scd-line2);
                border-radius:8px; background:var(--scd-panel); color:var(--scd-ink); max-width:180px; }
            .scdp-spacer { flex:1; }
            .scdp-btn { text-decoration:none; font-size:13px; font-weight:600; border-radius:8px; padding:6px 13px;
                border:1px solid var(--scd-line2); color:var(--scd-ink); background:var(--scd-panel); }
            .scdp-btn:hover { border-color:var(--scd-accent); color:var(--scd-ink); }

            .scdp-cols { display:flex; gap:12px; align-items:flex-start; overflow-x:auto; padding:0 4px 12px 0; }
            .scdp-col { flex:1 1 225px; min-width:190px; max-width:340px; background:var(--scd-col); border:1px solid var(--scd-line);
                border-radius:12px; padding:10px; min-height:180px; display:flex; flex-direction:column; gap:8px; }
            .scdp-col.is-over { outline:2px dashed var(--scd-accent); outline-offset:-3px; background:var(--scd-accent-weak); }
            .scdp-col--locked { opacity:.72; }
            .scdp-colhead { display:flex; align-items:center; gap:7px; font-size:11.5px; font-weight:700;
                text-transform:uppercase; letter-spacing:.06em; color:var(--scd-soft); }
            .scdp-colhead .scdp-d { width:9px; height:9px; border-radius:50%; }
            .scdp-colhead .scdp-n { margin-left:auto; font-size:11px; background:var(--scd-panel);
                border:1px solid var(--scd-line); border-radius:999px; padding:1px 7px; letter-spacing:0; }

            .scdp-card { display:block; background:var(--scd-panel); border:1px solid var(--scd-line);
                border-radius:9px; padding:8px 9px; text-decoration:none; color:var(--scd-ink); cursor:grab; }
            .scdp-card:hover { border-color:var(--scd-line2); color:var(--scd-ink); }
            .scdp-card.is-dragging { opacity:.45; }
            .scdp-card.is-miss { background:var(--scd-miss-weak); border-color:var(--scd-miss); }
            .scdp-card:focus { outline:2px solid var(--scd-accent); outline-offset:1px; }
            .scdp-ti { font-size:13px; font-weight:600; line-height:1.35; display:block; margin-bottom:5px;
                overflow:hidden; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; }
            .scdp-meta { display:flex; align-items:center; gap:7px; font-size:11px; color:var(--scd-soft); }
            .scdp-meta .scdp-d { width:7px; height:7px; border-radius:50%; flex:0 0 auto; }
            .scdp-when { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; }
            .scdp-when.is-miss { color:var(--scd-miss); font-weight:700; }
            .scdp-who { margin-left:auto; width:17px; height:17px; border-radius:50%; font-size:9px; font-weight:700;
                color:#fff; display:grid; place-items:center; flex:0 0 auto; }
            .scdp-none { font-size:12px; color:var(--scd-soft); text-align:center; padding:14px 0; }

            .scdp-feedback { position:fixed; bottom:24px; right:24px; background:var(--scd-ink); color:var(--scd-panel);
                padding:10px 14px; border-radius:9px; font-size:13px; z-index:9999; display:none; align-items:center;
                gap:12px; box-shadow:0 6px 20px rgba(0,0,0,.25); }
            .scdp-feedback button { background:none; border:0; color:var(--scd-accent); font-weight:700; cursor:pointer; font-size:13px; }
            .scdp-feedback.is-error { background:var(--scd-miss); color:#fff; }

            .scdp-panel { border:1px solid var(--scd-line); border-radius:12px; background:var(--scd-panel);
                padding:22px; max-width:640px; font-size:13.5px; line-height:1.6; }
            .scdp-panel h2 { margin-top:0; font-size:16px; }
        ';
    }

    private function params() {
        $type = isset( $_GET['scd_type'] ) ? sanitize_key( wp_unslash( $_GET['scd_type'] ) ) : '';
        if ( '' !== $type && ! in_array( $type, $this->base_types(), true ) ) {
            $type = '';
        }
        $author = isset( $_GET['scd_author'] ) ? absint( $_GET['scd_author'] ) : 0;

        return compact( 'type', 'author' );
    }

    private function base_types() {
        $types = (array) SCD_Settings::get( 'included_post_types', array( 'post' ) );
        $types = array_values( array_filter( $types, 'post_type_exists' ) );
        return $types ? $types : array( 'post' );
    }

    /**
     * Board columns: the pipeline statuses the site enabled, then Published.
     *
     * @return array status name => label
     */
    private function columns() {
        $columns = array();
        foreach ( SCD_Statuses::selected() as $status ) {
            $columns[ $status ] = SCD_Statuses::label( $status );
        }
        $columns['publish'] = __( 'Published', 'scheduled-content-dashboard-pro' );

        /**
         * Filters the board columns.
         *
         * @param array $columns Status name => column label.
         */
        return apply_filters( 'scdp_board_columns', $columns );
    }

    public function render_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'scheduled-content-dashboard-pro' ) );
        }

        echo '<div class="wrap scdp-board">';

        $p       = $this->params();
        $columns = $this->columns();

        $this->render_toolbar( $p );

        if ( count( SCD_Statuses::selected() ) < 2 ) {
            printf(
                '<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__( 'The board works best with more than one pipeline status. Enable Draft, Pending, or a custom status to get columns worth dragging between.', 'scheduled-content-dashboard-pro' ),
                esc_url( admin_url( 'admin.php?page=' . SCD_Settings::PAGE_SLUG ) ),
                esc_html__( 'Open settings', 'scheduled-content-dashboard-pro' )
            );
        }

        echo '<div class="scdp-cols">';
        foreach ( $columns as $status => $label ) {
            $this->render_column( $status, $label, $p );
        }
        echo '</div>';

        echo '<div id="scdp-feedback" class="scdp-feedback" role="status" aria-live="polite"></div>';
        echo '</div>';
    }

    private function render_toolbar( $p ) {
        ?>
        <div class="scdp-toolbar">
            <h1><?php esc_html_e( 'Content Board', 'scheduled-content-dashboard-pro' ); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
                <select name="scd_type" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by type', 'scheduled-content-dashboard-pro' ); ?>">
                    <option value=""><?php esc_html_e( 'All types', 'scheduled-content-dashboard-pro' ); ?></option>
                    <?php foreach ( $this->base_types() as $type ) :
                        $object = get_post_type_object( $type ); ?>
                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $p['type'], $type ); ?>>
                            <?php echo esc_html( $object ? $object->labels->name : $type ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="scd_author" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by author', 'scheduled-content-dashboard-pro' ); ?>">
                    <option value="0"><?php esc_html_e( 'All authors', 'scheduled-content-dashboard-pro' ); ?></option>
                    <?php foreach ( $this->author_choices() as $id => $name ) : ?>
                        <option value="<?php echo (int) $id; ?>" <?php selected( $p['author'], $id ); ?>><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <span class="scdp-spacer"></span>

            <a class="scdp-btn" href="<?php echo esc_url( menu_page_url( SCD_Admin_Page::PAGE_SLUG, false ) ); ?>"><?php esc_html_e( 'Calendar', 'scheduled-content-dashboard-pro' ); ?></a>
            <a class="scdp-btn" href="<?php echo esc_url( menu_page_url( SCD_Overview::PAGE_SLUG, false ) ); ?>"><?php esc_html_e( 'Overview', 'scheduled-content-dashboard-pro' ); ?></a>
        </div>
        <?php
    }

    private function render_column( $status, $label, $p ) {
        $items    = $this->column_items( $status, $p );
        $can_drop = $this->can_move_to( $status ) ? '1' : '0';

        $classes = array( 'scdp-col' );
        if ( '0' === $can_drop ) {
            $classes[] = 'scdp-col--locked';
        }

        printf(
            '<div class="%1$s" data-status="%2$s" data-droppable="%3$s">',
            esc_attr( implode( ' ', $classes ) ),
            esc_attr( $status ),
            esc_attr( $can_drop )
        );

        printf(
            '<div class="scdp-colhead"><span class="scdp-d" style="background:%1$s"></span>%2$s<span class="scdp-n">%3$d</span></div>',
            esc_attr( SCD_Statuses::color( $status ) ),
            esc_html( $label ),
            (int) count( $items )
        );

        if ( empty( $items ) ) {
            printf( '<div class="scdp-none">%s</div>', esc_html__( 'Nothing here', 'scheduled-content-dashboard-pro' ) );
        }

        foreach ( $items as $item ) {
            $this->render_card( $item );
        }

        echo '</div>';
    }

    private function render_card( $item ) {
        $classes = array( 'scdp-card' );
        if ( $item['is_missed'] ) {
            $classes[] = 'is-miss';
        }
        printf(
            '<a class="%1$s" href="%2$s" draggable="%3$s" data-post-id="%4$d" data-status="%5$s">'
            . '<span class="scdp-ti">%6$s</span>'
            . '<span class="scdp-meta">'
            . '<span class="scdp-d" style="background:%7$s"></span>'
            . '<span>%8$s</span>'
            . '<span class="scdp-when %9$s">%10$s</span>'
            . '<span class="scdp-who" style="background:%11$s">%12$s</span>'
            . '</span></a>',
            esc_attr( implode( ' ', $classes ) ),
            esc_url( $item['edit_link'] ),
            $item['can_edit'] ? 'true' : 'false',
            (int) $item['id'],
            esc_attr( $item['status'] ),
            esc_html( $item['title'] ),
            esc_attr( $this->type_color( $item['type'] ) ),
            esc_html( $item['type_label'] ),
            $item['is_missed'] ? 'is-miss' : '',
            esc_html( $item['when'] ),
            esc_attr( $this->author_color( $item['author'] ) ),
            esc_html( $this->initial( $item['author'] ) )
        );
    }

    private function column_items( $status, $p ) {
        $args = array(
            'post_type'      => $p['type'] ? array( $p['type'] ) : $this->base_types(),
            'post_status'    => $status,
            'posts_per_page' => self::PER_COLUMN,
            'orderby'        => 'date',
            'order'          => 'publish' === $status ? 'DESC' : 'ASC',
            'no_found_rows'  => true,
        );

        if ( 'publish' === $status ) {
            $args['date_query'] = array(
                array(
                    'column' => 'post_date',
                    'after'  => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - ( self::PUBLISHED_DAYS * DAY_IN_SECONDS ) ),
                ),
            );
        }

        if ( $p['author'] > 0 ) {
            $args['author'] = $p['author'];
        }
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            $args['author'] = get_current_user_id();
        }

        $items = array();
        $now   = time();

        foreach ( get_posts( $args ) as $post ) {
            $type_object = get_post_type_object( $post->post_type );

            $items[] = array(
                'id'         => (int) $post->ID,
                'title'      => $post->post_title ? $post->post_title : __( '(no title)', 'scheduled-content-dashboard-pro' ),
                'edit_link'  => get_edit_post_link( $post->ID, 'raw' ),
                'status'     => $post->post_status,
                'type'       => $post->post_type,
                'type_label' => $type_object ? $type_object->labels->singular_name : $post->post_type,
                'author'     => get_the_author_meta( 'display_name', $post->post_author ),
                'when'       => mysql2date( 'M j, H:i', $post->post_date ),
                'is_missed'  => SCD_Statuses::can_miss( $post->post_status )
                    && strtotime( get_gmt_from_date( $post->post_date ) . ' UTC' ) <= $now,
                'can_edit'   => current_user_can( 'edit_post', $post->ID ),
            );
        }

        return $items;
    }

    /** Whether the current user could put anything into this column at all. */
    private function can_move_to( $status ) {
        if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
            return true;
        }
        foreach ( $this->base_types() as $type ) {
            $object = get_post_type_object( $type );
            if ( $object && current_user_can( $object->cap->publish_posts ) ) {
                return true;
            }
        }
        return false;
    }

    public function handle_move() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $target  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'scheduled-content-dashboard-pro' ) ), 403 );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => __( 'Post not found.', 'scheduled-content-dashboard-pro' ) ), 404 );
        }

        if ( ! array_key_exists( $target, $this->columns() ) ) {
            wp_send_json_error( array( 'message' => __( 'That column is not on this board.', 'scheduled-content-dashboard-pro' ) ), 400 );
        }
        if ( ! in_array( $post->post_type, $this->base_types(), true ) ) {
            wp_send_json_error( array( 'message' => __( 'That post type is not on this board.', 'scheduled-content-dashboard-pro' ) ), 400 );
        }
        if ( $target === $post->post_status ) {
            wp_send_json_success( array( 'id' => $post_id, 'status' => $target ) );
        }

        $type_object = get_post_type_object( $post->post_type );
        $publishing  = in_array( $target, array( 'publish', 'future' ), true );
        if ( $publishing && ( ! $type_object || ! current_user_can( $type_object->cap->publish_posts ) ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to publish this post type.', 'scheduled-content-dashboard-pro' ) ), 403 );
        }

        $update = array(
            'ID'          => $post_id,
            'post_status' => $target,
        );

        $dated = strtotime( get_gmt_from_date( $post->post_date ) . ' UTC' );

        if ( 'future' === $target && $dated <= time() ) {
            // Scheduling something dated in the past would publish it instead.
            $local = wp_date( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
        } elseif ( 'publish' === $target && $dated > time() ) {
            // Core sends a future-dated publish back to 'future'. The board asked
            // "publish now", so bring the date forward and mean it.
            $local = wp_date( 'Y-m-d H:i:s', time() );
        }

        if ( isset( $local ) ) {
            $update['post_date']     = $local;
            $update['post_date_gmt'] = get_gmt_from_date( $local );
            $update['edit_date']     = true;
        }

        $result = wp_update_post( $update, true );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        $fresh = get_post( $post_id );

        wp_send_json_success(
            array(
                'id'     => $post_id,
                'status' => $fresh ? $fresh->post_status : $target,
                'when'   => $fresh ? mysql2date( 'M j, H:i', $fresh->post_date ) : '',
            )
        );
    }

    private function author_choices() {
        $args = array(
            'post_type'      => $this->base_types(),
            'post_status'    => array_keys( $this->columns() ),
            'posts_per_page' => 500,
            'no_found_rows'  => true,
        );
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            $args['author'] = get_current_user_id();
        }

        $authors = array();
        foreach ( get_posts( $args ) as $post ) {
            $authors[ (int) $post->post_author ] = get_the_author_meta( 'display_name', $post->post_author );
        }
        asort( $authors );
        return $authors;
    }

    private function type_color( $type ) {
        $map = array(
            'post' => 'var(--scd-accent)',
            'page' => '#d97706',
        );
        if ( isset( $map[ $type ] ) ) {
            return $map[ $type ];
        }
        $palette = array( '#0d9488', '#db2777', '#7c3aed', '#0284c7', '#65a30d' );
        return $palette[ abs( crc32( (string) $type ) ) % count( $palette ) ];
    }

    private function author_color( $name ) {
        $palette = array( '#475569', '#7c3aed', '#0d9488', '#b45309', '#be123c', '#1d4ed8' );
        return $palette[ abs( crc32( (string) $name ) ) % count( $palette ) ];
    }

    private function initial( $name ) {
        $name = trim( (string) $name );
        return $name ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : '?';
    }
}
