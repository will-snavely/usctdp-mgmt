<?php

/**
 * Generates random registrations against real activities/students, creating
 * the full usctdp_purchase + usctdp_registration + usctdp_ledger record set
 * that ajax_submit_payment() would produce for a real "create" order - so
 * generated data exercises balances, ledger reports, etc. the same way real
 * data does.
 */
class Usctdp_Random_Registration_Generator
{
    private $student_count;
    private $activity_count;

    /** @var array<int, array{roster: int[], capacity: int}> Keyed by activity_id. */
    private $enrolled = [];

    /** @var array<int, true> Activity IDs we've already warned about missing pricing for. */
    private $warned_missing_pricing = [];

    public function __construct()
    {
        global $wpdb;
        $this->student_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}usctdp_student");
        $this->activity_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}usctdp_activity");
    }

    private function random_student()
    {
        global $wpdb;
        $offset = rand(0, $this->student_count - 1);
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}usctdp_student LIMIT 1 OFFSET $offset");
    }

    private function random_activity()
    {
        global $wpdb;
        $offset = rand(0, $this->activity_count - 1);
        return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}usctdp_activity LIMIT 1 OFFSET $offset");
    }

    /**
     * Lazily loads an activity's roster (capacity + currently enrolled
     * student ids, including registrations that already existed before this
     * run) so repeated calls don't keep hitting the database.
     */
    private function get_roster($activity)
    {
        $activity_id = (int) $activity->id;
        if (!isset($this->enrolled[$activity_id])) {
            global $wpdb;
            $existing = $wpdb->get_col($wpdb->prepare(
                "SELECT student_id FROM {$wpdb->prefix}usctdp_registration
                    WHERE activity_id = %d AND status = 'active'",
                $activity_id
            ));
            $this->enrolled[$activity_id] = [
                'roster' => array_map('intval', $existing),
                'capacity' => $activity->capacity !== null ? (int) $activity->capacity : PHP_INT_MAX,
            ];
        }
        return $this->enrolled[$activity_id];
    }

    private function get_base_price($activity)
    {
        $pricing = Usctdp_Mgmt_Model::get_activity_pricing($activity);
        if (empty($pricing) || empty($pricing->pricing)) {
            return null;
        }
        $prices = $pricing->pricing;
        if ($activity->type === 'tournament') {
            return isset($prices['base']) ? round(floatval($prices['base']), 2) : null;
        }
        return isset($prices['One']) ? round(floatval($prices['One']), 2) : null;
    }

    private function create_purchase_and_registration($activity, $student)
    {
        $created_by = get_current_user_id();
        $created_at = current_time('mysql');

        $purchase_query = new Usctdp_Mgmt_Purchase_Query();
        $purchase_id = $purchase_query->add_item([
            'product_id' => $activity->product_id,
            'family_id' => $student->family_id,
            'student_id' => $student->id,
            'type' => 'registration',
            'created_at' => $created_at,
            'created_by' => $created_by,
            'discounts' => '[]',
        ]);
        if (!$purchase_id) {
            throw new Exception('Failed to create purchase.');
        }

        $registration_query = new Usctdp_Mgmt_Registration_Query();
        $registration_id = $registration_query->add_item([
            'purchase_id' => $purchase_id,
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'student_level' => $student->level,
            'status' => 'active',
            'notes' => '',
            'created_at' => $created_at,
            'created_by' => $created_by,
            'modified_at' => $created_at,
            'modified_by' => $created_by,
        ]);
        if (!$registration_id) {
            throw new Exception('Failed to create registration.');
        }

        return [
            'purchase_id' => $purchase_id,
            'registration_id' => $registration_id,
        ];
    }

    private function create_ledger_entry($args)
    {
        $ledger_query = new Usctdp_Mgmt_Ledger_Query();
        $ledger_id = $ledger_query->add_item($args);
        if (!$ledger_id) {
            throw new Exception('Failed to create ledger entry.');
        }
        return $ledger_id;
    }

    /**
     * Writes the double-entry ledger rows for one generated registration:
     * always a charge (registration_fees debit / revenue credit), and - if
     * this registration is "paid" - a matching payment pair. Mirrors
     * Usctdp_Mgmt_Admin_Ajax::build_ledger_entries_for_line_item().
     */
    private function create_ledger_entries_for_registration($purchase_id, $student, $base_price, $is_paid)
    {
        $created_by = get_current_user_id();
        $created_at = current_time('mysql');
        $zero = '0.00';
        $base_price_str = number_format($base_price, 2, '.', '');

        $event_id = 'gen_registration_' . $purchase_id;
        $event = 'Generated Test Registration';

        $base = [
            'family_id' => $student->family_id,
            'student_id' => $student->id,
            'purchase_id' => $purchase_id,
            'event_id' => $event_id,
            'event' => $event,
            'created_by' => $created_by,
            'created_at' => $created_at,
        ];

        $this->create_ledger_entry(array_merge($base, [
            'account' => 'registration_fees',
            'debit' => $base_price_str,
            'credit' => $zero,
            'entry_type' => 'charge',
            'description' => 'Base Fee',
        ]));
        $this->create_ledger_entry(array_merge($base, [
            'account' => 'revenue',
            'debit' => $zero,
            'credit' => $base_price_str,
            'entry_type' => 'charge',
            'description' => 'Base Fee',
        ]));

        if (!$is_paid) {
            return;
        }

        $payment_method = (rand(0, 1) === 0) ? 'cash' : 'check';
        $check_number = $payment_method === 'check' ? (string) rand(1000, 9999) : null;
        $description = 'Payment (' . ($payment_method === 'check' ? 'Check #' . $check_number : 'Cash') . ')';

        $payment_base = $base;
        $payment_base['payment_method'] = $payment_method;
        if ($check_number !== null) {
            $payment_base['reference_id'] = $check_number;
        }

        $this->create_ledger_entry(array_merge($payment_base, [
            'account' => 'payment_' . $payment_method,
            'debit' => $base_price_str,
            'credit' => $zero,
            'entry_type' => 'payment',
            'description' => $description,
        ]));
        $this->create_ledger_entry(array_merge($payment_base, [
            'account' => 'registration_fees',
            'debit' => $zero,
            'credit' => $base_price_str,
            'entry_type' => 'payment',
            'description' => $description,
        ]));
    }

    private function generate_registrations($count, $chance_unpaid)
    {
        global $wpdb;

        $result = [];
        $created = 0;
        $attempts = 0;
        $max_attempts = max(500, $count * 50);

        while ($created < $count && $attempts < $max_attempts) {
            $attempts++;

            $activity = $this->random_activity();
            $student = $this->random_student();
            if (!$activity || !$student) {
                continue;
            }

            $roster = $this->get_roster($activity);
            if (in_array((int) $student->id, $roster['roster'], true) || count($roster['roster']) >= $roster['capacity']) {
                continue;
            }

            $base_price = $this->get_base_price($activity);
            if ($base_price === null) {
                if (!isset($this->warned_missing_pricing[$activity->id])) {
                    WP_CLI::warning("No pricing found for activity #{$activity->id} ({$activity->title}); using a random fallback price.");
                    $this->warned_missing_pricing[$activity->id] = true;
                }
                $base_price = rand(100, 300);
            }

            $is_paid = rand(1, 100) > $chance_unpaid;

            $wpdb->query('START TRANSACTION');
            try {
                $ids = $this->create_purchase_and_registration($activity, $student);
                $this->create_ledger_entries_for_registration($ids['purchase_id'], $student, $base_price, $is_paid);
                $wpdb->query('COMMIT');
            } catch (Throwable $e) {
                $wpdb->query('ROLLBACK');
                WP_CLI::warning('Failed to generate registration: ' . $e->getMessage());
                continue;
            }

            $this->enrolled[$activity->id]['roster'][] = (int) $student->id;
            $created++;
            $result[] = [
                'registration_id' => $ids['registration_id'],
                'purchase_id' => $ids['purchase_id'],
            ];
        }

        if ($created < $count) {
            WP_CLI::warning("Only generated $created of $count requested registrations (ran out of available student/activity slots).");
        }

        return $result;
    }

    public function generate_random($num_registrations, $chance_unpaid = 0)
    {
        if ($this->student_count === 0) {
            WP_CLI::error('No students found. Generate some people first.');
            return;
        }
        if ($this->activity_count === 0) {
            WP_CLI::error('No activities found. Import sessions/products first.');
            return;
        }

        WP_CLI::log('Generating registrations...');
        $result = $this->generate_registrations($num_registrations, $chance_unpaid);
        WP_CLI::success('Generated ' . count($result) . ' registration(s).');
    }
}
