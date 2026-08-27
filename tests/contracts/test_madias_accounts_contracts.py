from __future__ import annotations

import json
import re
import subprocess
import textwrap
import unittest

from php_source import REPO_ROOT, source

CUSTOM_AUTH = "plugin/seoflix-core/includes/class-custom-auth.php"
CONTACT = "plugin/seoflix-core/includes/class-contact.php"
USER_ACCOUNTS = "plugin/seoflix-core/includes/class-user-accounts.php"
ADMIN_RGPD = "plugin/seoflix-core/admin/class-admin-rgpd.php"
AFFILIATE = "plugin/seoflix-core/includes/class-affiliate.php"
LEGAL = "plugin/seoflix-core/admin/class-legal-pages.php"


def compact(text: str) -> str:
    return re.sub(r"\s+", " ", text)


class MadiasAccountsContracts(unittest.TestCase):
    def test_public_contact_uses_frontend_route_instead_of_hidden_wp_admin(self) -> None:
        contact = compact(source(CONTACT))
        custom_auth = compact(source(CUSTOM_AUTH))
        render = contact[contact.index("function render_shortcode") : contact.index("function handle_submit")]
        route = custom_auth[custom_auth.index("function route_frontend_action(") : custom_auth.index("function frontend_action_url(")]
        self.assertIn("home_url( '/sx-auth/contact/' )", render)
        self.assertNotIn("admin_url( 'admin-post.php' )", render)
        self.assertIn("'contact' => [ Contact::class, 'handle_submit' ]", route)

    def test_flag_off_closes_registration_and_cleanup_is_always_registered(self) -> None:
        php = compact(source(USER_ACCOUNTS))
        init = php[php.index("function init") : php.index("function enable_registration")]
        self.assertIn("add_action( 'delete_user', [ self::class, 'delete_user_data' ] )", init)
        self.assertIn("add_filter( 'wp_privacy_personal_data_exporters'", init)
        self.assertIn("add_filter( 'wp_privacy_personal_data_erasers'", init)
        self.assertLess(init.index("delete_user"), init.index("if ( ! FeatureFlags::user_accounts_enabled() )"))
        self.assertIn("self::enforce_registration_state()", init)
        self.assertRegex(
            php,
            re.compile(r"function enforce_registration_state\(\): void \{.*?FeatureFlags::user_accounts_enabled\(\).*?update_option\( 'users_can_register', 0 \)", re.S),
        )

    def test_privacy_export_uses_snapshot_keyset_not_mutable_offset(self) -> None:
        php = compact(source(USER_ACCOUNTS))
        exporter = php[php.index("function personal_data_exporter") : php.index("function personal_data_eraser")]
        self.assertIn("function privacy_export_request_id", php)
        keying = php[php.index("function privacy_export_request_id") : php.index("function privacy_export_failure")]
        self.assertNotIn("OFFSET", exporter)
        self.assertIn("MAX(id)", exporter)
        self.assertIn("id > %d", exporter)
        self.assertIn("id <= %d", exporter)
        self.assertIn("get_transient", exporter)
        self.assertIn("set_transient", exporter)
        self.assertIn("privacy_export_request_id", exporter)
        self.assertIn("$request_id", keying)
        self.assertIn("$_POST['id']", keying)
        self.assertIn("EXPORT_STATE_TTL", php)

    def test_privacy_reads_fail_closed_on_sql_errors(self) -> None:
        php = compact(source(USER_ACCOUNTS))
        failure = php[php.index("function privacy_export_failure") : php.index("function personal_data_exporter")]
        exporter = php[php.index("function personal_data_exporter") : php.index("function personal_data_eraser")]
        eraser = php[php.index("function personal_data_eraser") : php.index("function register_rewrites")]
        self.assertIn("'done' => false", failure)
        self.assertIn("self::privacy_export_failure()", exporter)
        for block in (exporter, eraser):
            self.assertIn("$wpdb->last_error", block)
        self.assertIn("'done' => ! $failed", eraser)
        self.assertIn("'items_retained' => $failed", eraser)

    def test_user_deletion_stops_when_account_cleanup_fails(self) -> None:
        php = compact(source(USER_ACCOUNTS))
        block = php[php.index("function delete_user_data") : php.index("function register_privacy_exporter")]
        self.assertGreaterEqual(block.count("false === $result"), 1)
        self.assertIn("wp_die", block)
        self.assertIn("n’ont pas pu être supprimées", block)

    def test_account_rows_are_deleted_on_user_deletion(self) -> None:
        php = compact(source(USER_ACCOUNTS))
        block = php[php.index("function delete_user_data") : php.index("function register_privacy_exporter")]
        self.assertIn("DB_Schema::table_favorites()", block)
        self.assertIn("DB_Schema::table_watch()", block)
        self.assertIn("foreach ( [ DB_Schema::table_favorites(), DB_Schema::table_watch() ] as $table )", block)
        self.assertEqual(block.count("$wpdb->delete("), 1)
        self.assertIn("'user_id' => $user_id", block)

    def test_privacy_harness_is_mutation_stable_and_fails_closed(self) -> None:
        source_path = json.dumps(str(REPO_ROOT / USER_ACCOUNTS))
        harness = textwrap.dedent(
            """
            <?php
            namespace {
                define('ABSPATH', __DIR__);
                define('HOUR_IN_SECONDS', 3600);
                define('ARRAY_A', 'ARRAY_A');
                $GLOBALS['sx_transients'] = [];
                $GLOBALS['sx_lookup_mode'] = 'found';
                $GLOBALS['sx_lookup_calls'] = 0;
                function get_user_by($field, $email) {
                    global $wpdb;
                    $GLOBALS['sx_lookup_calls']++;
                    if ('error' === $GLOBALS['sx_lookup_mode']) { $wpdb->last_error = 'simulated lookup failure'; return false; }
                    $wpdb->last_error = '';
                    if ('missing' === $GLOBALS['sx_lookup_mode']) return false;
                    return (object) ['ID' => 7];
                }
                function wp_unslash($value) { return $value; }
                function absint($value) { return abs((int) $value); }
                function wp_hash($value, $scheme = '') { return hash('sha256', $scheme . '|' . $value); }
                function get_transient($key) { return $GLOBALS['sx_transients'][$key] ?? false; }
                function set_transient($key, $value, $ttl) { $GLOBALS['sx_transients'][$key] = $value; return true; }
                function delete_transient($key) { unset($GLOBALS['sx_transients'][$key]); return true; }
                class WPDBStub {
                    public string $last_error = '';
                    public bool $fail_reads = false;
                    public array $favorites = [];
                    public array $watch = [];
                    public function __construct() {
                        for ($i = 1; $i <= 250; $i++) $this->favorites[$i] = ['id'=>$i,'video_id'=>$i,'created_at'=>'2026-01-01'];
                    }
                    public function prepare($query, ...$args) {
                        foreach ($args as $arg) $query = preg_replace('/%d/', (string) (int) $arg, $query, 1);
                        return $query;
                    }
                    private function rowsFor(string $table): array { return 'wp_favorites' === $table ? $this->favorites : $this->watch; }
                    private function &rowsRef(string $table): array {
                        if ('wp_favorites' === $table) return $this->favorites;
                        return $this->watch;
                    }
                    public function get_var($sql) {
                        if ($this->fail_reads) { $this->last_error = 'simulated read failure'; return null; }
                        $this->last_error = '';
                        $table = str_contains($sql, 'wp_favorites') ? 'wp_favorites' : 'wp_watch';
                        $rows = $this->rowsFor($table);
                        return $rows ? max(array_keys($rows)) : 0;
                    }
                    public function get_results($sql, $format = null) {
                        if ($this->fail_reads) { $this->last_error = 'simulated read failure'; return null; }
                        $this->last_error = '';
                        preg_match('/FROM (wp_(?:favorites|watch)).*id > (\\d+) AND id <= (\\d+).*LIMIT (\\d+)/s', $sql, $m);
                        [$all, $table, $cursor, $max, $limit] = $m;
                        $out = [];
                        foreach ($this->rowsFor($table) as $row) {
                            if ($row['id'] <= (int) $cursor || $row['id'] > (int) $max) continue;
                            $out[] = [
                                'id'=>$row['id'], 'video_id'=>$row['video_id'],
                                'event_at'=>$row['created_at'] ?? $row['watched_at'],
                                'progress_seconds'=>$row['progress_seconds'] ?? null,
                                'completed'=>$row['completed'] ?? null,
                            ];
                            if (count($out) >= (int) $limit) break;
                        }
                        return $out;
                    }
                    public function get_col($sql) {
                        if ($this->fail_reads) { $this->last_error = 'simulated read failure'; return null; }
                        $this->last_error = '';
                        preg_match('/FROM (wp_(?:favorites|watch)).*LIMIT (\\d+)/s', $sql, $m);
                        return array_slice(array_keys($this->rowsFor($m[1])), 0, (int) $m[2]);
                    }
                    public function delete($table, $where, $formats) {
                        $rows =& $this->rowsRef($table);
                        if (!isset($rows[$where['id']])) return 0;
                        unset($rows[$where['id']]); return 1;
                    }
                }
                $wpdb = new WPDBStub();
            }
            namespace Seoflix {
                final class DB_Schema {
                    public static function table_favorites(): string { return 'wp_favorites'; }
                    public static function table_watch(): string { return 'wp_watch'; }
                }
            }
            namespace {
                require __SOURCE__;
                $_POST['id'] = '101';
                $first_a = \\Seoflix\\User_Accounts::personal_data_exporter('a@example.test', 1);
                $wpdb->favorites[300] = ['id'=>300,'video_id'=>999,'created_at'=>'2026-01-03'];
                unset($wpdb->favorites[1]);

                $_POST['id'] = '202';
                $first_b = \\Seoflix\\User_Accounts::personal_data_exporter('a@example.test', 1);
                $GLOBALS['sx_lookup_mode'] = 'missing'; // l’e-mail du compte dérive entre les pages.
                $_POST['id'] = '101';
                $second_a = \\Seoflix\\User_Accounts::personal_data_exporter('a@example.test', 2);
                $_POST['id'] = '202';
                $second_b = \\Seoflix\\User_Accounts::personal_data_exporter('a@example.test', 2);
                $_POST['id'] = '101';
                $third_a = \\Seoflix\\User_Accounts::personal_data_exporter('a@example.test', 3);
                $_POST['id'] = '202';
                $third_b = \\Seoflix\\User_Accounts::personal_data_exporter('a@example.test', 3);

                $ids_a = array_merge(array_column($first_a['data'], 'item_id'), array_column($second_a['data'], 'item_id'), array_column($third_a['data'], 'item_id'));
                $ids_b = array_merge(array_column($first_b['data'], 'item_id'), array_column($second_b['data'], 'item_id'), array_column($third_b['data'], 'item_id'));

                $wpdb->favorites = [];
                for ($i = 1; $i <= 150; $i++) $wpdb->favorites[$i] = ['id'=>$i,'video_id'=>$i,'created_at'=>'2026-02-01'];
                $wpdb->watch = [];
                $GLOBALS['sx_lookup_mode'] = 'found';
                $_POST['id'] = '606';
                $erase_drift_first = \\Seoflix\\User_Accounts::personal_data_eraser('a@example.test', 1);
                $GLOBALS['sx_lookup_mode'] = 'missing';
                $erase_drift_second = \\Seoflix\\User_Accounts::personal_data_eraser('a@example.test', 2);
                $erase_drift_remaining = count($wpdb->favorites);

                $GLOBALS['sx_lookup_mode'] = 'found';
                $wpdb->fail_reads = true;
                $_POST['id'] = '303';
                $export_error = \\Seoflix\\User_Accounts::personal_data_exporter('a@example.test', 1);
                $erase_error = \\Seoflix\\User_Accounts::personal_data_eraser('a@example.test', 1);
                $wpdb->fail_reads = false;

                $GLOBALS['sx_lookup_mode'] = 'error';
                $_POST['id'] = '404';
                $lookup_export_error = \\Seoflix\\User_Accounts::personal_data_exporter('a@example.test', 1);
                $lookup_erase_error = \\Seoflix\\User_Accounts::personal_data_eraser('a@example.test', 1);

                $calls_before_invalid_id = $GLOBALS['sx_lookup_calls'];
                unset($_POST['id']);
                $invalid_id = \\Seoflix\\User_Accounts::personal_data_exporter('missing@example.test', 1);
                $invalid_id_skipped_lookup = $calls_before_invalid_id === $GLOBALS['sx_lookup_calls'];

                $GLOBALS['sx_lookup_mode'] = 'missing';
                $_POST['id'] = '505';
                $missing_export = \\Seoflix\\User_Accounts::personal_data_exporter('missing@example.test', 1);
                $missing_eraser = \\Seoflix\\User_Accounts::personal_data_eraser('missing@example.test', 1);
                echo json_encode([
                    'a_counts'=>[count($first_a['data']), count($second_a['data']), count($third_a['data']), count($ids_a), count(array_unique($ids_a))],
                    'b_counts'=>[count($first_b['data']), count($second_b['data']), count($third_b['data']), count($ids_b), count(array_unique($ids_b))],
                    'done'=>[$first_a['done'], $second_a['done'], $third_a['done'], $first_b['done'], $second_b['done'], $third_b['done']],
                    'a_new_row_excluded'=>!in_array('favorite-300', $ids_a, true),
                    'b_new_row_included'=>in_array('favorite-300', $ids_b, true),
                    'a_original_101_present'=>in_array('favorite-101', $ids_a, true),
                    'erase_email_drift'=>[
                        $erase_drift_first['removed_count'], $erase_drift_first['done'],
                        $erase_drift_second['removed_count'], $erase_drift_second['done'],
                        $erase_drift_remaining,
                    ],
                    'export_error_done'=>$export_error['done'],
                    'erase_error'=>[$erase_error['items_retained'], $erase_error['done']],
                    'lookup_export_error_done'=>$lookup_export_error['done'],
                    'lookup_erase_error'=>[$lookup_erase_error['items_retained'], $lookup_erase_error['done']],
                    'invalid_id'=>[$invalid_id['done'], $invalid_id_skipped_lookup],
                    'missing_export_done'=>$missing_export['done'],
                    'missing_eraser'=>[$missing_eraser['items_retained'], $missing_eraser['done']],
                ]);
            }
            """
        ).lstrip().replace("__SOURCE__", source_path)
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(
            {
                "a_counts": [100, 100, 50, 250, 250],
                "b_counts": [100, 100, 50, 250, 250],
                "done": [False, False, True, False, False, True],
                "a_new_row_excluded": True,
                "b_new_row_included": True,
                "a_original_101_present": True,
                "erase_email_drift": [100, False, 50, True, 0],
                "export_error_done": False,
                "erase_error": [True, False],
                "lookup_export_error_done": False,
                "lookup_erase_error": [True, False],
                "invalid_id": [False, True],
                "missing_export_done": True,
                "missing_eraser": [False, True],
            },
            json.loads(result.stdout),
        )

    def test_native_privacy_exporter_and_eraser_cover_favorites_and_history(self) -> None:
        php = compact(source(USER_ACCOUNTS))
        for token in (
            "wp_privacy_personal_data_exporters",
            "wp_privacy_personal_data_erasers",
            "personal_data_exporter",
            "personal_data_eraser",
            "DB_Schema::table_favorites()",
            "DB_Schema::table_watch()",
            "get_user_by( 'email', $email_address )",
            "'items_removed'",
            "'items_retained'",
            "'done'",
        ):
            self.assertIn(token, php)
        self.assertRegex(php, re.compile(r"PRIVACY_BATCH\s*=\s*(?:50|100)"))
        self.assertIn("LIMIT %d", php)
        eraser = php[php.index("function personal_data_eraser") : php.index("function register_rewrites")]
        self.assertNotIn("OFFSET %d", eraser)

    def test_custom_rgpd_delete_is_disclosed_bounded_and_checked(self) -> None:
        php = compact(source(ADMIN_RGPD))
        self.assertIn("favoris et historique vidéo", php.lower())
        self.assertIn("erase_user_activity_batch", php)
        self.assertIn("removed_count", php)
        self.assertIn("rgpd_delete_failed", php)
        handler = php[php.index("function handle_delete") : php.index("function handle_export")]
        self.assertNotIn("User_Accounts::delete_user_data", handler)
        self.assertIn("'done'", handler)
        self.assertIn("'items_retained'", handler)
        self.assertRegex(php, re.compile(r"ADMIN_DELETE_BATCH\s*=\s*(?:50|100)"))
        self.assertGreaterEqual(handler.count("LIMIT %d"), 2)
        self.assertIn("rgpd_more", handler)

    def test_custom_rgpd_export_and_delete_include_account_tables(self) -> None:
        php = compact(source(ADMIN_RGPD))
        self.assertIn("DB_Schema::table_favorites()", php)
        self.assertIn("DB_Schema::table_watch()", php)
        self.assertIn("favorites", php)
        self.assertIn("watch_history", php)
        self.assertIn("deleted_account_rows", php)

    def test_admin_rgpd_search_closes_on_user_lookup_sql_error(self) -> None:
        source_path = json.dumps(str(REPO_ROOT / ADMIN_RGPD))
        harness = textwrap.dedent(
            """
            <?php
            namespace {
                define('ABSPATH', __DIR__);
                define('ARRAY_A', 'ARRAY_A');
                function wp_salt() { return 'salt'; }
                function get_user_by($field, $email) { global $wpdb; $wpdb->last_error = 'simulated lookup SQL error'; return false; }
                function wp_die($message, $title = '', $args = []) { throw new RuntimeException((string) $message); }
                function esc_html($value) { return (string) $value; }
                function esc_attr($value) { return (string) $value; }
                function esc_url($value) { return (string) $value; }
                function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
                function wp_nonce_field($nonce) {}
                class SearchLookupWPDBStub {
                    public string $last_error = '';
                    public string $comments = 'wp_comments';
                    public function prepare($sql, ...$args) { return $sql; }
                    public function get_results($sql, $format = null) { $this->last_error = ''; return []; }
                }
                $wpdb = new SearchLookupWPDBStub();
            }
            namespace Seoflix {}
            namespace {
                require __SOURCE__;
                $method = new ReflectionMethod('Seoflix\\Admin\\Admin_Rgpd', 'render_results');
                $method->setAccessible(true);
                ob_start();
                try { $method->invoke(null, 'person@example.test', ''); $closed = false; }
                catch (Throwable $e) { $closed = true; }
                $output = ob_get_clean();
                echo json_encode(['closed'=>$closed, 'reported_no_account'=>str_contains($output, 'Aucun compte trouvé')]);
            }
            """
        ).lstrip().replace("__SOURCE__", source_path)
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual({"closed": True, "reported_no_account": False}, json.loads(result.stdout))

    def test_admin_delete_closes_when_comment_lookup_has_sql_error(self) -> None:
        source_path = json.dumps(str(REPO_ROOT / ADMIN_RGPD))
        harness = textwrap.dedent(
            """
            <?php
            namespace {
                define('ABSPATH', __DIR__);
                function current_user_can($cap) { return true; }
                function check_admin_referer($nonce) { return true; }
                function sanitize_email($email) { return (string) $email; }
                function wp_unslash($value) { return $value; }
                function wp_salt() { return 'salt'; }
                function get_comment($id) { global $wpdb; $wpdb->last_error = 'simulated get_comment SQL error'; return false; }
                function wp_delete_comment($id, $force) { return true; }
                function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
                function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
                function wp_safe_redirect($url) { echo $url; exit(0); }
                function wp_die($message, $title = '', $args = []) { echo 'WP_DIE'; exit(75); }
                class DeleteLookupWPDBStub {
                    public string $last_error = '';
                    public string $comments = 'wp_comments';
                    public function prepare($sql, ...$args) { return $sql; }
                    public function get_col($sql) { $this->last_error = ''; return [5]; }
                    public function query($sql) { $this->last_error = ''; return 0; }
                }
                $wpdb = new DeleteLookupWPDBStub();
                $_POST = ['email'=>'', 'ip'=>'203.0.113.10'];
            }
            namespace Seoflix {
                final class DB_Schema { public static function table_affiliate_clicks(): string { return 'wp_clicks'; } }
                final class Video_Comments { public const COMMENT_TYPE = 'seoflix_video_discussion'; public static function erase_comment($comment): bool { return true; } }
            }
            namespace {
                require __SOURCE__;
                \\Seoflix\\Admin\\Admin_Rgpd::handle_delete();
            }
            """
        ).lstrip().replace("__SOURCE__", source_path)
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn("rgpd_delete_failed=1", result.stdout)
        self.assertNotIn("rgpd_delete_failed=0", result.stdout)

    def test_admin_json_export_is_bounded_and_fails_closed_in_php(self) -> None:
        php = compact(source(ADMIN_RGPD))
        handler = php[php.index("function handle_export") :]
        self.assertIn("function bounded_export_rows", php)
        helper = php[php.index("function bounded_export_rows") : php.index("function handle_export")]
        self.assertRegex(php, re.compile(r"ADMIN_EXPORT_LIMIT\s*=\s*(?:500|1000)"))
        self.assertGreaterEqual(handler.count("self::bounded_export_rows"), 4)
        self.assertNotIn("$wpdb->get_results", handler)
        self.assertIn("LIMIT %d", helper)
        self.assertIn("$wpdb->last_error", helper)
        self.assertIn("wp_die", helper)

        source_path = json.dumps(str(REPO_ROOT / ADMIN_RGPD))
        harness = textwrap.dedent(
            """
            <?php
            namespace {
                define('ABSPATH', __DIR__);
                define('ARRAY_A', 'ARRAY_A');
                function wp_die($message, $title = '', $args = []) { throw new RuntimeException((string) $message); }
                class AdminExportWPDBStub {
                    public string $last_error = '';
                    public string $mode = 'success';
                    public function prepare($sql, ...$args) {
                        foreach ($args as $arg) $sql = preg_replace('/%d/', (string) (int) $arg, $sql, 1);
                        return $sql;
                    }
                    public function get_results($sql, $format = null) {
                        if ('error' === $this->mode) { $this->last_error = 'simulated SQL error'; return null; }
                        $this->last_error = '';
                        preg_match('/LIMIT (\\d+)/', $sql, $match);
                        $limit = (int) ($match[1] ?? 0);
                        if ('overflow' === $this->mode) return array_fill(0, $limit, ['id'=>1]);
                        return [['id'=>1], ['id'=>2]];
                    }
                }
                $wpdb = new AdminExportWPDBStub();
            }
            namespace Seoflix {}
            namespace {
                require __SOURCE__;
                $method = new ReflectionMethod('Seoflix\\Admin\\Admin_Rgpd', 'bounded_export_rows');
                $method->setAccessible(true);
                $success = $method->invoke(null, 'SELECT * FROM table WHERE user_id = %d ORDER BY id ASC', [7]);
                $wpdb->mode = 'error';
                try { $method->invoke(null, 'SELECT * FROM table ORDER BY id ASC', []); $error_closed = false; }
                catch (Throwable $e) { $error_closed = true; }
                $wpdb->mode = 'overflow';
                try { $method->invoke(null, 'SELECT * FROM table ORDER BY id ASC', []); $overflow_closed = false; }
                catch (Throwable $e) { $overflow_closed = true; }
                echo json_encode(['success_count'=>count($success), 'error_closed'=>$error_closed, 'overflow_closed'=>$overflow_closed]);
            }
            """
        ).lstrip().replace("__SOURCE__", source_path)
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(
            {"success_count": 2, "error_closed": True, "overflow_closed": True},
            json.loads(result.stdout),
        )

    def test_admin_json_export_closes_on_user_lookup_sql_error(self) -> None:
        source_path = json.dumps(str(REPO_ROOT / ADMIN_RGPD))
        harness = textwrap.dedent(
            """
            <?php
            namespace {
                define('ABSPATH', __DIR__);
                define('ARRAY_A', 'ARRAY_A');
                function current_user_can($cap) { return true; }
                function check_admin_referer($nonce) { return true; }
                function sanitize_email($email) { return (string) $email; }
                function wp_unslash($value) { return $value; }
                function home_url() { return 'https://example.test/'; }
                function get_user_by($field, $email) { global $wpdb; $wpdb->last_error = 'simulated lookup SQL error'; return false; }
                function wp_die($message, $title = '', $args = []) { echo 'LOOKUP_CLOSED'; exit(73); }
                function nocache_headers() {}
                function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
                class LookupErrorWPDBStub {
                    public string $last_error = '';
                    public string $comments = 'wp_comments';
                    public string $usermeta = 'wp_usermeta';
                    public function prepare($sql, ...$args) {
                        foreach ($args as $arg) $sql = preg_replace('/%s/', "'" . addslashes((string) $arg) . "'", $sql, 1);
                        foreach ($args as $arg) $sql = preg_replace('/%d/', (string) (int) $arg, $sql, 1);
                        return $sql;
                    }
                    public function get_results($sql, $format = null) { $this->last_error = ''; return []; }
                }
                $wpdb = new LookupErrorWPDBStub();
                $_POST = ['email' => 'person@example.test', 'ip' => ''];
            }
            namespace Seoflix {}
            namespace {
                require __SOURCE__;
                \\Seoflix\\Admin\\Admin_Rgpd::handle_export();
            }
            """
        ).lstrip().replace("__SOURCE__", source_path)
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(73, result.returncode, result.stderr)
        self.assertEqual("LOOKUP_CLOSED", result.stdout)
        self.assertNotIn('"export_date"', result.stdout)

    def test_admin_json_export_closes_on_encoding_error_before_headers(self) -> None:
        source_path = json.dumps(str(REPO_ROOT / ADMIN_RGPD))
        harness = textwrap.dedent(
            """
            <?php
            namespace {
                define('ABSPATH', __DIR__);
                define('ARRAY_A', 'ARRAY_A');
                function current_user_can($cap) { return true; }
                function check_admin_referer($nonce) { return true; }
                function sanitize_email($email) { return (string) $email; }
                function wp_unslash($value) { return $value; }
                function home_url() { return 'https://example.test/'; }
                function wp_json_encode($value, $flags = 0) { return false; }
                function nocache_headers() { echo 'HEADER_STARTED'; }
                function wp_die($message, $title = '', $args = []) { echo 'ENCODE_CLOSED'; exit(74); }
                class EncodingErrorWPDBStub {
                    public string $last_error = '';
                    public string $comments = 'wp_comments';
                    public string $usermeta = 'wp_usermeta';
                }
                $wpdb = new EncodingErrorWPDBStub();
                $_POST = ['email' => '', 'ip' => ''];
            }
            namespace Seoflix {}
            namespace {
                require __SOURCE__;
                \\Seoflix\\Admin\\Admin_Rgpd::handle_export();
            }
            """
        ).lstrip().replace("__SOURCE__", source_path)
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(74, result.returncode, result.stderr)
        self.assertEqual("ENCODE_CLOSED", result.stdout)
        self.assertNotIn("HEADER_STARTED", result.stdout)

    def test_registration_sends_one_minimal_admin_notification_without_ip(self) -> None:
        php = source(CUSTOM_AUTH)
        register = php[php.index("function handle_register") : php.index("function handle_activate")]
        self.assertEqual(register.count("wp_mail( $admin_to"), 1)
        admin_block = register[register.index("$admin_to") : register.index("wp_mail( $admin_to") + 120]
        self.assertNotIn("$ip", admin_block)
        self.assertNotIn("$user_email", admin_block)
        self.assertIn("$user_id", admin_block)
        self.assertNotIn("md5( $ip )", register)
        self.assertIn("wp_hash( $ip", register)

    def test_affiliate_tracking_is_minimized_and_has_24_month_purge(self) -> None:
        php = compact(source(AFFILIATE))
        self.assertRegex(php, re.compile(r"RETENTION_DAYS\s*=\s*730"))
        self.assertIn("seoflix_purge_affiliate_clicks", php)
        self.assertIn("wp_schedule_event", php)
        self.assertIn("purge_expired_clicks", php)
        self.assertIn("DATE_SUB", php)
        self.assertIn("LIMIT", php)
        self.assertIn("wp_schedule_single_event", php)
        self.assertIn("'catchup'", php)
        self.assertIn("current_time( 'mysql', true )", php)
        self.assertIn("UTC_TIMESTAMP()", php)
        log = php[php.index("function log_click") : php.index("function register_metabox")]
        self.assertIn("'user_agent' => null", log)
        self.assertIn("'referer' => null", log)
        self.assertIn("'source_page' => null", log)
        self.assertNotIn("HTTP_USER_AGENT", log)
        self.assertNotIn("HTTP_REFERER", log)

    def test_affiliate_catchup_is_cleared_on_deactivation(self) -> None:
        deactivator = compact(source("plugin/seoflix-core/includes/class-deactivator.php"))
        self.assertIn("wp_clear_scheduled_hook( 'seoflix_purge_affiliate_clicks' )", deactivator)
        self.assertIn("wp_clear_scheduled_hook( 'seoflix_purge_affiliate_clicks', [ 'catchup' ] )", deactivator)

    def test_legal_retention_matches_implemented_affiliate_purge(self) -> None:
        legal = source(LEGAL)
        self.assertIn("Clics affiliés (hashés)</strong> : 24 mois", legal)


if __name__ == "__main__":
    unittest.main()
