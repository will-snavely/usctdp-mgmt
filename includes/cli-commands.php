<?php

// Check to ensure WordPress functions are available and prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

class Usctdp_Cli_Command
{
    public function __construct()
    {
        $this->load_dependencies();
    }

    private function load_dependencies()
    {
        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-clean.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-product-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-session-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-build-program-schedule.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-family-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-stage-legacy-families.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-send-legacy-import-invites.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-staff-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-import-page-data.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-random-people-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-random-registration-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-roster-generator.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-seed-roster-groups.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-clean-products.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-meta.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-reconcile-ledger.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-audit-registrations.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-manage-reservation-groups.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-void-stale-registrations.php";

        require_once plugin_dir_path(dirname(__FILE__)) .
            "includes/cli/class-usctdp-merge-matching-clinics.php";
    }

    public function gen_people($args, $assoc_args)
    {
        $generator = new Usctdp_Random_People_Generator();
        $generator->generate_random(10, 20, 8);
    }

    public function gen_registrations($args, $assoc_args)
    {
        $generator = new Usctdp_Random_Registration_Generator();
        $count = 50;
        $chance_unpaid = 0;
        if (isset($args[0])) {
            $count = intval($args[0]);
        }
        if (isset($args[1])) {
            $chance_unpaid = intval($args[1]);
        }
        $generator->generate_random($count, $chance_unpaid);
    }

    public function gen_rosters($args, $assoc_args)
    {
        $generator = new Usctdp_Roster_Generator();
        $generator->create_rosters();
    }

    /**
     * Materializes an explicit roster group (containing just that session)
     * for every session that doesn't already have one. Roster groups are
     * normally created lazily the first time a roster is edited in the
     * admin UI - this pre-seeds prod so every session starts with its own
     * explicit group already in place. Safe to re-run: already-grouped
     * sessions are skipped.
     *
     * ## EXAMPLES
     *
     *     wp usctdp seed_roster_groups
     */
    public function seed_roster_groups($args, $assoc_args)
    {
        $seeder = new Usctdp_Seed_Roster_Groups();
        $seeder->seed();
    }

    public function import_families($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }
        $mock = true;
        if (isset($args[1])) {
            $mock = boolval($args[1]);
        }
        $generator = new Usctdp_Import_Family_Data();
        $generator->import($file_path, $mock);
    }

    /**
     * Stage legacy family/student data (same JSON shape as `import_families`)
     * into usctdp_import_pending for the opt-in import flow, instead of
     * writing directly into usctdp_family/usctdp_student. Matches against
     * existing accounts by email so returning customers get linked to their
     * current account rather than a duplicate placeholder.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the legacy families JSON file.
     *
     * [--dry-run]
     * : Log what would happen without creating accounts or staging rows.
     *
     * ## EXAMPLES
     *
     *     wp usctdp stage_legacy_families data/legacy_families.json --dry-run
     *     wp usctdp stage_legacy_families data/legacy_families.json
     */
    public function stage_legacy_families($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }
        $dry_run = \WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
        $stager = new Usctdp_Stage_Legacy_Families();
        $stager->stage($file_path, $dry_run);
    }

    /**
     * Emails everyone staged by `stage_legacy_families` (and not yet
     * invited or confirmed) a link to review and confirm their data.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Log who would be emailed without sending anything or generating keys.
     *
     * [--resend]
     * : Also re-email families that were already invited (but haven't
     * confirmed yet) - e.g. after their first link expired.
     *
     * [--limit=<number>]
     * : Only send to this many families. Useful for a small test batch
     * before emailing everyone.
     *
     * ## EXAMPLES
     *
     *     wp usctdp send_legacy_import_invites --dry-run
     *     wp usctdp send_legacy_import_invites --limit=5
     *     wp usctdp send_legacy_import_invites
     *     wp usctdp send_legacy_import_invites --resend
     */
    public function send_legacy_import_invites($args, $assoc_args)
    {
        $dry_run = \WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
        $resend = \WP_CLI\Utils\get_flag_value($assoc_args, 'resend', false);
        $limit = \WP_CLI\Utils\get_flag_value($assoc_args, 'limit', null);
        $sender = new Usctdp_Send_Legacy_Import_Invites();
        $sender->send($dry_run, $resend, $limit !== null ? intval($limit) : null);
    }

    public function import_products($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }

        $skip_download = false;
        if ($args && count($args) > 1) {
            if ($args[1] === "true") {
                $skip_download = true;
            }
        }
        $generator = new Usctdp_Import_Product_Data();
        $generator->import($file_path, $skip_download);
    }

    public function import_sessions($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }
        $generator = new Usctdp_Import_Session_Data();
        $generator->import($file_path);
    }

    public function build_schedule($args, $assoc_args)
    {
        $builder = new Usctdp_Build_Program_Schedule();
        $builder->build();
    }

    public function import_staff($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }
        $generator = new Usctdp_Import_Staff_Data();
        $generator->import($file_path);
    }

    /**
     * Create/update WP pages, the primary nav menu, and Contact Form 7
     * forms from a JSON manifest.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the pages JSON file (e.g. data/pages.json).
     *
     * [--dry-run]
     * : Log what would happen without writing anything.
     *
     * ## EXAMPLES
     *
     *     wp usctdp import_pages data/pages.json
     *     wp usctdp import_pages data/pages.json --dry-run
     */
    public function import_pages($args, $assoc_args)
    {
        $file_path = '';
        if ($args && count($args) > 0) {
            $file_path = $args[0];
        } else {
            WP_CLI::error('File path not provided');
            return;
        }
        $dry_run = \WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
        $generator = new Usctdp_Import_Page_Data();
        $generator->import($file_path, $dry_run);
    }

    public function meta($args, $assoc_args)
    {
        if (count($args) < 1) {
            WP_CLI::error('Provide operation: get, set, del');
            return;
        }
        $operation = $args[0];

        if (count($args) < 2) {
            WP_CLI::error('Provide table name');
            return;
        }
        $table = $args[1];
        $meta = new Usctdp_Meta($table);

        switch ($operation) {
            case 'get':
                if (count($args) < 3) {
                    WP_CLI::error('Provide object id.');
                    return;
                }
                $object_id = $args[2];
                $meta_key = null;
                if (count($args) >= 4) {
                    $meta_key = $args[3];
                }
                $meta->get_meta($object_id, $meta_key);
                break;
            case 'set':
                if (count($args) < 3) {
                    WP_CLI::error('Provide object id.');
                    return;
                }
                if (count($args) < 4) {
                    WP_CLI::error('Provide meta key.');
                    return;
                }
                if (count($args) < 5) {
                    WP_CLI::error('Provide meta value.');
                    return;
                }
                $object_id = $args[2];
                $meta_key = $args[3];
                $meta_value = $args[4];
                $meta->set_meta($object_id, $meta_key, $meta_value);
                break;
            case 'del':
                if (count($args) < 2) {
                    WP_CLI::error('Provide object id.');
                    return;
                }
                $object_id = $args[2];
                $meta_key = null;
                if (count($args) >= 3) {
                    $meta_key = $args[3];
                }
                $meta->delete_meta($object_id, $meta_key);
                break;
            default:
                WP_CLI::error('Unknown operation: ' . $operation);
        }
    }

    public function clean($args, $assoc_args)
    {
        $cleaner = new Usctdp_Clean();
        $target = "";
        if ($args && count($args) > 0) {
            $target = $args[0];
        } else {
            WP_CLI::error('Target not provided (one of all, classes, people)');
            return;
        }
        $cleaner->clean($target);
    }

    public function clean_products($args, $assoc_args)
    {
        $cleaner = new Usctdp_Clean_Products();
        $cleaner->clean_products();
    }

    /**
     * Finds two related gaps left by create_purchase_and_ledger_entries()
     * (class-usctdp-mgmt-woocommerce-hooks.php) not completing for an order,
     * and with --fix, backfills both by re-deriving everything from the
     * original WooCommerce order:
     *
     *   - Purchases with no matching usctdp_ledger rows (the null-insert gap
     *     that method used to have).
     *   - 'active' registrations with no purchase at all (purchase_id = 0) -
     *     e.g. the web container getting recreated mid-request between
     *     confirm_registration() and create_purchase_and_ledger_entries().
     *
     * ## OPTIONS
     *
     * [--fix]
     * : Attempt to backfill both kinds of gap. Without this flag, only
     * reports what's found.
     *
     * ## EXAMPLES
     *
     *     wp usctdp reconcile_ledger
     *     wp usctdp reconcile_ledger --fix
     */
    public function reconcile_ledger($args, $assoc_args)
    {
        $fix = \WP_CLI\Utils\get_flag_value($assoc_args, 'fix', false);
        $reconciler = new Usctdp_Reconcile_Ledger();
        $reconciler->reconcile($fix);
    }

    /**
     * Finds 'active' registrations with no evidence of payment (no purchase
     * attached, or a purchase with no ledger rows) - the signature left
     * behind by confirm_registration()'s previously no-op'd order_id filter
     * incorrectly activating registrations tied to a different, unrelated
     * order. Report-only; deciding what to do with each one (void it, chase
     * the payment, leave it) is a judgment call, not something to automate.
     *
     * ## EXAMPLES
     *
     *     wp usctdp audit_registrations
     */
    public function audit_registrations($args, $assoc_args)
    {
        $auditor = new Usctdp_Audit_Registrations();
        $auditor->audit();
    }

    /**
     * Voids 'pending' registrations whose checkout attempt never produced a
     * WooCommerce order - abandoned before submitting, or rejected by some
     * later, unrelated validation step - so they stop permanently blocking
     * that student from re-registering for the same activity. See
     * Usctdp_Void_Stale_Registrations for the full reasoning, including why
     * this is safe (doesn't touch a 'pending' registration that has a real
     * order_id) and why it doesn't affect capacity (already excluded from
     * counts once Usctdp_Mgmt_Woocommerce_Hooks::HOLD_MINUTES elapses).
     *
     * Manual for now, not yet wired to a schedule - see that class's doc
     * comment.
     *
     * ## OPTIONS
     *
     * [--older-than=<minutes>]
     * : How old (by created_at) a still-pending, order-less registration
     * must be before it's considered abandoned. Default: 60.
     *
     * [--fix]
     * : Void what's found. Without this flag, only reports what's found.
     *
     * ## EXAMPLES
     *
     *     wp usctdp release_stale_registrations
     *     wp usctdp release_stale_registrations --fix
     *     wp usctdp release_stale_registrations --older-than=120 --fix
     */
    public function release_stale_registrations($args, $assoc_args)
    {
        $older_than_minutes = (int) \WP_CLI\Utils\get_flag_value($assoc_args, 'older-than', 60);
        $fix = \WP_CLI\Utils\get_flag_value($assoc_args, 'fix', false);
        $releaser = new Usctdp_Void_Stale_Registrations();
        $releaser->release($older_than_minutes, $fix);
    }

    /**
     * Explicitly runs (or resumes) the migration that gives every existing
     * activity its own dedicated usctdp_reservation_group row, instead of
     * waiting for it to fire automatically as a side effect of whichever
     * request happens to hit the site first after deploying a version
     * bump. Safe to run anytime and safe to re-run - see
     * Usctdp_Manage_Reservation_Groups::seed() and
     * Usctdp_Mgmt_Activity_Table::backfill_reservation_groups() for why.
     *
     * ## EXAMPLES
     *
     *     wp usctdp seed_reservation_groups
     */
    public function seed_reservation_groups($args, $assoc_args)
    {
        $manager = new Usctdp_Manage_Reservation_Groups();
        $manager->seed();
        WP_CLI::success('Reservation groups are seeded and up to date.');
    }

    /**
     * Marks two or more activities as sharing one physical space/time slot
     * (e.g. two clinics scheduled on the same court), so their enrollment
     * capacity is checked and enforced as one shared pool instead of
     * independently. Always creates a brand-new reservation group at the
     * given capacity and repoints every listed activity at it; each
     * activity's old group is deleted if nothing else still uses it.
     *
     * ## OPTIONS
     *
     * <activity-id>...
     * : Two or more usctdp_activity ids to merge into one shared group.
     *
     * --capacity=<number>
     * : The shared capacity for the merged group. Always required
     * explicitly - never inferred from the activities being merged, since
     * they may currently disagree.
     *
     * [--name=<name>]
     * : Titles the group's combined roster document. Optional - omit it
     * and the roster falls back to the merged activities' own titles,
     * joined (see Usctdp_Mgmt_Reservation_Group_Query::get_roster_title()).
     * Use `rename_reservation_group` to set/change it later.
     *
     * ## EXAMPLES
     *
     *     wp usctdp merge_reservation_group 42 43 --capacity=20
     *     wp usctdp merge_reservation_group 42 43 --capacity=20 --name="Court 3"
     */
    public function merge_reservation_group($args, $assoc_args)
    {
        if (count($args) < 2) {
            WP_CLI::error('Provide at least two activity ids to merge.');
            return;
        }
        $capacity = \WP_CLI\Utils\get_flag_value($assoc_args, 'capacity', null);
        if ($capacity === null) {
            WP_CLI::error('--capacity is required.');
            return;
        }
        $name = \WP_CLI\Utils\get_flag_value($assoc_args, 'name', null);
        $manager = new Usctdp_Manage_Reservation_Groups();
        $manager->merge($args, $capacity, $name);
    }

    /**
     * Sets (or clears, with an empty string) the title used for a
     * reservation group's combined roster document.
     *
     * ## OPTIONS
     *
     * <group-id>
     * : The usctdp_reservation_group id to rename.
     *
     * <name>
     * : The new name. Pass an empty string ("") to clear it and fall back
     * to the joined member-activity titles again.
     *
     * ## EXAMPLES
     *
     *     wp usctdp rename_reservation_group 7 "Court 3"
     *     wp usctdp rename_reservation_group 7 ""
     */
    public function rename_reservation_group($args, $assoc_args)
    {
        if (count($args) < 2) {
            WP_CLI::error('Provide a reservation group id and a name.');
            return;
        }
        $manager = new Usctdp_Manage_Reservation_Groups();
        $manager->rename($args[0], $args[1]);
    }

    /**
     * Adjusts a reservation group's shared capacity directly, without
     * changing which activities belong to it. Use `merge_reservation_group`
     * instead if you're combining activities for the first time.
     *
     * ## OPTIONS
     *
     * <group-id>
     * : The usctdp_reservation_group id to update.
     *
     * <capacity>
     * : The new capacity.
     *
     * ## EXAMPLES
     *
     *     wp usctdp set_reservation_group_capacity 7 24
     */
    public function set_reservation_group_capacity($args, $assoc_args)
    {
        if (count($args) < 2) {
            WP_CLI::error('Provide a reservation group id and a capacity.');
            return;
        }
        $manager = new Usctdp_Manage_Reservation_Groups();
        $manager->set_capacity($args[0], $args[1]);
    }

    /**
     * Automates building reservation groups for two clinics that share a
     * court - finds every (session, day, start_time, end_time) slot where
     * both clinic products have exactly one activity, and merges each pair
     * into its own new shared group (capacity = the larger of the two) via
     * `merge_reservation_group`'s same underlying logic. See
     * Usctdp_Merge_Matching_Clinics's class doc comment for why "same day
     * and time" and "same day" (as someone might describe two clinics that
     * share a court) collapse to one matching rule here.
     *
     * ## OPTIONS
     *
     * <clinic-a>
     * : Exact product title of the first clinic (e.g. "Yellow Ball").
     *
     * <clinic-b>
     * : Exact product title of the second clinic (e.g. "Yellow Ball Open").
     *
     * [--dry-run]
     * : Print what would be merged without actually merging anything.
     *
     * [--name=<name>]
     * : Titles each merged group's combined roster document (same as
     * `merge_reservation_group`'s --name). If more than one slot matches,
     * every resulting group gets this same name - use
     * `rename_reservation_group` afterward to disambiguate any of them.
     * Omit it and each roster falls back to its own merged activities'
     * titles, joined.
     *
     * ## EXAMPLES
     *
     *     wp usctdp merge_matching_clinics "Yellow Ball" "Yellow Ball Open" --dry-run
     *     wp usctdp merge_matching_clinics "Yellow Ball" "Yellow Ball Open"
     *     wp usctdp merge_matching_clinics "Red Pre-Rally" "Red Ball"
     *     wp usctdp merge_matching_clinics "Yellow Ball" "Yellow Ball Open" --name="Court 3"
     */
    public function merge_matching_clinics($args, $assoc_args)
    {
        if (count($args) < 2) {
            WP_CLI::error('Provide two clinic product titles to match up.');
            return;
        }
        $dry_run = \WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
        $name = \WP_CLI\Utils\get_flag_value($assoc_args, 'name', null);
        $merger = new Usctdp_Merge_Matching_Clinics();
        $merger->merge($args[0], $args[1], $dry_run, $name);
    }
}

// Register the command with WP-CLI
WP_CLI::add_command('usctdp', 'Usctdp_Cli_Command');
