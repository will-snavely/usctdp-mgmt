<?php

class Usctdp_Meta
{
    private $table;
    private $query;

    public function __construct($table)
    {
        $this->table = $table;
    }
    private function get_query($id)
    {
        if (!$this->query) {
            switch ($this->table) {
                case 'product':
                    $this->query = new Usctdp_Mgmt_Product_Query([
                        "id" => $id,
                        "number" => 1
                    ]);
                    break;
                case 'session':
                    $this->query = new Usctdp_Mgmt_Session_Query([
                        "id" => $id,
                        "number" => 1
                    ]);
                    break;
                default:
                    throw new Exception("Unsupported table: $this->table");
            }
        }
        return $this->query;
    }

    public function get_meta($object_id, $meta_key = null)
    {
        $query = $this->get_query($object_id);
        if (empty($query->items)) {
            WP_CLI::error("Object with id '$object_id' not found in table '$this->table'.");
            return;
        }
        $item = $query->items[0];
        $meta_value = $item->meta;
        if ($meta_key) {
            $meta_array = json_decode($meta_value, true);
            WP_CLI::log($meta_array[$meta_key] ?? "Meta key '$meta_key' not found.");
        } else {
            WP_CLI::log($meta_value);
        }
    }

    public function set_meta($object_id, $meta_key, $meta_value)
    {
        $query = $this->get_query($object_id);
        if (empty($query->items)) {
            WP_CLI::error("Object with id '$object_id' not found in table '$this->table'.");
            return;
        }
        $item = $query->items[0];
        $meta_json = json_decode($item->meta, true) ?? [];
        $meta_json[$meta_key] = json_decode($meta_value);

        $query->update_item($object_id, [
            "meta" => json_encode($meta_json),
        ]);
    }

    public function delete_meta($object_id, $meta_key = null)
    {
        $query = $this->get_query($object_id);
        if (empty($query->items)) {
            WP_CLI::error("Object with id '$object_id' not found in table '$this->table'.");
            return;
        }
        if ($meta_key === null) {
            $query->update_item($object_id, [
                "meta" => null,
            ]);
            return;
        } else {
            $item = $query->items[0];
            $meta_value = json_decode($item->meta, true) ?? [];
            unset($meta_value[$meta_key]);

            $query->update_item($object_id, [
                "meta" => json_encode($meta_value),
            ]);
        }
    }
}
