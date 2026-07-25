<?php

/**
 * Emails everyone staged in usctdp_import_pending (by
 * `wp usctdp stage_legacy_families`) a link to review and confirm their
 * data at the "Confirm Import" page. The reset key is generated here, at
 * send time, rather than when the row was staged - staging can happen long
 * before you're ready to email people, and a key's expiration clock starts
 * the moment it's generated.
 */
class Usctdp_Send_Legacy_Import_Invites
{
    const RESET_KEY_TTL = 30 * DAY_IN_SECONDS;

    public function send($dry_run = false, $resend = false, $limit = null)
    {
        $confirm_page = get_page_by_path('confirm-import');
        if (!$confirm_page) {
            WP_CLI::error('Could not find the "confirm-import" page. Run `wp usctdp import_pages data/pages.json` first.');
            return;
        }
        $confirm_url_base = get_permalink($confirm_page->ID);

        $pending_query = new Usctdp_Mgmt_Import_Pending_Query(['number' => false]);
        $sent = 0;
        $skipped = 0;

        foreach ($pending_query->items as $pending) {
            if (!empty($pending->confirmed_at)) {
                continue;
            }
            if (!empty($pending->invited_at) && !$resend) {
                continue;
            }
            if ($limit !== null && $sent >= $limit) {
                break;
            }

            $user = get_userdata($pending->user_id);
            if (!$user) {
                WP_CLI::warning(sprintf('Skipping pending import #%d: linked user #%d no longer exists.', $pending->id, $pending->user_id));
                $skipped++;
                continue;
            }

            $email = $pending->emails[0] ?? $user->user_email;
            if (empty($email)) {
                WP_CLI::warning(sprintf('Skipping "%s": no email on file.', $pending->last));
                $skipped++;
                continue;
            }

            if ($dry_run) {
                WP_CLI::log(sprintf('[DRY RUN] Would email %s <%s> (pending #%d)', $pending->last, $email, $pending->id));
                $sent++;
                continue;
            }

            add_filter('password_reset_expiration', [$this, 'extend_reset_expiration']);
            $key = get_password_reset_key($user);
            remove_filter('password_reset_expiration', [$this, 'extend_reset_expiration']);

            if (is_wp_error($key)) {
                WP_CLI::warning(sprintf('Skipping "%s": failed to generate a confirmation link (%s).', $pending->last, $key->get_error_message()));
                $skipped++;
                continue;
            }

            $confirm_url = esc_url_raw(add_query_arg([
                'usctdp_login' => $user->user_login,
                'usctdp_key' => $key,
            ], $confirm_url_base));

            if (!$this->send_invite_email($email, $pending->last, $confirm_url)) {
                WP_CLI::warning(sprintf('wp_mail failed sending to %s (pending #%d).', $email, $pending->id));
                $skipped++;
                continue;
            }

            (new Usctdp_Mgmt_Import_Pending_Query())->update_item($pending->id, ['invited_at' => current_time('mysql')]);
            $sent++;
        }

        $prefix = $dry_run ? '[DRY RUN] ' : '';
        WP_CLI::log(sprintf('%sSent %d invite(s), skipped %d.', $prefix, $sent, $skipped));
        WP_CLI::success('Done.');
    }

    public function extend_reset_expiration()
    {
        return self::RESET_KEY_TTL;
    }

    private function send_invite_email($to, $last_name, $confirm_url)
    {
        $site_name = get_bloginfo('name');
        $subject = sprintf('Bring your %s family account online', $site_name);
        $message = "Hi {$last_name} family,\n\n"
            . "We've moved to a new registration system, and we found your family's records from a previous season.\n\n"
            . "If you'd like to bring that info (contact details and your students) into a new online account instead of starting from scratch, click the link below:\n\n"
            . "{$confirm_url}\n\n"
            . "This link is unique to your family and expires in 30 days. If you don't recognize this or have questions, just reply to this email.\n\n"
            . "- {$site_name}";

        return wp_mail($to, $subject, $message);
    }
}
