<?php

class Usctdp_Build_Program_Schedule
{
    private function get_clinic_activities()
    {
        global $wpdb;
        return $wpdb->get_results(
            "   SELECT
                    act.meta as activity_meta,
                    clinic.day_of_week as day_of_week,
                    clinic.start_time as start_time,
                    clinic.end_time as end_time,
                    sess.title as session_name,
                    sess.start_date as session_start_date,
                    sess.end_date as session_end_date,
                    sess.season as session_season,
                    sess.meta as session_meta,
                    prod.wp_image_id as product_image_id,
                    prod.title as product_name,
                    prod.code as product_code,
                    prod.id as product_id,
                    prod.level as product_level,
                    prod.type as product_type,
                    prod.description as product_description,
                    prod.short_description as product_short_description,
                    prod.age_group as product_age_group,
                    prod.age_range as product_age_range,
                    prod.woocommerce_id as product_woocommerce_id
                FROM {$wpdb->prefix}usctdp_activity AS act
                JOIN {$wpdb->prefix}usctdp_clinic AS clinic ON act.id = clinic.id
                JOIN {$wpdb->prefix}usctdp_session AS sess ON act.session_id = sess.id
                JOIN {$wpdb->prefix}usctdp_product AS prod ON act.product_id = prod.id
                WHERE sess.is_active = 1
                ORDER BY act.id ASC"
        );
    }

    private function get_tournament_activities()
    {
        global $wpdb;
        return $wpdb->get_results(
            "   SELECT
                    tourn.schedule as tournament_schedule,
                    sess.title as session_name,
                    sess.start_date as session_start_date,
                    sess.end_date as session_end_date,
                    sess.season as session_season,
                    sess.meta as session_meta,
                    prod.wp_image_id as product_image_id,
                    prod.title as product_name,
                    prod.code as product_code,
                    prod.id as product_id,
                    prod.level as product_level,
                    prod.type as product_type,
                    prod.description as product_description,
                    prod.short_description as product_short_description,
                    prod.age_group as product_age_group,
                    prod.age_range as product_age_range,
                    prod.woocommerce_id as product_woocommerce_id
                FROM {$wpdb->prefix}usctdp_activity AS act
                JOIN {$wpdb->prefix}usctdp_tournament AS tourn ON act.id = tourn.id
                JOIN {$wpdb->prefix}usctdp_session AS sess ON act.session_id = sess.id
                JOIN {$wpdb->prefix}usctdp_product AS prod ON act.product_id = prod.id
                WHERE sess.is_active = 1
                ORDER BY tourn.start_date ASC"
        );
    }

    private function get_empty_schedule()
    {
        return [
            1 => ['day' => 'Mon', 'day_full' => 'Monday', 'times' => []],
            2 => ['day' => 'Tue', 'day_full' => 'Tuesday', 'times' => []],
            3 => ['day' => 'Wed', 'day_full' => 'Wednesday', 'times' => []],
            4 => ['day' => 'Thu', 'day_full' => 'Thursday', 'times' => []],
            5 => ['day' => 'Fri', 'day_full' => 'Friday', 'times' => []],
            6 => ['day' => 'Sat', 'day_full' => 'Saturday', 'times' => []],
            7 => ['day' => 'Sun', 'day_full' => 'Sunday', 'times' => []],
        ];
    }

    private function int_to_day_of_week($int)
    {
        $days = [
            1 => ["Monday", "Mon"],
            2 => ["Tuesday", "Tue"],
            3 => ["Wednesday", "Wed"],
            4 => ["Thursday", "Thu"],
            5 => ["Friday", "Fri"],
            6 => ["Saturday", "Sat"],
            7 => ["Sunday", "Sun"]
        ];
        return isset($days[$int]) ? $days[$int] : "";
    }

    private function day_name_to_int($day)
    {
        $days = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];
        return $days[strtolower(trim((string) $day))] ?? null;
    }

    private function get_level_color($level)
    {
        $colors = [
            "beginner"     => "#e03535",
            "intermediate" => "#e87722",
            "advanced"     => "#3a9e5f",
            "varies"       => "#466be5",
        ];
        return $colors[strtolower($level)] ?? "#3893de";
    }

    private function format_level_label($level)
    {
        $words = explode(' ', strtolower(trim($level)));
        $words = array_map(fn($word) => $word === 'to' ? $word : ucfirst($word), $words);
        return implode(' ', $words);
    }

    private function ensure_product(&$products, $row)
    {
        $product_id = $row->product_id;
        if (!isset($products[$product_id])) {
            $products[$product_id] = [
                "id" => $product_id,
                "name" => $row->product_name,
                "code" => $row->product_code,
                "description" => $row->product_description,
                "short_description" => $row->product_short_description,
                "image_id" => $row->product_image_id,
                "level" => strtolower($row->product_level),
                "level_label" => ucfirst(strtolower($row->product_level)),
                "type" => strtolower($row->product_type),
                "ball_color" => $this->get_level_color($row->product_level),
                "age_group" => $row->product_age_group,
                "age_range" => $row->product_age_range,
                "woocommerce_id" => $row->product_woocommerce_id,
                "season" => $row->session_season,
                "schedule" => $this->get_empty_schedule(),
                "sessions" => [],
                "schedule_days" => []
            ];
        }
        return $product_id;
    }

    private function add_clinic_activity(&$products, $activity)
    {
        $product_id = $this->ensure_product($products, $activity);
        $dayOfWeek = $this->int_to_day_of_week($activity->day_of_week);
        $dayShort = $dayOfWeek[1];
        $start_time = strtotime($activity->start_time);
        $end_time = strtotime($activity->end_time);

        $activityMeta = json_decode($activity->activity_meta, true) ?: [];
        $activityLevel = $activityMeta['level'] ?? null;

        $products[$product_id]['schedule'][$activity->day_of_week]['times'][$start_time] = [
            'start_time' => date('g:i A', $start_time),
            'end_time' => date('g:i A', $end_time),
            'level_label' => $activityLevel ? $this->format_level_label($activityLevel) : null,
            'level_color' => $activityLevel ? $this->get_level_color($activityLevel) : null,
            'note' => $activityMeta['note'] ?? null,
        ];
        $products[$product_id]['schedule_days'][$dayShort] = 1;

        $session_start = strtotime($activity->session_start_date);
        $session_end = strtotime($activity->session_end_date);
        $sessionMeta = json_decode($activity->session_meta, true) ?: [];
        $products[$product_id]['sessions'][$session_start] = [
            'title' => $activity->session_name,
            'start_date' => date('M j, Y', $session_start),
            'end_date' => date('M j, Y', $session_end),
            'note' => $sessionMeta['note'] ?? null,
        ];
    }

    private function add_tournament_activity(&$products, $tournament)
    {
        $product_id = $this->ensure_product($products, $tournament);

        $scheduleEntries = json_decode($tournament->tournament_schedule, true) ?: [];
        foreach ($scheduleEntries as $entry) {
            $dayOfWeek = isset($entry['day']) ? $this->day_name_to_int($entry['day']) : null;
            if (!$dayOfWeek || empty($entry['startTime']) || empty($entry['endTime'])) {
                continue;
            }
            $start_time = strtotime($entry['startTime']);
            $end_time = strtotime($entry['endTime']);
            $products[$product_id]['schedule'][$dayOfWeek]['times'][$start_time] = [
                'start_time' => date('g:i A', $start_time),
                'end_time' => date('g:i A', $end_time),
                'level_label' => null,
                'level_color' => null,
                'note' => $entry['description'] ?? null,
            ];
            $products[$product_id]['schedule_days'][$this->int_to_day_of_week($dayOfWeek)[1]] = 1;
        }

        $session_start = strtotime($tournament->session_start_date);
        $session_end = strtotime($tournament->session_end_date);
        $sessionMeta = json_decode($tournament->session_meta, true) ?: [];
        $products[$product_id]['sessions'][$session_start] = [
            'title' => $tournament->session_name,
            'start_date' => date('M j, Y', $session_start),
            'end_date' => date('M j, Y', $session_end),
            'note' => $sessionMeta['note'] ?? null,
        ];
    }

    private function save_schedule($product_id, $product)
    {
        $query = new Usctdp_Mgmt_Program_Schedule_Query([
            "product_id" => $product_id,
            "number" => 1,
        ]);
        $data = [
            "product_id" => $product_id,
            "woocommerce_id" => $product['woocommerce_id'],
            "type" => $product['type'],
            "age_group" => $product['age_group'],
            "level" => $product['level'],
            "data" => json_encode($product),
        ];
        if (!empty($query->items)) {
            $query->update_item($query->items[0]->id, $data);
        } else {
            $query->add_item($data);
        }
    }

    public function build()
    {
        $products = [];

        foreach ($this->get_clinic_activities() as $activity) {
            $this->add_clinic_activity($products, $activity);
        }
        foreach ($this->get_tournament_activities() as $tournament) {
            $this->add_tournament_activity($products, $tournament);
        }

        foreach ($products as &$product) {
            ksort($product['schedule']);
            ksort($product['sessions']);
            foreach ($product['schedule'] as &$day) {
                ksort($day['times']);
            }
        }
        unset($product, $day);

        foreach ($products as $product_id => $product) {
            $this->save_schedule($product_id, $product);
        }

        WP_CLI::log('Built program schedule for ' . count($products) . ' product(s).');
    }
}
