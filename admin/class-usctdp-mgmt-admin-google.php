<?php

use Google\Client;

class Usctdp_Mgmt_Admin_Google
{
    public function google_oauth_handler()
    {
        $redirect_url = admin_url('admin.php?page=usctdp-admin-main');
        if (!isset($_GET['page']) || $_GET['page'] !== 'usctdp-admin-main') {
            return;
        }

        if (isset($_GET['usctdp_google_auth']) && $_GET['usctdp_google_auth'] === '1') {
            Usctdp_Mgmt::logger()->log_info('Google OAuth Initiated');
            $scopes = ['https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/documents'];
            $client = new Client();
            $client->setClientId(env('GOOGLE_DOCS_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_DOCS_CLIENT_SECRET'));
            $client->setRedirectUri($redirect_url);

            $client->setAccessType('offline');
            $client->setPrompt('consent');
            $client->setScopes($scopes);
            $authUrl = $client->createAuthUrl();
            wp_redirect(filter_var($authUrl, FILTER_SANITIZE_URL));
            exit;
        } else if (isset($_GET['code'])) {
            Usctdp_Mgmt::logger()->log_info('Google OAuth Code Received');
            $unique_token = bin2hex(random_bytes(8));
            $transient_key = Usctdp_Mgmt_Admin::$transient_prefix . '_' . $unique_token;
            $transient_data = null;
            if (!current_user_can('manage_options')) {
                $transient_data = [
                    'type' => 'error',
                    'message' => 'You do not have permission to perform this action.'
                ];
                set_transient($transient_key, $transient_data, 10);
                wp_redirect(add_query_arg(['usctdp_auth_status' => 'error', 'code' => false], $redirect_url));
                exit;
            }

            $client = new Client();
            $client->setClientId(env('GOOGLE_DOCS_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_DOCS_CLIENT_SECRET'));
            $client->setRedirectUri($redirect_url);

            $code = sanitize_text_field(wp_unslash($_GET['code']));
            try {
                $token = $client->fetchAccessTokenWithAuthCode($code);
                if (isset($token['refresh_token'])) {
                    Usctdp_Mgmt::logger()->log_info('Google OAuth Refresh Token Received');
                    update_option('usctdp_google_refresh_token', $token['refresh_token']);
                    update_option('usctdp_google_refresh_token_timestamp', date('Y-m-d H:i:s'));
                    $message = 'Authorization successful! Refresh Token stored.';
                } else {
                    Usctdp_Mgmt::logger()->log_info(' Google OAuth Refresh Token Not Received');
                    $message = 'Authorization successful, but Refresh Token was not returned.';
                    $message .= ' (user may have authorized previously).';
                }
                $transient_data = [
                    'type' => 'success',
                    'message' => $message
                ];
                set_transient($transient_key, $transient_data, 10);
                wp_redirect(add_query_arg(['usctdp_auth_status' => 'success', 'code' => false], $redirect_url));
                exit;
            } catch (Throwable $e) {
                Usctdp_Mgmt::logger()->log_exception("google_oauth_handler", $e);
                $transient_data = [
                    'type' => 'error',
                    'message' => 'An unknown error occurred.'
                ];
                set_transient($transient_key, $transient_data, 10);
                wp_redirect(add_query_arg('usctdp_auth_status', 'error', $redirect_url));
                exit;
            }
        }
    }
}
