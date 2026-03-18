<?php

class Select2_Search_Exception extends Exception
{
}

class Usctdp_Mgmt_Select2
{
    private $select2_search_targets;

    public function __construct()
    {
        $this->select2_search_targets = [
            'session' => [
                'callback' => $this->select2_session_search(...),
                'filters' => [
                    'active' => intval(...),
                    'category' => intval(...)
                ]
            ],
            'activity' => [
                'callback' => $this->select2_activity_search(...),
                'filters' => [
                    'session_id' => intval(...),
                    'product_id' => intval(...)
                ]
            ],
            'product' => [
                'callback' => $this->select2_product_search(...),
                'filters' => [
                    'type' => intval(...)
                ]
            ],
            'family' => [
                'callback' => $this->select2_family_search(...),
                'filters' => []
            ],
            'student' => [
                'callback' => $this->select2_student_search(...),
                'filters' => [
                    'family_id' => intval(...)
                ]
            ],
        ];
    }

    public function is_valid_target($target)
    {
        return array_key_exists($target, $this->select2_search_targets);
    }

    public function search($target, $search, $filters)
    {
        if ($this->is_valid_target($target)) {
            $search_target = $this->select2_search_targets[$target];
            return $search_target['callback']($search, $filters);
        } else {
            return [];
        }
    }

    public function get_filters($target)
    {
        if ($this->is_valid_target($target)) {
            return $this->select2_search_targets[$target]['filters'];
        } else {
            return [];
        }
    }

    public function select2_session_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Session_Query();
        $active = $filters['active'] ?? null;
        $category = $filters['category'] ?? null;
        $query_results = $query->search_sessions($search, $active, $category, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title
                );
            }
        }
        return $results;
    }

    private function select2_activity_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Activity_Query();
        $session_id = $filters['session_id'] ?? null;
        $product_id = $filters['product_id'] ?? null;
        $query_results = $query->search_activities($search, $session_id, $product_id, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'type' => intval($result->type),
                    'session_id' => intval($result->session_id),
                    'product_id' => intval($result->product_id),
                );
            }
        }
        return $results;
    }

    private function select2_product_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Product_Query();
        $activity_type = $filters['type'] ?? null;
        $type_enum = Usctdp_Product_Type::tryFrom($activity_type);
        $query_results = $query->search_products($search, $type_enum, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'category' => intval($result->session_category),
                    'type' => intval($result->type)
                );
            }
        }
        return $results;
    }

    private function select2_family_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Family_Query();
        $query_results = $query->search_families($search, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'address' => $result->address,
                    'city' => $result->city,
                    'state' => $result->state,
                    'zip' => $result->zip,
                    'phone_numbers' => json_decode($result->phone_numbers),
                    'email' => $result->email,
                    'notes' => $result->notes,
                );
            }
        }
        return $results;
    }

    private function select2_student_search($search, $filters)
    {
        $results = [];
        $query = new Usctdp_Mgmt_Student_Query();
        $family_id = $filters['family_id'] ?? null;
        $query_results = $query->search_students($search, $family_id, 10);
        if ($query_results) {
            foreach ($query_results as $result) {
                $results[] = array(
                    'id' => $result->id,
                    'text' => $result->title,
                    'level' => $result->level,
                    'first' => $result->first,
                    'last' => $result->last,
                );
            }
        }
        return $results;
    }
}
