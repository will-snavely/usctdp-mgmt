<?php

use BerlinDB\Database\Query;

if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Mgmt_Ledger_Query extends Query
{
    protected $table_name = 'usctdp_ledger';
    protected $table_alias = 'ulgr';
    protected $table_schema = 'Usctdp_Mgmt_Ledger_Schema';
    protected $item_name = 'ledger';
    protected $item_name_plural = 'ledger';
    protected $item_shape = 'Usctdp_Mgmt_Ledger_Row';

    public function get_ledger_data($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["id"])) {
            $conditions[] = "ulgr.id = %d";
            $where_args[] = $args['id'];
        }
        if (isset($args["family_id"])) {
            $conditions[] = "ulgr.family_id = %d";
            $where_args[] = $args['family_id'];
        }
        if (isset($args["event_id"])) {
            $conditions[] = "ulgr.event_id = %d";
            $where_args[] = $args['event_id'];
        }
        if (isset($args["account"])) {
            $conditions[] = "ulgr.account = %s";
            $where_args[] = $args['account'];
        }
        if (isset($args["order_id"])) {
            $conditions[] = "ulgr.order_id = %d";
            $where_args[] = $args['order_id'];
        }
        if (isset($args["purchase_id"])) {
            $conditions[] = "ulgr.purchase_id = %d";
            $where_args[] = $args['purchase_id'];
        }
        if ($conditions) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }

        $limit_clause = '';
        $limit_args = [];
        if (isset($args["number"])) {
            $limit_clause = "LIMIT %d";
            $limit_args[] = $args['number'];
        }
        if (isset($args["offset"])) {
            $limit_clause .= " OFFSET %d";
            $limit_args[] = $args['offset'];
        }
        $query = $wpdb->prepare(
            "   SELECT *
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                {$where_clause}
                ORDER BY ulgr.id DESC
                {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_sql = "SELECT COUNT(*) as count
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                {$where_clause}";
        $count_query = $count_sql;
        if (!empty($where_args)) {
            $count_query = $wpdb->prepare($count_sql, $where_args);
        }
        $count = $wpdb->get_var($count_query);

        return [
            'data' => $window,
            'count' => $count
        ];
    }
    public function get_ledger_events($args)
    {
        global $wpdb;

        $where_clause = '';
        $where_args = [];
        $conditions = [];
        if (isset($args["family_id"])) {
            $conditions[] = "family_id = %d";
            $where_args[] = $args['family_id'];
        }
        if (isset($args["purchase_id"])) {
            $conditions[] = "purchase_id = %d";
            $where_args[] = $args['purchase_id'];
        }
        if (isset($args["account"])) {
            $conditions[] = "account = %s";
            $where_args[] = $args['account'];
        }
        if ($conditions) {
            $where_clause = "WHERE " . implode(" AND ", $conditions);
        }

        $limit_clause = '';
        $limit_args = [];
        if (isset($args["number"]) && $args["number"] > 0) {
            $limit_clause = "LIMIT %d";
            $limit_args[] = $args['number'];

            if (isset($args["offset"])) {
                $limit_clause .= " OFFSET %d";
                $limit_args[] = $args['offset'];
            }
        }

        $query = $wpdb->prepare(
            "SELECT
                id,
                event_id,
                order_id,
                DATE_FORMAT(created_at, '%%Y-%%m-%%dT%%T.%%fZ') as event_date,
                entry_type,
                description as event_description,
                debit as charge_amount,
                credit as payment_amount
            FROM {$wpdb->prefix}usctdp_ledger
            {$where_clause}
            ORDER BY created_at, id ASC
            {$limit_clause}",
            array_merge($where_args, $limit_args)
        );
        $window = $wpdb->get_results($query);

        $count_sql =
            "SELECT count(id)
            FROM {$wpdb->prefix}usctdp_ledger
            {$where_clause}";
        $count_query = $count_sql;
        if (!empty($where_args)) {
            $count_query = $wpdb->prepare($count_sql, $where_args);
        }
        $count = $wpdb->get_var($count_query);

        return [
            'data' => $window,
            'count' => $count
        ];
    }

    /**
     * Gross revenue + accounts receivable grouped by session, for the
     * Earnings dashboard (usctdp-mgmt-admin-earnings.php). Joins
     * purchase -> registration -> activity -> session the same way
     * Usctdp_Mgmt_Purchase_Query::get_purchase_data() does; purchases with
     * no registration (e.g. merchandise) fall out as session_id NULL, which
     * the caller renders as an "Other / Unassigned" row rather than
     * dropping.
     *
     * gross_revenue mirrors the ledger's own 'revenue' account (credited
     * once per charge - see build_ledger_entries_for_line_item() in
     * class-usctdp-mgmt-woocommerce-hooks.php). receivable reuses the exact
     * formula get_family_balance() (class-usctdp-mgmt-admin-ajax.php) uses,
     * just grouped by session instead of family.
     *
     * $args['date_from']/$args['date_to'] are already-converted UTC
     * datetime strings (see Usctdp_Mgmt_Admin_Ajax::eastern_date_range_to_utc()) -
     * this method does no timezone handling of its own.
     */
    public function get_session_earnings($args)
    {
        global $wpdb;

        $conditions = ["pur.status = %s", "pur.type != %s"];
        $where_args = ['active', 'credit_import'];

        if (isset($args['date_from'])) {
            $conditions[] = "pur.created_at >= %s";
            $where_args[] = $args['date_from'];
        }
        if (isset($args['date_to'])) {
            $conditions[] = "pur.created_at < %s";
            $where_args[] = $args['date_to'];
        }
        $where_clause = "WHERE " . implode(" AND ", $conditions);

        $query = $wpdb->prepare(
            "   SELECT
                    sesh.id AS session_id,
                    sesh.title AS session_title,
                    sesh.start_date AS session_start_date,
                    sesh.end_date AS session_end_date,
                    SUM(CASE WHEN ulgr.account = 'revenue' THEN ulgr.credit - ulgr.debit ELSE 0 END) AS gross_revenue,
                    SUM(CASE WHEN ulgr.account IN ('registration_fees', 'merchandise_fees')
                             THEN ulgr.debit - ulgr.credit ELSE 0 END) AS receivable
                FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                JOIN {$wpdb->prefix}usctdp_purchase AS pur ON pur.id = ulgr.purchase_id
                LEFT JOIN {$wpdb->prefix}usctdp_registration AS reg ON reg.purchase_id = pur.id
                LEFT JOIN {$wpdb->prefix}usctdp_activity AS act ON act.id = reg.activity_id
                LEFT JOIN {$wpdb->prefix}usctdp_session AS sesh ON sesh.id = act.session_id
                {$where_clause}
                GROUP BY sesh.id
                ORDER BY sesh.start_date DESC",
            $where_args
        );
        return $wpdb->get_results($query);
    }

    /**
     * Total PayPal transaction fees for the orders behind the in-range
     * 'revenue' entries get_session_earnings() would sum. PayPal fees never
     * land in the ledger (WooCommerce/PayPal computes them independently of
     * this plugin) - the WooCommerce PayPal Payments plugin instead writes
     * them to order meta 'PayPal Transaction Fee' per order (see
     * FeesUpdater::update() in
     * woocommerce-paypal-payments/modules/ppcp-wc-gateway/src/Helper/FeesUpdater.php).
     * Deliberately a single total, not allocated per-session - a single
     * order can span multiple sessions/products, and prorating a per-order
     * fee across its line items would be a guess, not a real number.
     */
    public function get_paypal_fees_total($args)
    {
        global $wpdb;

        $conditions = [
            "ulgr.account = %s",
            "pur.status = %s",
            "pur.type != %s",
            "ulgr.order_id > 0",
        ];
        $where_args = ['revenue', 'active', 'credit_import'];

        if (isset($args['date_from'])) {
            $conditions[] = "pur.created_at >= %s";
            $where_args[] = $args['date_from'];
        }
        if (isset($args['date_to'])) {
            $conditions[] = "pur.created_at < %s";
            $where_args[] = $args['date_to'];
        }
        $where_clause = "WHERE " . implode(" AND ", $conditions);

        $query = $wpdb->prepare(
            "   SELECT COALESCE(SUM(pm.meta_value + 0), 0)
                FROM (
                    SELECT DISTINCT ulgr.order_id
                    FROM {$wpdb->prefix}usctdp_ledger AS ulgr
                    JOIN {$wpdb->prefix}usctdp_purchase AS pur ON pur.id = ulgr.purchase_id
                    {$where_clause}
                ) AS o
                JOIN {$wpdb->postmeta} AS pm ON pm.post_id = o.order_id AND pm.meta_key = 'PayPal Transaction Fee'",
            $where_args
        );
        return (float) $wpdb->get_var($query);
    }
}
