<?php
/**
 * Coordinator Access Control Helpers
 * Restricts coordinator access to specific assigned Project Types and Subtypes.
 */

if (!function_exists('get_coordinator_assignments')) {
    function get_coordinator_assignments($conn, $coordinator_id) {
        $assignments = [
            'type_ids' => [],
            'subtype_ids' => [],
            'type_names' => [],
            'subtype_names' => []
        ];

        $stmt = $conn->prepare("
            SELECT ca.project_type_id, ca.project_subtype_id,
                   pt.type_name, ps.subtype_name
            FROM coordinator_assignments ca
            LEFT JOIN project_types pt ON ca.project_type_id = pt.id
            LEFT JOIN project_subtypes ps ON ca.project_subtype_id = ps.id
            WHERE ca.coordinator_id = ?
        ");
        $stmt->bind_param('i', $coordinator_id);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            if ($row['project_type_id'] && !$row['project_subtype_id']) {
                $assignments['type_ids'][] = $row['project_type_id'];
                if ($row['type_name']) {
                    $assignments['type_names'][] = $row['type_name'];
                }
            }
            if ($row['project_subtype_id']) {
                $assignments['subtype_ids'][] = $row['project_subtype_id'];
                if ($row['subtype_name']) {
                    $assignments['subtype_names'][] = $row['subtype_name'];
                }
            }
        }
        $stmt->close();
        
        // If a coordinator is assigned to a generic type, they have access to ALL subtypes under it.
        // Let's explicitly fetch all subtypes for assigned types so we don't miss anything.
        if (!empty($assignments['type_ids'])) {
            $in_types = implode(',', array_map('intval', $assignments['type_ids']));
            $sub_res = mysqli_query($conn, "SELECT id, subtype_name FROM project_subtypes WHERE project_type_id IN ($in_types)");
            if ($sub_res) {
                while ($sub_row = mysqli_fetch_assoc($sub_res)) {
                    if (!in_array($sub_row['id'], $assignments['subtype_ids'])) {
                        $assignments['subtype_ids'][] = $sub_row['id'];
                    }
                    if (!in_array($sub_row['subtype_name'], $assignments['subtype_names'])) {
                        $assignments['subtype_names'][] = $sub_row['subtype_name'];
                    }
                }
            }
        }

        return $assignments;
    }
}

if (!function_exists('get_coordinator_access_sql')) {
    /**
     * Generates a SQL WHERE condition restricting access based on type and subtype columns.
     * Use this when filtering records (like internships, applications, teams) for a coordinator.
     */
    function get_coordinator_access_sql($conn, $coordinator_id, $type_col, $subtype_col) {
        $assignments = get_coordinator_assignments($conn, $coordinator_id);

        if (empty($assignments['type_names']) && empty($assignments['subtype_names'])) {
            return " (1=0) "; // No assignments = no access
        }

        $conditions = [];
        
        if (!empty($assignments['type_names'])) {
            $type_list = implode("','", array_map(function($val) use ($conn) {
                return mysqli_real_escape_string($conn, $val);
            }, $assignments['type_names']));
            $conditions[] = "$type_col IN ('$type_list')";
        }

        if (!empty($assignments['subtype_names'])) {
            $subtype_list = implode("','", array_map(function($val) use ($conn) {
                return mysqli_real_escape_string($conn, $val);
            }, $assignments['subtype_names']));
            $conditions[] = "$subtype_col IN ('$subtype_list')";
        }

        if (empty($conditions)) {
            return " (1=0) ";
        }

        return " (" . implode(" OR ", $conditions) . ") ";
    }
}

if (!function_exists('has_coordinator_access')) {
    /**
     * Checks if a coordinator has access to a specific type or subtype.
     */
    function has_coordinator_access($conn, $coordinator_id, $type_name, $subtype_name) {
        $assignments = get_coordinator_assignments($conn, $coordinator_id);
        if (in_array($type_name, $assignments['type_names'])) {
            return true;
        }
        if (in_array($subtype_name, $assignments['subtype_names'])) {
            return true;
        }
        return false;
    }
}
